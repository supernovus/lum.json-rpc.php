<?php

namespace Lum\JSON\RPC\Exception;

class InvalidResponse extends Exception 
{
  protected $code = -31999;
  protected $message = 'Invalid Response';
}

