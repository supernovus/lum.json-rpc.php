<?php

namespace Lum\JSON\RPC\Client;

use Lum\JSON\RPC\Exception\InvalidResponse;

/**
 * A response from a JSON-RPC server.
 *
 * @property 
 */
class Response
{
  /**
   * If the response was successful, this will be true.
   * If not, this will be false.
   */
  public $success = false;

  /**
   * The id of the response.
   */
  public $id;

  /**
   * If the response is successful, this will be the response data.
   */
  public $result;

  /**
   * If the response is not successful, this will contain:
   * 
   *  JSON-RPC 1.0  The 'error' property if it is an integer, or null.
   *  JSON-RPC 2.0  The 'code' property of the error object.
   */
  public $code;

  /**
   * If the response is not successful, this will contain:
   *
   *  JSON-RPC 1.0  The 'error' property if it is a string, or null.
   *  JSON-RPC 2.0  The 'message' property of the error object.
   */
  public $message;

  /**
   * If the response is not successful, this will contain:
   *
   *  JSON-RPC 1.0  The 'error' property, if it was not a string or integer.
   *  JSON-RPC 2.0  The 'data' property of the error object, if it was set.
   */
  public $error_data;

  protected $client; // The parent client instance.

  /**
   * Create a Client\Response object.
   *
   * @param Lum\JSON\RPC\Client $client  The parent Client instance.
   * @param array $response The response from the server.
   */
  public function __construct (\Lum\JSON\RPC\Client $client, $response)
  {
    $this->client = $client;

    if (isset($response) && is_array($response))
    {
      $ver = $this->client->version;
      if ($ver == 2)
      {
        if (!isset($response['jsonrpc']) || $response['jsonrpc'] != '2.0')
        {
          throw new InvalidResponse("Response was not in JSON-RPC 2.0 format");
        }
      }
      if (array_key_exists('id', $response))
      {
        $this->id = $response['id'];
      }
      else
      {
        throw new InvalidResponse("Response did not have 'id'");
      }
      if (isset($response['error']))
      {
        if ($ver == 2)
        { // JSON-RPC 2.0 has a very specific error object format.
          if (is_array($response['error']) 
            && isset($response['error']['code'])
            && isset($response['error']['message']))
          {
            $this->code    = $response['error']['code'];
            $this->message = $response['error']['message'];
            if (isset($response['error']['data']))
            {
              $this->error_data = $response['error']['data'];
            }
          }
          else
          {
            throw new InvalidResponse("Invalid error object in response.");
          }
        }
        else
        { // JSON-RPC 1.0 has no defined error format, so we guess.
          if (is_array($response['error']) && isset($response['error']['code']))
          { // Using error objects in version 1.0.
            $this->code = $response['error']['code'];
            if (isset($response['error']['message']))
              $this->message = $response['error']['message'];
            if (isset($response['error']['data']))
              $this->error_data = $response['error']['data'];
          }
          if (is_int($response['error']))
          { // A status code.
            $this->code = $response['error'];
          }
          elseif (is_string($response['error']))
          { // An error message.
            $this->message = $response['error'];
          }
          else
          { // Something else.
            $this->error_data = $response['error'];
          }
        }
      }
      elseif (isset($response['result']))
      {
        $this->result = $response['result'];
        $this->success = true;
      }
    }
    else
    {
      throw new InvalidResponse("Response was not a valid JSON object");
    }
  }
}
