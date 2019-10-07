<?php

namespace Lum\JSON\RPC;

/**
 * A simple JSON-RPC client.
 */
class Client
{
  const ID_RAND = 0;
  const ID_TIME = 1;
  const ID_UUID = 2;
  const ID_UNIQ = 3;

  public $version = 1;     // Use 2 for JSON-RPC 2.0
  public $debug   = false; // Enable debugging.
  public $notify  = false; // Force notification mode, use with care.
  public $batch   = false; // If true, use batch procesing (2.0 only.)

  public $idtype  = self::ID_TIME; // How to build request id.
  public $batch_name = 'send';       // Default method name for sending batch.

  public $notifications = []; // A list of methods that are notifications.
  public $named_params  = []; // Any methods that use named params (2.0 only.)

  protected $batch_requests = []; // Any requests in the batch queue.
  protected $transport;           // The transport object.

  /**
   * Build a JSON-RPC client.
   *
   * @param Array $opts   Named parameters.
   *
   *  'debug'         If true, we enable debugging.
   *  'idtype'        Type of id we will add.
   *                  Client::ID_RAND  --  Use rand().
   *                  Client::ID_TIME  --  Use microtime().
   *                  Client::ID_UUID  --  Use \Lum\UUID::v4().
   *                  Client::ID_UNIQ  --  Use uniqid().
   *  'version'       Either 1 (default) or 2 (for JSON-RPC 2.0).
   *  'batch'         If true, we use batch mode (2.0 only)
   *  'batch_name'    Override the method name used to send batch requests.
   *                  We use 'send' by default. (Only if batch is true.)
   *  'notifications' An array of methods that are notifications.
   *  'named_params'  An array of methods that use named params (2.0 only.)
   *  'transport'     An instance of a class implementing the
   *                  Lum\JSON\RPC\Client\Transport interface.
   *                  If not specified, we use the
   *                  \Lum\JSON\RPC\Client\HTTP class by default.
   *
   * Any extra parameters are sent to the Transport object's constructor.
   * The default HTTP transport REQUIRES the 'url' parameter to specify
   * the JSON-RPC URL endpoint.
   *
   * Any of the parameters other than 'transport' can be changed after
   * initialization using object properties of the same name.
   */
  public function __construct (Array $opts)
  { // Handle scalar options.
    foreach (['debug','idtype','version','batch','batch_name'] as $opt)
    {
      if (isset($opts[$opt]) && is_scalar($opts[$opt]))
      {
        $this->$opt = $opts[$opt];
      }
    }
    // Handle array options.
    foreach (['notifications','handle_errors','named_params'] as $opt)
    {
      if (isset($opts[$opt]) && is_array($opts[$opt]))
      {
        $this->$opt = $opts[$opt];
      }
    }

    // Handle our transport.
    if (isset($opts['transport']) && $opts['transport'] instanceof Client\Transport)
    {
      $this->transport = $opts['transport'];
    }
    else
    {
      $this->transport = new Client\HTTP($opts);
    }
  }

  protected static function DEBUG ($message)
  {
    if (!is_string($message))
    {
      $message = json_encode($message);
    }
    error_log("# JSON-RPC: $message");
  }

  /**
   * Builds requests based on object method calls.
   */
  public function __call ($method, $params)
  {
    if (!is_scalar($method))
    {
      throw new Exception\InvalidRequest("Method name has no scalar value.");
    }

    if (!is_array($params))
    {
      throw new Exception\InvalidParams("Parameters must be an array.");
    }

    if ($this->version == 2 && $this->batch && $method == $this->batch_name)
    {
      return $this->_rpc_send_batch_requests();
    }

    $callback = null;
    if ($this->version == 2 && !$this->notify && $this->batch && is_callable($params[0]))
    { // A callback.
      $callback = array_shift($params);
    }

    if ($this->version == 2 && in_array($method, $this->named_params))
    {
      if (is_array($params[0]))
      {
        $params = $params[0];
      }
      else
      {
        throw new Exception\InvalidParams("Named parameters expected in '$method'.");
      }
    }

    $request = [];
    if ($this->version == 2)
    {
      $request['jsonrpc'] = '2.0';
    }
    $request['method'] = $method;
    if ($this->version == 1 || count($params) > 0)
    {
      $request['params'] = $params;
    }

    // Determine if we are sending a notification or a method call.
    if ($this->notify || in_array($method, $this->notifications))
    { // We're sending a notification.
      $notify = true;
      if ($this->version == 1)
      { // version 1 is null id, version 2 is absent id. 
        $request['id'] = null;
      }
    }
    else
    { // We're sending a method call, and need to generate an id.
      $notify = false;
      if ($this->idtype == self::ID_RAND)
      {
        $id = rand();
      }
      elseif ($this->idtype == self::ID_TIME)
      {
        $id = str_replace('.', '', microtime(true));
      }
      elseif ($this->idtype == self::ID_UNIQ)
      {
        $id = uniqid();
      }
      elseif ($this->idtype == self::ID_UUID)
      {
        $id = \Lum\UUID::v4();
      }
      else
      {
        throw new Exception\InvalidRequest("Invalid idtype set.");
      }
      $request['id'] = $id;
    }

    $this->debug && self::DEBUG(['request'=>$request]);

    if ($this->version == 2 && $this->batch)
    {
      $request_spec = ['request'=>$request];
      if (isset($callback)) $request_spec['callback'] = $callback;
      $this->batch_requests[] = $request_spec;
    }
    else
    {
      return $this->_rpc_send_request($request);
    }
  }

  protected function _rpc_send_request ($request)
  {
    $request_json = json_encode($request);
    $response_text = $this->transport->send_request($request_json);
    $this->debug && self::DEBUG(["response_text"=>$response_text]);
    if (isset($request['id']))
    { // We're not a notification, let's parse the response.
      $response_json = json_decode($response_text, true);
      if (isset($response_json))
      {
        $response = new Client\Response($this, $response_json);
        return $response;
      }
      else
      {
        throw new Exception\ParseError();
      }
    }
  }

  protected function _rpc_send_batch_requests ()
  {
    $callbacks = [];
    $requests  = [];
    foreach ($this->batch_requests as $request_spec)
    {
      $requests[] = $req = $request_spec['request'];
      if (isset($req['id']) && isset($request_spec['callback']))
      {
        $callbacks[$req['id']] = $request_spec['callback'];
      }
    }
    $request_json = json_encode($requests);
    $response_text = $this->transport->send_request($request_json);
    $this->debug && self::DEBUG(["response_text"=>$response_text]);
    if (trim($response_text) == '') return; // Empty response.
    $response_json = json_decode($response_text, true);
    if (isset($response_json) && is_array($response_json))
    {
      $responses = [];
      if (isset($response_json['jsonrpc']))
      { // A single response was returned, most likely an error.
        $responses[] = new Client\Response($this, $response_json);
      }
      elseif (isset($response_json[0]))
      { // An array of responses was received.
        foreach ($response_json as $response)
        {
          $responses[] = $response = new Client\Response($this, $response);
          if (isset($response->id) && isset($callbacks[$response->id]))
          { // Send the response to the callback.
            $callbacks[$response->id]($response);
          }
        }
      }
      else
      {
        throw new Exception\InvalidResponse("Batch response was in unrecognized format.");
      }
      return $responses;
    }
    else
    {
      throw new Exception\ParserError();
    }
  }

}
