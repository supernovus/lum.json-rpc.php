<?php

namespace Lum\JSON\RPC;

/**
 * A simple JSON-RPC server framework.
 *
 * Use this Trait in your server class.
 *
 * Define methods that will be your public API.
 *
 * If a method is successful, return whatever scalar or object you want
 * to be returned as the 'result' member of the response.
 *
 * If a method fails, you can throw an Exception or any Exception subclass.
 * There are some predefined Exceptions included in this class, the most
 * useful one for your RPC methods being 'InvalidParams'.
 * If you make your own, be sure to update the 'code'.
 * If you define a getData() method in your custom Exception classes, it can
 * be used to retreive the 'data' portion of the JSON-RPC Error object.
 *
 * Then using whatever transport you want, send incoming requests to the
 * $server->handle_jsonrpc_request(); method.
 */
trait Server
{
  /**
   * A helper function to get a named parameter.
   *
   * @param mixed  $params   The array or object with named parameters.
   * @param string $name     The name of the parameter to look for.
   * @param mixed  $default  The default value if it's not found.
   *
   * @return mixed           The parameter value.
   *
   * A common use for this is if you want to support both positional and
   * named parameters from the same call. This only works if the first
   * parameter is never an array or object.
   *
   *  public function mymethod ($param1, $param2=null, $param3=null)
   *  {
   *    $param2 = self::get_named_param($param1, 'param2', $param2);
   *    $param3 = self::get_named_param($param1, 'param3', $param3);
   *    $param1 = self::get_named_param($param1, 'param1', $param1);
   *    // the rest of the function here.
   *  }
   *
   */
  public static function get_named_param ($params, $name, $default=null)
  {
    if (is_array($params) && isset($params[$name]))
      return $params[$name];
    if (is_object($params) && isset($params->$name))
      return $params->$name;
    return $default;
  }
  
  /**
   * Process a JSON-RPC request.
   *
   * @param Mixed $request  Either the JSON text, Array or object.
   * @param Array $opts     Optional settings.
   *
   * @return Mixed   The response, see below for the possible formats.
   *
   *  Options:
   *
   *   'v1errors'    If true, we use the Server\Error structure even on
   *                 JSON-RPC 1.0 requests. Otherwise version 1.0 will
   *                 use the error message as a string.
   *
   *   'v1named'     If true, we allow named parameters in version 1.0
   *                 requests. This is technically against the spec, but
   *                 plenty of implementations use it, so we support it.
   *
   *   'asobject'    If true, we return the Server\Response object.
   *                 If false, we return a JSON string.
   *
   *   'debug'       If true, we enable some debugging code.
   *
   *   'inbatch'     DO NOT USE, this is used internally to indicate
   *                 we are in a batch operation.
   *
   */
  public function handle_jsonrpc_request ($request, $opts=[])
  {
    $notification = false;

    $version = 1.0;
    $params  = null;
    $result  = null;
    $ropts   = [];

    try
    {
      if (is_string($request))
      {
        $request = json_decode($request, true);
      }
      if (is_array($request))
      {
        if (isset($request[0]))
        { // A numbered array, this is a batch request.
          return $this->handle_jsonrpc_batch_request($request, $opts);
        }
  
        if (isset($request['method']))
        {
          $method = $request['method'];
        }
        else
        {
          throw new Exception\InvalidRequest();
        }
        
        if (isset($request['jsonrpc']))
        {
          $version = (float)$request['jsonrpc'];
        }
        elseif (isset($opts['inbatch']) && $opts['inbatch'])
        {
          throw new Exception\InvalidRequest();
        }
  
        if (isset($request['params']))
        {
          $params = $request['params'];
        }
  
        if (isset($request['id']))
        {
          $ropts['id'] = $request['id'];
        }
        else
        {
          $notification = true;
        }
  
      }
      elseif (is_object($request))
      {
        if (isset($request->method))
        {
          $method = $request->method;
        }
        else
        {
          throw new Exception\InvalidRequest();
        }
  
        if (isset($request->jsonrpc))
        {
          $version = (float)$request->jsonrpc;
        }
        elseif (isset($opts['inbatch']) && $opts['inbatch'])
        { // Batch mode is only valid in JSON-RPC 2.0.
          throw new Exception\InvalidRequest();
        }
        
        if (isset($request->params))
        {
          $params = $request->params;
        }
        
        if (isset($request->id))
        {
          $ropts['id'] = $request->id;
        }
        else
        {
          $notification = true;
        }

      }
      else
      {
        throw new Exception\ParseError();
      }

      $meth = [$this, $method];
      if (is_callable($meth))
      {
        $np = ($version >= 2 || (isset($opts['v1named']) && $opts['v1named']));
        if (is_null($params))
        {
          $result = $meth(); // Call the method with o parameters.
        }
        elseif (is_array($params))
        {
          if (count($params) === 0)
          { // Empty array is the same as null.
            $result = $meth();
          }
          elseif (isset($params[0]))
          { // Positional parameters.
            $result = call_user_func_array($meth, $params);
          }
          elseif ($np)
          { // Named parameters.
            $result = $meth($params);
          }
          else
          {
            throw new Exception\InvalidParams();
          }
        }
        elseif (is_object($params) && $np)
        { // Named parameters using a StdObject instead of an Array.
          $result = $meth($params);
        }
        else
        {
          throw new Exception\InvalidParams();
        }
      }
      else
      {
        throw new Exception\MethodNotFound();
      }
    }
    catch (\Exception $e)
    {
      $message = $e->getMessage();
      if ($version >= 2 || (isset($opts['v1errors']) && $opts['v1errors']))
      {
        $code    = $e->getCode();
        if (is_callable([$e, 'getData']))
        {
          $data = $e->getData();
        }
        else
        {
          $data = null;
        }
        $ropts['error'] = new Server\Error($message, $code, $data);
      }
      else
      {
        $ropts['error'] = $message;
      }
    }

    if ($notification && !isset($ropts['error']))
    {
      return; // Nothing to see here, move alone.
    }

    if (isset($result))
      $ropts['result'] = $result;

    $response = new Server\Response($version, $ropts);

    if (isset($opts['asobject']) && $opts['asobject'])
      return $response;
    else
      return json_encode($response);
  }

  // Process a batch JSON-RPC request.
  protected function handle_jsonrpc_batch_request ($requests, $opts)
  {
    $sopts = $opts; // make a copy of the options.
    $sopts['asobject'] = true; // Force the 'asobject' option.
    $sopts['inbatch']  = true; // We are in a batch operation.

    $responses = [];

    foreach ($requests as $request)
    {
      $response = $this->handle_jsonrpc_request($request, $sopts);
      if (isset($response))
        $responses[] = $response;
    }

    if (isset($opts['asobject']) && $opts['asobject'])
      return $responses;
    else
      return json_encode($responses);
  }

}

