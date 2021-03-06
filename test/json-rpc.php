<?php

namespace Lum\Test;

require_once 'vendor/autoload.php';

\Lum\Test\Functional::start();

use Lum\JSON\RPC\Client;
use Lum\JSON\RPC\Server;
use Lum\JSON\RPC\Client\Transport;
use Lum\JSON\RPC\Exception\Exception;
use Lum\JSON\RPC\Exception\InvalidParams;

plan(37);

$DEBUG = false;

/**
 * A test Transport class that acts as a Server.
 */
class TestServer implements Transport
{
  use Server;

  public $use_v1_errors = false;
  public $use_v1_named  = false;

  protected $sessions = [];

  public function send_request ($request)
  {
    $opts = 
    [
      'v1errors' => $this->use_v1_errors,
      'v1named'  => $this->use_v1_named,
    ];
    return $this->handle_jsonrpc_request($request, $opts);
  }

  public function start_session ()
  {
    $sid = uniqid();
    return $this->sessions[$sid] = ['sid'=>$sid, 'started'=>microtime(true)];
  }

  protected function validate_session ($sid)
  {
    if (!isset($sid))
      throw new InvalidParams();
    if (!isset($this->sessions[$sid]))
      throw new InvalidSID();
  }

  public function get_session_data ($sid, $key=null)
  {
    $key = self::get_named_param($sid, 'key', $key);
    $sid = self::get_named_param($sid, 'sid', $sid);
    $this->validate_session($sid);
    if (isset($key))
    {
      if (isset($this->sessions[$sid][$key]))
      {
        return $this->sessions[$sid][$key];
      }
      else
      {
        throw new InvalidKey();
      }
    }
    return $this->sessions[$sid];
  }

  public function set_session_data ($sid, $key=null, $val=null)
  {
    $key = self::get_named_param($sid, 'key',   $key);
    $val = self::get_named_param($sid, 'value', $val);
    $sid = self::get_named_param($sid, 'sid',   $sid);
    $this->validate_session($sid);
    if (!isset($key, $val))
      throw new InvalidParams();
    $this->sessions[$sid][$key] = $val;
    return true;
  }

  public function keepalive ($sid)
  {
    $sid = self::get_named_param($sid, 'sid', $sid);
    $this->validate_session($sid);
    if (isset($this->sessions[$sid]['keepalive']))
    {
      $this->sessions[$sid]['keepalive'] += 1;
    }
    else
    {
      $this->sessions[$sid]['keepalive'] = 1;
    }
  }

  public function end_session ($sid)
  {
    $data = $this->get_session_data($sid);
    $data['finished'] = microtime(true);
    $sid = self::get_named_param($sid, 'sid', $sid);
    unset($this->sessions[$sid]);
    return $data;
  }

}

class InvalidSID extends Exception
{
  protected $code    = 1000;
  protected $message = 'Invalid session id';
}

class InvalidKey extends Exception
{
  protected $code    = 1001;
  protected $message = 'Invalid key';
}

function jerr ($r, $n="response")
{
  error_log("# $n: ".json_encode($r));
}

$server = new TestServer();

$client = new Client(['transport'=>$server, 'debug'=>$DEBUG]);
$client->notifications[] = 'keepalive';

$response = $client->start_session();

if ($DEBUG)
  jerr($response);

ok($response->success, "start_session() returned ok");

ok(isset($response->result, $response->result['sid']), "sid was set");

$sid = $response->result['sid'];

ok (isset($response->result, $response->result['started']), "started was set");

$started = $response->result['started'];

$response = $client->keepalive($sid); // keepalive = 1

is($response, null, "keepalive returns null");

$response = $client->set_session_data($sid, 'hello', 'world');

if ($DEBUG)
  jerr($response);

ok($response->success, "set_session_data() returned ok");

is($response->result, true, "set_session_data() returned true");

$client->keepalive($sid); // keepalive = 2

$response = $client->get_session_data($sid, 'hello');

ok($response->success, "get_session_data(sid, key) returned ok");

is($response->result, 'world', "get_session_data(sid, key) returned proper data");

$client->keepalive($sid); // keepalive = 3

$response = $client->get_session_data($sid);

if ($DEBUG)
  jerr($response);

ok($response->success, "get_session_data(sid) returned ok");

ok(isset($response->result, $response->result['keepalive']), "get_session_data(sid) returned proper data");

is($response->result['keepalive'], 3, 'keepalive is proper value');

$response = $client->set_session_data($sid);

if ($DEBUG)
  jerr($response);

ok(!$response->success, "missing parameters returns not ok");

is ($response->message, 'Invalid params', 'correct error message');

$response = $client->set_session_data('foo', 'hello', 'world');

ok (!$response->success, "invalid parameter value returns not ok");

is ($response->message, 'Invalid session id', 'correct error message');

$response = $client->end_session($sid);

ok ($response->success, "end_session() returned ok");

ok (isset($response->result, $response->result['finished']), 'finished was set');

$finished = $response->result['finished'];

ok(($finished > $started), 'finished is greater than started');

// Test JSON-RPC 2.0, starting with named parameters.
$client->version = 2;
$client->named_params = ['get_session_data','set_session_data'];

$response = $client->start_session();

if ($DEBUG)
  jerr($response);

ok($response->success, "2.0 start_session() returned ok");

$sid = $response->result['sid'];

$client->keepalive($sid); // ka = 1

$response = $client->set_session_data(['sid'=>$sid,'key'=>'goodbye','value'=>'universe']);

ok($response->success, "2.0 set_session_data(named_params) returned ok");

$response = $client->get_session_data(['sid'=>$sid, 'key'=>'goodbye']);

ok($response->success, "2.0 get_session_data(named_params) returned ok");

is($response->result, 'universe', "2.0 get_session_data(named_params) returned proper data");

// Now to test 2.0 batch mode.
$client->named_params = [];
$client->batch = true;

$cb1 = function ($res)
{
  ok($res->success, "batch callback 1 returned ok");
  is($res->result, 'bar', 'batch callback 1 returned proper data');
};

$cb2 = function ($res)
{
  ok($res->success, "batch callback 2 returned ok");
  ok(isset($res->result, $res->result['finished']), "batch callback 2 returned proper data");
  $finished = $res->result['finished'];
  $started  = $res->result['started'];
  ok(($finished > $started), "batch callback 2 finished is greater than started");
};

$client->keepalive($sid); // ka = 2
$client->set_session_data($sid, 'foo', 'bar');  // response 1
usleep(500);
$client->keepalive($sid); // ka = 3
$client->get_session_data($cb1, $sid, 'foo');   // response 2
usleep(500);
$client->get_session_data($sid, 'bar');         // response 3
usleep(500);
$client->keepalive($sid); // ka = 4
$client->get_session_data($sid, 'keepalive');   // response 4
usleep(500);
$client->end_session($cb2, $sid);               // response 5

$responses = $client->send();

is(count($responses), 5, 'correct number of responses from batch request');

$response = array_shift($responses); // response 1

ok($response->success, "batch response 1 returned ok");

$response = array_shift($responses); // response 2

ok($response->success, "batch response 2 returned ok");
is($response->result, 'bar', 'batch response 2 returned proper data');

$response = array_shift($responses); // response 3

ok(!$response->success, "batch response 3 returned not ok");
is($response->code, 1001, "batch response 3 returned proper error code");
is($response->message, 'Invalid key', "batch response 3 returned proper message");

$response = array_shift($responses); // response 4

ok($response->success, "batch response 4 returned ok");
is ($response->result, 4, "batch response 4 returned proper data");

$response = array_shift($responses); // response 5

ok($response->success, "batch response 5 returned ok");

echo get_tap();
return test_instance();

