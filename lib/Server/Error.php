<?php

namespace Lum\JSON\RPC\Server;

class Error implements \JsonSerializable
{
  public $code;
  public $message;
  public $data;

  public function __construct ($message, $code = 0, $data = null)
  {
    $this->code = $code;
    $this->message = $message;
    $this->data = $data;
  }

  public function jsonSerialize ()
  {
    $return =
    [
      'code'    => $this->code,
      'message' => $this->message,
    ];
    if (isset($this->data))
    {
      $return['data'] = $this->data;
    }
    return $return;
  }
}

