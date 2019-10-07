<?php

namespace Lum\JSON\RPC\Exception;

/**
 * The method does not exist / is not available.
 */
class MethodNotFound extends Exception
{
  protected $code    = -32601;
  protected $message = 'Method not found';
}

