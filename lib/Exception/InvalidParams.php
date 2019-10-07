<?php

namespace Lum\JSON\RPC\Exception;

/**
 * Invalid method parameter(s).
 */
class InvalidParams extends Exception
{
  protected $code    = -32602;
  protected $message = 'Invalid params';
}

