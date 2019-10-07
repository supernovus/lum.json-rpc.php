<?php

namespace Lum\JSON\RPC\Server;

class Response implements \JsonSerializable
{
  public $version;
  public $result;
  public $error;
  public $id;

  public function __construct ($version, $opts)
  {
    $this->version = $version;

    if (isset($opts['result']))
    {
      $this->result = $opts['result'];
    }

    if (isset($opts['error']))
    {
      $this->error = $opts['error'];
    }

    if (isset($opts['id']))
    {
      $this->id = $opts['id'];
    }
  }

  public function jsonSerialize ()
  {
    $return = [];
    $ver2 = false;

    if ($this->version >= 2)
    {
      $ver2 = true;
      $return['jsonrpc'] = sprintf("%.1f", $this->version);
    }

    if (isset($this->result))
    {
      $return['result'] = $this->result;
    }
    elseif (!$ver2)
    {
      $return['result'] = null;
    }
    if (isset($this->error))
    {
      $return['error'] = $this->error;
    }
    elseif (!$ver2)
    {
      $return['error'] = null;
    }
    if (isset($this->id))
    {
      $return['id'] = $this->id;
    }
    else
    {
      $return['id'] = null;
    }

    return $return;
  }

}

