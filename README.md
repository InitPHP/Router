# InitPHP Router

This is an open source library that lets you create and manage advanced routes for HTTP requests.

[![Latest Stable Version](http://poser.pugx.org/initphp/router/v)](https://packagist.org/packages/initphp/router) [![Total Downloads](http://poser.pugx.org/initphp/router/downloads)](https://packagist.org/packages/initphp/router) [![Latest Unstable Version](http://poser.pugx.org/initphp/router/v/unstable)](https://packagist.org/packages/initphp/router) [![License](http://poser.pugx.org/initphp/router/license)](https://packagist.org/packages/initphp/router) [![PHP Version Require](http://poser.pugx.org/initphp/router/require/php)](https://packagist.org/packages/initphp/router)

## Features

- Full support for GET, POST, PUT, DELETE, OPTIONS, PATCH, HEAD, AJAX and ANY request methods.
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

## Requirements

- PHP 7.4 or higher
- Apache is; **AllowOverride All** should be set to and **mod_rewrite** should be on.
- [InitPHP HTTP](https://github.com/InitPHP/HTTP)

## Installation

```
composer require initphp/router --no-dev
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

		fastcgi_pass unix:/var/run/php5-fpm.sock;
		fastcgi_index index.php;
		include fastcgi.conf;
		fastcgi_intercept_errors on;
	}
}
```


## Configuration

```php
$config = [
    'controller'        => [
        'path'      => null, //The full path to the directory where the Controller classes are kept.
        'namespace' => null, //Namespace prefix of Controller classes, if applicable.
    ],
    'middleware'        => [
        'path'      => null, //The full path to the directory where the Middleware classes are kept.
        'namespace' => null, //Namespace prefix of Middleware classes, if applicable.
    ],
    'basePath'          => null, // If you are working in a subdirectory; identifies your working directory.
    'variableMethods'   => false, // It makes the request method mutable with Laravel-like $_REQUEST['_method'].
];
```

## Usage

_**See the Wiki for detailed documentation.**_

```php
require_once "vendor/autoload.php";
use \InitPHP\HTTP\{Request, Response, Stream, Emitter};
use \InitPHP\Router\Router;

if(($headers = function_exists('apache_request_headers') ? apache_request_headers() : []) === FALSE){
    $headers = [];
}

$uri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
        . '://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];

// Construct the HTTP request object.
$request = new Request(
    ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
    $uri,
    $headers,
    null,
    '1.1'
);

// Create a new HTTP response object.
$response = new Response(200, [], (new Stream('', null)), '1.1');

// Create the router object.
$router = new Router($request, $response, []);

// ... Create routes.
$router->get('/', function () {
    return 'Hello World!';
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

## Credits

- [Muhammet ŞAFAK](https://www.muhammetsafak.com.tr) <<info@muhammetsafak.com.tr>>

## License

Copyright &copy; 2022 [MIT Licence](./LICENSE)
