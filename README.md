# InitPHP Router

## Features

- Full support for GET, POST, PUT, DELETE, OPTIONS, PATCH, HEAD, AJAX and ANY request methods.
- Variable request methods with (Laravel-like) `$_POST['_method']`. (Default is off, optionally can be activated)
- Controller support. (HomeController@about or HomeController::about)
- Middleware (before and after) support.
- Static and dynamic route patterns.
- Ability to create custom parameter pattern.
- Namespace support.
- Route grouping support.
- Domain-based routing support.
- Ability to define custom 404 errors
- Ability to name routes
- Ability to call a class in (Symfony-like) callable functions or parameters of controller methods.
- CLI Routing

## Requirements

- PHP 7.4 or higher
- Apache is; **AllowOverride All** should be set to and **mod_rewrite** should be on.
- [InitPHP HTTP](https://github.com/InitPHP/HTTP)

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
    'view'              => [
        'path'      => null, // The full path to the directory where view files will be searched for view routes.
        'extension' => '.php', // The default file extension of view files.
    ],
    'mainDomain'        => null, // If routing will be done on a domain basis, it defines the main domain.
    'basePath'          => null, // If you are working in a subdirectory; identifies your working directory.
    'variableMethods'   => false, // It makes the request method mutable with Laravel-like $_POST['_method'].
    'debug'             => false, // Debug mode opens.
];
```

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
	server_name myprojectsite.dev;
	root /var/www/myprojectsite/public;

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

## Usage

## Credits

- [Muhammet ŞAFAK](https://www.muhammetsafak.com.tr) <<info@muhammetsafak.com.tr>>

## License

Copyright &copy; 2022 [MIT Licence](./LICENSE)
