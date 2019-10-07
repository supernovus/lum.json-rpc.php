<?php

namespace Lum\JSON\RPC\Exception;

/**
 * Invalid JSON was received by the server.
 * An error occurred on the server while parsing the JSON text.
 */
class ParseError extends Exception
{
  protected $code    = -32700;
  protected $message = 'Parse error'; 
}

