<?php

namespace Lum\JSON\RPC\Exception;

/**
 * The JSON sent is not a valid Request object.
 */
class InvalidRequest extends Exception
{
  protected $code    = -32600;
  protected $message = 'Invalid Request';
}

