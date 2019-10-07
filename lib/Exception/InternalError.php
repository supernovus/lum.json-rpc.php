<?php

namespace Lum\JSON\RPC\Exception;

/**
 * Internal JSON-RPC error.
 */
class InternalError extends Exception
{
  protected $code    = -32603;
  protected $message = 'Internal error';
}

