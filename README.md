# InitPHP Router

This is an open source library that lets you create and manage advanced routes for HTTP requests.

[![Latest Stable Version](http://poser.pugx.org/initphp/router/v)](https://packagist.org/packages/initphp/router) [![Total Downloads](http://poser.pugx.org/initphp/router/downloads)](https://packagist.org/packages/initphp/router) [![Latest Unstable Version](http://poser.pugx.org/initphp/router/v/unstable)](https://packagist.org/packages/initphp/router) [![License](http://poser.pugx.org/initphp/router/license)](https://packagist.org/packages/initphp/router) [![PHP Version Require](http://poser.pugx.org/initphp/router/require/php)](https://packagist.org/packages/initphp/router)

## Features

- Full support for GET, POST, PUT, DELETE, OPTIONS, PATCH, HEAD and ANY request methods.
- Variable request methods with (Laravel-like) `$_REQUEST['_method']`. (Default is off, optionally can be activated)
- Controller support. (HomeController@about or HomeController::about)
- Middleware/Filter (before and after) support.
- Static and dynamic route patterns.
- Ability to create custom parameter pattern.
- Namespace support.
- Route grouping support.
- Domain-based routing support.
- Ability to define custom 404 errors
- Ability to name routes
- Ability to call a class in (Symfony-like) callable functions or parameters of controller methods.
- Routing by request ports.
- Routing by client IP address (Via `$_SERVER['REMOTE_ADDR']`. Locally, this value can be something like `::1` or `127.0.0.1`.)
- A directory path can be defined as a virtual link.

## Requirements

- PHP 7.2 or later
- Apache is; **AllowOverride All** should be set to and **mod_rewrite** should be on.
- Any library that implements the [Psr-7 HTTP Message Interface](https://www.php-fig.org/psr/psr-7/) and an emitter written for Psr-7. For example; [InitPHP HTTP](https://github.com/InitPHP/HTTP) Library

## Installation

```
composer require initphp/router
```

Is Apache `.htaccess`

```
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [L]
```

Is NGINX;

```
server {
	listen 80;
	server_name myinitphpdomain.dev;
	root /var/www/myInitPHPDomain/public;
	index index.php;
	location / {
		try_files $uri $uri/ /index.php?$query_string;
	}
	location ~ \.php$ {
		fastcgi_split_path_info ^(.+\.php)(/.+)$;
		fastcgi_pass unix:/var/run/php7.4-fpm.sock;
		fastcgi_index index.php;
		include fastcgi.conf;
		fastcgi_intercept_errors on;
	}
}
```

## Configuration

```php
$config = [
    'paths'                 => [
        'controller'            => null, //The full path to the directory where the Controller classes are kept.
        'middleware'            => null, //The full path to the directory where the Middleware classes are kept.
    ],
    'namespaces'            => [
        'controller'            => null, //Namespace prefix of Controller classes, if applicable.
        'middleware'            => null, //Namespace prefix of Middleware classes, if applicable.
    ],
    'base_path'             => '/', // If you are working in a subdirectory; identifies your working directory.
    'variable_method'       => false, // It makes the request method mutable with Laravel-like $_REQUEST['_method'].
    'argument_new_instance' => false, // This configuration is used for Request and Response objects that you want as arguments.
];
```

## Usage

The following example uses the [InitPHP HTTP](https://github.com/InitPHP/HTTP) library. If you wish, you can use this library using the command below, or you can perform similar operations using another library that uses the Psr-7 HTTP Message interface.

```
composer require initphp/http
```

_**See the Wiki for detailed documentation.**_

```php
require_once "vendor/autoload.php";
use \InitPHP\HTTP\Message\{Request, Response, Stream};
use \InitPHP\HTTP\Emitter\Emitter;
use \InitPHP\Router\Router;

$request = Request::createFromGlobals();
$response = new Response();

// Create the router object.
$router = new Router($request, $response, []);

// ... Create routes.
$router->get('/', function () {
    return 'Hello World!';
});

$router->post('/login', function (Request $request, Response $response) {
    return $response->json([
        'status'        => 0,
        'message'       => 'Unauthorized',
    ], 401);
});

// If you do not make a definition for 404 errors; An exception is thrown if there is no match with the request.
$router->error_404(function () {
    echo 'Page Not Found';
});

// Resolve the current route and get the generated HTTP response object.
$response = $router->dispatch();

// Publish the HTTP response object.
$emitter = new Emitter;
$emitter->emit($response);
```

## Getting Help

If you have questions, concerns, bug reports, etc, please file an issue in this repository's Issue Tracker.

## Getting Involved

> All contributions to this project will be published under the MIT License. By submitting a pull request or filing a bug, issue, or feature request, you are agreeing to comply with this waiver of copyright interest.

There are two primary ways to help:

- Using the issue tracker, and
- Changing the code-base.

### Using the issue tracker

Use the issue tracker to suggest feature requests, report bugs, and ask questions. This is also a great way to connect with the developers of the project as well as others who are interested in this solution.

Use the issue tracker to find ways to contribute. Find a bug or a feature, mention in the issue that you will take on that effort, then follow the Changing the code-base guidance below.

### Changing the code-base

Generally speaking, you should fork this repository, make changes in your own fork, and then submit a pull request. All new code should have associated unit tests that validate implemented features and the presence or lack of defects. Additionally, the code should follow any stylistic and architectural guidelines prescribed by the project. In the absence of such guidelines, mimic the styles and patterns in the existing code-base.

## Credits

- [Muhammet ŞAFAK](https://www.muhammetsafak.com.tr) <<info@muhammetsafak.com.tr>>

## License

Copyright &copy; 2022 [MIT Licence](./LICENSE)
