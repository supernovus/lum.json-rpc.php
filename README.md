# lum.json-rpc.php

## Summary

JSON-RPC Client and Server libraries.

## Classes

### Client Classes

| Class                         | Description                                 |
| ----------------------------- | ------------------------------------------- |
| Lum\JSON\RPC\Client           | The JSON-RPC client class.                  |
| Lum\JSON\RPC\Client\Response  | A response from a client request.           |
| Lum\JSON\RPC\Client\Transport | Transport interface.                        |
| Lum\JSON\RPC\Client\HTTP      | HTTP Transport class (default transport).   |
| Lum\JSON\RPC\Client\Curl      | Curl Transport class (requires Lum\Curl).   |

### Server Classes

| Class                         | Description                                 |
| ----------------------------- | ------------------------------------------- |
| Lum\JSON\RPC\Server           | A trait for JSON-RPC servers.               |
| Lum\JSON\RPC\Server\Error     | A class representing an error response.     |
| Lum\JSON\RPC\Server\Response  | A class representing a successful response. |

### Exception Classes

A few custom exceptions may be thrown from both the client and server.

| Class                                  | Description                        |
| -------------------------------------- | ---------------------------------- |
| Lum\JSON\RPC\Exception\Exception       | Base class for exceptions.         |
| Lum\JSON\RPC\Exception\InternalError   | Internal error occurred.           |
| Lum\JSON\RPC\Exception\InvalidParams   | Parameters were invalid.           |
| Lum\JSON\RPC\Exception\InvalidRequest  | Request was not valid.             |
| Lum\JSON\RPC\Exception\InvalidResponse | Response was not valid.            |
| Lum\JSON\RPC\Exception\MethodNotFound  | No such method.                    |
| Lum\JSON\RPC\Exception\ParseError      | Could not parse JSON.              |

## Tests

Run `composer test` to test the libraries.

## Official URLs

This library can be found in two places:

 * [Github](https://github.com/supernovus/lum.json-rpc.php)
 * [Packageist](https://packagist.org/packages/lum/lum-json-rpc)

## Author

Timothy Totten

## License

[MIT](https://spdx.org/licenses/MIT.html)
