<?php

/**
 * Router.php
 *
 * This file is part of Router.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.2.1
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Router;

use InitPHP\Router\Exception\{InvalidArgumentException, PageNotFoundException, RouterException};
use Psr\Container\ContainerInterface;
use Psr\Http\Message\{RequestInterface, ResponseInterface, StreamInterface};

class Router
{
    public const BOTH = 0; // both
    public const BEFORE = -1; // before
    public const AFTER = 1; // after

    public const ROUTE = 0;

    public const LINK = 1;

    public const SUPPORTED_METHODS = [
        'GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'PATCH', 'OPTIONS',
        'ANY', 'LINK',
    ];

    /** @var array<string, mixed> */
    protected array $cacheConfigs = [
        'enable'    => false,
        'path'      => null,
        'ttl'       => 86400,
    ];

    protected bool $cacheStatus = false;

    /** @var array<string, mixed> */
    protected array $configs = [
        'paths'                     => [
            'controller'                => null,
            'middleware'                => null,
        ],
        'namespaces'                => [
            'controller'                => null,
            'middleware'                => null,
        ],
        'base_path'                 => '/',
        'variable_method'           => false,
        'argument_new_instance'     => false,
    ];

    protected bool $argumentNewInstance = false;

    /** @var array<string, string> */
    protected array $patterns = [
        '{[^/]+}'           => '([^/]+)',
        ':any[0-9]?'        => '([^/]+)',
        '{any[0-9]?}'       => '([^/]+)',
        ':id[0-9]?'         => '(\d+)',
        '{id[0-9]?}'        => '(\d+)',
        ':int[0-9]?'        => '(\d+)',
        '{int[0-9]?}'       => '(\d+)',
        ':number[0-9]?'     => '([+-]?([0-9]*[.])?[0-9]+)',
        '{number[0-9]?}'    => '([+-]?([0-9]*[.])?[0-9]+)',
        ':float[0-9]?'      => '([+-]?([0-9]*[.])?[0-9]+)',
        '{float[0-9]?}'     => '([+-]?([0-9]*[.])?[0-9]+)',
        ':bool[0-9]?'       => '(true|false|1|0)',
        '{bool[0-9]?}'      => '(true|false|1|0)',
        ':string[0-9]?'     => '([\w\-_]+)',
        '{string[0-9]?}'    => '([\w\-_]+)',
        ':slug[0-9]?'       => '([\w\-_]+)',
        '{slug[0-9]?}'      => '([\w\-_]+)',
        ':uuid[0-9]?'       => '([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})',
        '{uuid[0-9]?}'      => '([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})',
        ':date[0-9]?'       => '([0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1]))',
        '{date[0-9]?}'      => '([0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1]))',
        ':locale'           => '([A-Za-z]{2}|[A-Za-z]{2}[\_\-]{1}[A-Za-z]{2})',
        '{locale}'          => '([A-Za-z]{2}|[A-Za-z]{2}[\_\-]{1}[A-Za-z]{2})',
        ':everything'       => '(.*)',
        '{everything}'      => '(.*)',
    ];

    protected RequestInterface $request;

    protected ResponseInterface $response;

    protected Uri $uri;

    protected ?ContainerInterface $container = null;

    protected int $id = 0;

    /** @var array<int, array<string, mixed>> */
    protected array $routes = [];

    /** @var array<string, list<int>> */
    protected array $methodIds = [];

    /** @var array<string, int> */
    protected array $names = [];

    /** @var array<string, mixed> */
    protected array $error404 = [
        'execute'   => null,
        'options'   => [
            'arguments' => [],
        ],
    ];

    /** @var array<string, mixed> */
    protected array $groupOptions = [];

    /** @var array<string, mixed>|null */
    private ?array $currentRoute = null;

    private string $currentUrl = '';

    private ?string $currentIp = null;

    private string $currentMethod = '';

    /** @var array<string, object> */
    private array $filterObjects = [];

    private string $content = '';

    /** @var string|null Class name of the controller handling the current route, or '__CALLABLE__' for closures. */
    private ?string $currentController = null;

    /** @var string|null Method name handling the current route, or '' for closures. */
    private ?string $currentControllerMethod = null;

    /** @var array<int, mixed> Arguments extracted from the current route. */
    private array $currentArguments = [];

    public function __construct(RequestInterface $request, ResponseInterface $response, array $configs = [])
    {
        if (isset($configs['container'])) {
            $this->container = $configs['container'];
            unset($configs['container']);
        }
        if (isset($configs['cache'])) {
            $this->cacheConfigs = \array_merge($this->cacheConfigs, $configs['cache']);
            unset($configs['cache']);
        }
        $this->configs = \array_merge($this->configs, $configs);
        $this->argumentNewInstance = $this->configs['argument_new_instance'];
        unset($this->configs['argument_new_instance']);
        $this->request = $request;
        $this->response = $response;
        $this->uri = new Uri($this->request->getUri()->__toString());

        $this->currentUrl = $this->uri->__toString();
        $this->currentIpBoot();
        $this->currentMethodBoot();

        foreach (self::SUPPORTED_METHODS as $method) {
            $this->methodIds[$method] = [];
        }

        $this->cacheStart();
    }

    public function __destruct()
    {
        $this->cacheStop();
        $this->destroy();
    }

    public function getRoutes(?string $method = null): array
    {
        $res = [];
        if ($method === null) {
            foreach ($this->routes as $route) {
                foreach ($route['methods'] as $_method) {
                    if (!isset($res[$_method])) {
                        $res[$_method] = [];
                    }
                    $res[$_method][$route['path']] = [
                        'execute'   => $route['execute'],
                        'options'   => $route['options'],
                    ];
                }
            }
            return $res;
        }
        $method = \strtoupper($method);
        if (isset($this->methodIds[$method])) {
            foreach ($this->methodIds[$method] as $id) {
                $route = $this->routes[$id];
                $res[$route['path']] = [
                    'execute'   => $route['execute'],
                    'options'   => $route['options'],
                ];
            }
        }
        return $res;
    }

    public function destroy(): void
    {
        $this->content = '';
        $this->routes = [];
        $this->methodIds = [];
        $this->id = 0;
        $this->names = [];
        $this->error404 = [
            'execute'   => null,
            'options'   => [
                'arguments' => [],
            ],
        ];
        $this->currentController = null;
        $this->currentControllerMethod = null;
        $this->currentArguments = [];
        $this->currentRoute = null;
        $this->currentUrl = '';
        $this->currentMethod = '';
        $this->currentIp = null;
    }

    /**
     * Returns the controller class that handled the current route.
     *
     * @return string|null The controller class name, '__CALLABLE__' when a
     * closure handled the route, or null before dispatch().
     */
    public function getCurrentController(): ?string
    {
        return $this->currentController;
    }

    /**
     * Returns the controller method that handled the current route.
     *
     * @return string|null The method name, an empty string when a closure
     * handled the route, or null before dispatch().
     */
    public function getCurrentControllerMethod(): ?string
    {
        return $this->currentControllerMethod;
    }

    /**
     * Returns the arguments extracted from the current route.
     *
     * @return array<int, mixed>
     */
    public function getCurrentArguments(): array
    {
        return $this->currentArguments;
    }

    /**
     * Adds a route.
     *
     * @param string|string[] $methods <p>An array or "|" separated string.</p>
     * @param string $path
     * @param string|callable|array $execute
     * @param array $options
     * @return $this
     */
    public function register($methods, string $path, $execute, array $options = []): self
    {
        if ($this->cacheStatus) {
            return $this;
        }
        if (!\is_string($execute) && !\is_callable($execute) && !\is_array($execute)) {
            throw new InvalidArgumentException('$execute can be string, callable, or array.');
        }
        if (!\is_string($methods) && !\is_array($methods)) {
            throw new InvalidArgumentException('$methods can be string or array.');
        }

        ++$this->id;

        $options = $this->optionsMerge($this->configs, $this->groupOptions, $options);

        $methods = $this->registerMethodsCheck($methods);

        $path = (isset($options['base_path']) && $options['base_path'] != '/' ? \rtrim($options['base_path'], '/') . '/' : '')
            . ($options['prefix'] ?? '')
            . $path;
        $path = (string)\preg_replace('#/{2,}#', '/', $path);

        $uri = clone $this->uri;
        $uri->withPath($path);

        if (isset($options['host']) && !empty($options['host']) && \is_string($options['host'])) {
            $uri->withHost($options['host']);
            unset($options['host']);
        }
        if (isset($options['port']) && !empty($options['port']) && \is_int($options['port'])) {
            $uri->withPort($options['port']);
            unset($options['port']);
        }
        if (isset($options['scheme']) && \in_array($options['scheme'], ['http', 'https'], true)) {
            $uri->withScheme($options['scheme']);
            unset($options['scheme']);
        }

        if (isset($options['name'])) {
            if (\is_array($options['name'])) {
                $options['name'] = \implode('', $options['name']);
            }
            if (isset($options['as'])) {
                $options['name'] = $options['as'] . $options['name'];
            }
            $name = \trim($options['name']);
            unset($options['name']);
            if (isset($this->names[$name])) {
                throw new RouterException('The name "' . $name . '" is already in use by another route.');
            }
            $this->names[$name] = $this->id;
        }
        $this->routes[$this->id] = [
            'id'        => $this->id,
            'methods'   => $methods,
            'path'      => $uri->__toString(),
            'execute'   => $execute,
            'options'   => $options,
        ];
        $this->methodRouteRegister($this->id, $methods);

        return $this;
    }

    public function link(string $link, string $source, array $options = []): self
    {
        if (!\file_exists($source)) {
            throw new InvalidArgumentException('$source can be directory or file.');
        }
        if (\is_dir($source)) {
            $link = \rtrim($link, '/') . '/(.*)';
        }

        return $this->register(['LINK'], $link, $source, $options);
    }

    /**
     * @see Router::register()
     * @param string|string[] $methods
     * @param string $path
     * @param string|callable|array $execute
     * @param array $options
     * @return $this
     */
    public function add($methods, string $path, $execute, array $options = []): self
    {
        return $this->register($methods, $path, $execute, $options);
    }

    /**
     * @see Router::register()
     * @param string $path
     * @param string|callable|array $execute
     * @param array $options
     * @return $this
     */
    public function get(string $path, $execute, array $options = []): self
    {
        return $this->register(['GET'], $path, $execute, $options);
    }

    /**
     * @see Router::register()
     * @param string $path
     * @param string|callable|array $execute
     * @param array $options
     * @return $this
     */
    public function post(string $path, $execute, array $options = []): self
    {
        return $this->register(['POST'], $path, $execute, $options);
    }

    /**
     * @see Router::register()
     * @param string $path
     * @param string|callable|array $execute
     * @param array $options
     * @return $this
     */
    public function put(string $path, $execute, array $options = []): self
    {
        return $this->register(['PUT'], $path, $execute, $options);
    }

    /**
     * @see Router::register()
     * @param string $path
     * @param string|callable|array $execute
     * @param array $options
     * @return $this
     */
    public function delete(string $path, $execute, array $options = []): self
    {
        return $this->register(['DELETE'], $path, $execute, $options);
    }

    /**
     * @see Router::register()
     * @param string $path
     * @param string|callable|array $execute
     * @param array $options
     * @return $this
     */
    public function options(string $path, $execute, array $options = []): self
    {
        return $this->register(['OPTIONS'], $path, $execute, $options);
    }

    /**
     * @see Router::register()
     * @param string $path
     * @param string|callable|array $execute
     * @param array $options
     * @return $this
     */
    public function patch(string $path, $execute, array $options = []): self
    {
        return $this->register(['PATCH'], $path, $execute, $options);
    }

    /**
     * @see Router::register()
     * @param string $path
     * @param string|callable|array $execute
     * @param array $options
     * @return $this
     */
    public function head(string $path, $execute, array $options = []): self
    {
        return $this->register(['HEAD'], $path, $execute, $options);
    }

    /**
     * @see Router::register()
     * @param string $path
     * @param string|callable|array $execute
     * @param array $options
     * @return $this
     */
    public function any(string $path, $execute, array $options = []): self
    {
        return $this->register(['ANY'], $path, $execute, $options);
    }

    /**
     * Defines the handler used when no route matches the request (HTTP 404).
     *
     * @param string|callable|array<int, mixed> $execute A closure, a
     * "Controller@method" string, or a [class, method] array.
     * @param array<string, mixed> $options
     * @return $this
     */
    public function setNotFoundHandler($execute, array $options = []): self
    {
        if ($this->cacheStatus) {
            return $this;
        }
        if (!\is_array($execute) && !\is_string($execute) && !\is_callable($execute)) {
            throw new InvalidArgumentException('$execute can be string, callable, or array.');
        }
        $this->error404 = [
            'execute'   => $execute,
            'options'   => \array_merge($this->error404['options'], $options),
        ];
        return $this;
    }

    /**
     * Defines the handler used when no route matches the request (HTTP 404).
     *
     * @deprecated since 2.0, use {@see Router::setNotFoundHandler()} instead.
     * @param string|callable|array<int, mixed> $execute
     * @param array<string, mixed> $options
     * @return $this
     */
    public function error_404($execute, array $options = []): self
    {
        return $this->setNotFoundHandler($execute, $options);
    }

    /**
     * It is used for routing by grouping urls with a path prefix.
     *
     * @param string $prefix
     * @param \Closure $group
     * @param array $options
     * @return void
     */
    public function group(string $prefix, \Closure $group, array $options = []): void
    {
        if ($this->cacheStatus) {
            return;
        }
        $prev_group_options = $this->groupOptions;
        $options['prefix'] = $prefix;
        $this->groupOptions = $this->optionsMerge($this->groupOptions, $options);
        \call_user_func_array($group, [$this]);
        $this->groupOptions = $prev_group_options;
    }

    /**
     * Provides domain-based route grouping.
     *
     * @param string $host
     * @param \Closure $group
     * @param array $options
     * @return void
     */
    public function domain(string $host, \Closure $group, array $options = []): void
    {
        if ($this->cacheStatus) {
            return;
        }
        $prev_group_options = $this->groupOptions;
        $options['host'] = $host;
        $this->groupOptions = $this->optionsMerge($this->groupOptions, $options);
        \call_user_func_array($group, [$this]);
        $this->groupOptions = $prev_group_options;
    }

    /**
     * It only does route grouping for a specific port.
     *
     * @param int $port
     * @param \Closure $group
     * @param array $options
     * @return void
     */
    public function port(int $port, \Closure $group, array $options = []): void
    {
        if ($this->cacheStatus) {
            return;
        }
        $prev_group_options = $this->groupOptions;
        $options['port'] = $port;
        $this->groupOptions = $this->optionsMerge($this->groupOptions, $options);
        \call_user_func_array($group, [$this]);
        $this->groupOptions = $prev_group_options;
    }

    /**
     * Defines routes that will run on requests from one or more IPs.
     *
     * @param string|string[] $ip
     * @param \Closure $group
     * @param array $options
     * @return void
     */
    public function ip($ip, \Closure $group, array $options = []): void
    {
        if ($this->cacheStatus) {
            return;
        }
        $prev_group_options = $this->groupOptions;
        $options['ip'] = $this->confirmIpAddresses($ip);
        $this->groupOptions = $this->optionsMerge($this->groupOptions, $options);
        \call_user_func_array($group, [$this]);
        $this->groupOptions = $prev_group_options;
    }

    /**
     * Defines routes for a resource controller.
     *
     * @param string $prefix
     * @param string $controller
     * @return void
     */
    public function resource(string $prefix, string $controller): void
    {
        if ($this->cacheStatus) {
            return;
        }
        $prefix = \trim($prefix, '/');
        $name_prefix = \str_replace('/', '.', $prefix);
        $controller = $this->controllerFind($controller);

        $this->register(['GET'], ('/' . $prefix), ($controller . '::index'), ['name' => ($name_prefix . '.index')])
            ->register(['GET'], ('/' . $prefix . '/create'), ($controller . '::create'), ['name' => ($name_prefix . '.create')])
            ->register(['POST'], ('/' . $prefix), ($controller . '::store'), ['name' => ($name_prefix . '.store')])
            ->register(['GET'], ('/' . $prefix . '/{' . $prefix . '}'), ($controller . '::show'), ['name' => ($name_prefix . '.show')])
            ->register(['GET'], ('/' . $prefix . '/{' . $prefix . '}/edit'), ($controller . '::edit'), ['name' => ($name_prefix . '.edit')])
            ->register(['PUT', 'PATCH'], ('/' . $prefix . '/{' . $prefix . '}'), ($controller . '::update'), ['name' => ($name_prefix . '.update')])
            ->register(['DELETE'], ('/' . $prefix . '/{'.$prefix.'}'), ($controller . '::destroy'), ['name' => ($name_prefix . '.destroy')]);
    }

    /**
     * Names the route before it.
     *
     * @param string $name
     * @return $this
     */
    public function name(string $name): self
    {
        if ($this->cacheStatus) {
            return $this;
        }
        if (!isset($this->routes[$this->id])) {
            throw new RouterException('The route to add the name could not be found.');
        }
        if (isset($this->routes[$this->id]['options']['as'])) {
            $name = $this->routes[$this->id]['options']['as'] . $name;
        }
        $name = \trim($name);
        if (isset($this->names[$name])) {
            throw new RouterException('The name "' . $name . '" is already in use by another route.');
        }
        $this->names[$name] = $this->id;
        return $this;
    }

    /**
     * Generates a URL for a named route.
     *
     * @param string $name
     * @param array $arguments
     * @return string
     */
    public function route(string $name, array $arguments = []): string
    {
        $name = \trim($name);
        if (!isset($this->names[$name])) {
            return $name;
        }
        $id = $this->names[$name];
        $path = $this->routes[$id]['path'];
        if (!empty($arguments)) {
            $replace = [];
            foreach ($arguments as $key => $value) {
                $key = \trim($key, ':{} ');
                if (\is_object($value) && \method_exists($value, '__toString')) {
                    $value = $value->__toString();
                } else {
                    $value = (string)$value;
                }
                $replace['{'.$key.'}'] = $value;
                $replace[':'.$key] = $value;
                $replace['{'.$key.'}?'] = $value;
                $replace[':'.$key.'?'] = $value;
            }
            $url = \strtr($path, $replace);
        } else {
            $url = $path;
        }
        if (\strpos($url, '?') === false) {
            return $url;
        }
        $url = \preg_replace('/({[\w\-_]+}\?|:[\w\-_]+\?)/', '?', $url);
        $url = \str_replace('?', '', $url);
        if (\rtrim($url, '/') != $url) {
            $url = \rtrim($url, '/') . '/';
        }
        return $url;
    }

    /**
     * Defines the middleware to be applied to the last added route before it.
     *
     * @param string|callable $filter <p>
     * A callable function or middleware class name that extends the \InitPHP\Router\Middleware class.
     * </p>
     * @param int $position <p>Router::BEFORE, Router::AFTER  or Router::BOTH</p>
     * @return $this
     */
    public function filter($filter, int $position = self::BOTH): self
    {
        if ($this->cacheStatus) {
            return $this;
        }
        if (!isset($this->routes[$this->id])) {
            throw new RouterException('The route to add the filter could not be found.');
        }
        if (!\is_string($filter) && !\is_callable($filter)) {
            throw new RouterException('Middleware/filter can only be string or callable.');
        }
        if (!\in_array($position, [self::BEFORE, self::BOTH, self::AFTER], true)) {
            throw new RouterException('The value given for the parameter $position is not valid.');
        }
        if (!isset($this->routes[$this->id]['options']['middleware'])) {
            $this->routes[$this->id]['options']['middleware'] = [];
        }
        switch ($position) {
            case self::BEFORE:
                if (!isset($this->routes[$this->id]['options']['middleware']['before'])) {
                    $this->routes[$this->id]['options']['middleware']['before'] = [];
                }
                $this->routes[$this->id]['options']['middleware']['before'][] = $filter;
                break;
            case self::AFTER:
                if (!isset($this->routes[$this->id]['options']['middleware']['after'])) {
                    $this->routes[$this->id]['options']['middleware']['after'] = [];
                }
                $this->routes[$this->id]['options']['middleware']['after'][] = $filter;
                break;
            default:
                $this->routes[$this->id]['options']['middleware'][] = $filter;
        }
        return $this;
    }

    /**
     * Defines the middleware to be applied to the last added route before it.
     *
     * @see Router::filter()
     * @param string|callable $middleware
     * @param int $position
     * @return $this
     */
    public function middleware($middleware, int $position = self::BOTH): self
    {
        return $this->filter($middleware, $position);
    }

    /**
     * Adds a new pattern to be used.
     *
     * @param string $key
     * @param string $pattern
     * @return $this
     */
    public function pattern(string $key, string $pattern): self
    {
        $key = \trim($key, ':{}');
        if (\substr($pattern, 0, 1) != '(' && \substr($pattern, -1) != ')') {
            $pattern = '(' . $pattern . ')';
        }
        $this->patterns[':' . $key] = $pattern;
        $this->patterns['{' . $key . '}'] = $pattern;
        return $this;
    }

    /**
     * Adds a new pattern to be used.
     *
     * @see Router::pattern()
     * @param string $key
     * @param string $pattern
     * @return $this
     */
    public function where(string $key, string $pattern): self
    {
        return $this->pattern($key, $pattern);
    }

    /**
     * It finds the route to run and runs it.
     *
     * @return ResponseInterface
     * @throws \ReflectionException
     */
    public function dispatch(): ResponseInterface
    {
        if (!isset($this->currentRoute)) {
            $this->currentRoute = $this->resolve($this->currentMethod, $this->currentUrl);
        }

        $hasRoute = isset($this->currentRoute) && !empty($this->currentRoute);

        if ($hasRoute === false) {
            return $this->notFound();
        }

        $route = $this->currentRoute;
        $arguments = $route['arguments'] ?? [];
        $this->currentArguments = $arguments;

        $filters = [
            'before'    => [],
            'after'     => [],
        ];
        if (isset($route['options']['middleware']['before'])) {
            $filters['before'] = $route['options']['middleware']['before'];
            unset($route['options']['middleware']['before']);
        }
        if (isset($route['options']['middleware']['after'])) {
            $filters['after'] = $route['options']['middleware']['after'];
            unset($route['options']['middleware']['after']);
        }
        if (isset($route['options']['middleware']) && !empty($route['options']['middleware'])) {
            $filters['before'] = \array_merge($route['options']['middleware'], $filters['before']);
            $filters['after'] = \array_merge($route['options']['middleware'], $filters['after']);
            unset($route['options']['middleware']);
        }

        $this->middlewareHandle($filters['before'], $arguments, self::BEFORE);
        $responseStatusCode = (int)$this->response->getStatusCode();
        if ($responseStatusCode < 200 || $responseStatusCode > 299) {
            return $this->response;
        }
        if ($route['methods'] === ['LINK']) {
            return $this->execute($route['execute'], $arguments, self::LINK);
        } else {
            if (\is_callable($route['execute'])) {
                $this->currentController = '__CALLABLE__';
                $this->currentControllerMethod = '';
                $this->response = $this->execute($route['execute'], $arguments);
            } else {
                $parse = $this->getControllerMethod($route['execute'], $route['options']);
                $parse['controller'] = $this->controllerFind($parse['controller']);
                $reflection = new \ReflectionClass($parse['controller']);
                $controller = $this->getClassContainer($reflection);
                $this->middlewareHandle($this->controllerMiddlewaresProperty($controller, $parse['method'], self::BEFORE), $arguments, self::BEFORE);
                $this->currentController = \get_class($controller);
                $this->currentControllerMethod = $parse['method'];
                $responseStatusCode = (int)$this->response->getStatusCode();
                if ($responseStatusCode < 200 || $responseStatusCode > 299) {
                    return $this->response;
                }
                $this->response = $this->execute([$controller, $parse['method']], $arguments);
                $after_middleware = $this->controllerMiddlewaresProperty($controller, $parse['method'], self::AFTER);
                if (!empty($after_middleware)) {
                    $filters['after'] = \array_merge($filters['after'], $after_middleware);
                }
            }
        }

        $this->middlewareHandle($filters['after'], $arguments, self::AFTER);

        return $this->response;
    }

    /**
     * @param string $method
     * @param string $current_url
     * @return array|null
     */
    public function resolve(string $method, string $current_url): ?array
    {
        $this->currentUrl = $current_url;
        $this->currentMethod = $method = \strtoupper($method);

        $routes = \array_unique(\array_merge($this->methodIds['LINK'], $this->methodIds['ANY'], $this->methodIds[$method]));
        $patterns = $this->getPatterns();
        $matches = [];
        foreach ($routes as $id) {
            $route = $this->routes[$id];
            if (!empty($route['options']['ip'])) {
                $allowedIps = \is_array($route['options']['ip']) ? $route['options']['ip'] : [$route['options']['ip']];
                if (!\in_array($this->currentIp, $allowedIps, true)) {
                    continue;
                }
            }
            $path = \preg_replace($patterns['keys'], $patterns['values'], $route['path']);
            if (\preg_match('#^' . $path . '$#', $this->currentUrl, $arguments)) {
                \array_shift($arguments);
                $route['arguments'] = $arguments;
                if ($route['methods'] === ['LINK']) {
                    if ($this->linkServePath((string)$route['execute'], $arguments) === null) {
                        // No file behind the link (missing or escaping the root): let it 404.
                        continue;
                    }
                    return $this->currentRoute = $route;
                }
                $matches_size = \strlen($route['path']);
                if (\is_array($arguments) && !empty($arguments)) {
                    $matches_size += (\count($arguments) * 25);
                }
                if (!isset($matches[$matches_size])) {
                    $matches[$matches_size] = [];
                }
                $matches[$matches_size][] = [
                    'route'     => $route,
                ];
                continue;
            }
        }
        if (!empty($matches)) {
            \krsort($matches);
            $current_match = \current($matches);
            return $this->currentRoute = $current_match[0]['route'];
        }
        return null;
    }

    private function controllerMiddlewaresProperty(object $controller, string $method, int $pos = self::BEFORE): array
    {
        if (!\property_exists($controller, 'middlewares')) {
            return [];
        }
        $reflection = new \ReflectionProperty($controller, 'middlewares');
        if (!$reflection->isPublic()) {
            return [];
        }
        unset($reflection);
        $middlewares = $controller->middlewares;
        if (!\is_array($middlewares)) {
            return [];
        }
        $res = [];
        if ($pos === self::BEFORE) {
            if (isset($middlewares['after'])) {
                unset($middlewares['after']);
            }
            if (isset($middlewares['before'])) {
                if (!\is_array($middlewares['before'])) {
                    $middlewares['before'] = [$middlewares['before']];
                }
                $res = $middlewares['before'];
            }
        } else {
            if (isset($middlewares['before'])) {
                unset($middlewares['before']);
            }
            if (isset($middlewares['after'])) {
                if (!\is_array($middlewares['after'])) {
                    $middlewares['after'] = [$middlewares['after']];
                }
                $res = $middlewares['after'];
            }
        }
        $method_len = \strlen($method);
        foreach ($middlewares as $key => $value) {
            if (!\is_string($key)) {
                $res[] = $value;
                continue;
            }
            if (!\is_array($value)) {
                continue;
            }
            if ($key !== $method) {
                continue;
            }
            if (isset($value['before'])) {
                if ($pos === self::BEFORE && \is_array($value['before'])) {
                    $res = \array_merge($res, $value['before']);
                }
                unset($value['before']);
            }
            if (isset($value['after'])) {
                if ($pos === self::AFTER && \is_array($value['after'])) {
                    $res = \array_merge($res, $value['after']);
                }
                unset($value['after']);
            }
            if (empty($value) || !\is_array($value)) {
                continue;
            }
            foreach ($value as $row) {
                $res[] = $row;
            }
        }
        return $res;
    }

    private function middlewareClassFind(string $class): string
    {
        if (!isset($this->currentRoute) || \class_exists($class)) {
            return $class;
        }
        $namespace = $this->currentRoute['options']['namespaces']['middleware'];
        $class_full_name = \rtrim($namespace, '\\') . '\\' . \ltrim($class, '\\');
        if (\class_exists($class_full_name)) {
            return $class_full_name;
        }
        if (!empty($this->currentRoute['options']['paths']['middleware'])) {
            $path = \rtrim($this->currentRoute['options']['paths']['middleware'], '/\\') . \DIRECTORY_SEPARATOR . \ltrim($class, '\\/') . '.php';
            if (\is_file($path)) {
                require $path;
                if (\class_exists($class_full_name)) {
                    return $class_full_name;
                }
            }
        }
        throw new RouterException('"'.$class.'" filter/middleware class not found.');
    }

    private function middlewareHandle(array $filters, $arguments, int $pos): void
    {
        if (empty($filters)) {
            return;
        }
        $run_filters = [];
        foreach ($filters as $filter) {
            if (is_callable($filter)) {
                $res = \call_user_func_array($filter, [$this->request, $this->response, $arguments]);
            } else {
                $filterClass = $this->middlewareClassFind($filter);
                if (\in_array($filterClass, $run_filters, true)) {
                    continue;
                }
                $run_filters[] = $filterClass;
                if (isset($this->filterObjects[$filterClass])) {
                    $filter = $this->filterObjects[$filterClass];
                } else {
                    $filter = $this->getClassContainer(new \ReflectionClass($filterClass));
                    $this->filterObjects[$filterClass] = $filter;
                }
                $method = $pos == self::BEFORE ? 'before' : 'after';
                $res = \call_user_func_array([$filter, $method], [$this->request, $this->response, $arguments]);
            }
            if ($res instanceof ResponseInterface) {
                $this->response = $res;
                $statusCode = (int)$res->getStatusCode();
                if ($statusCode < 200 || $statusCode > 299) {
                    break;
                }
                continue;
            }
            if ($res === null) {
                exit;
            }
            if (\is_object($filter)) {
                $filter = \get_class($filter) . '::' . ($pos == self::BEFORE ? 'before()' : 'after()');
            } elseif (\is_callable($filter)) {
                $filter = '__CALLABLE__';
            } else {
                $filter = (string)$filter;
            }
            throw new RouterException('The "' . $filter . '" filter should return a \\Psr\\Http\\Message\\ResponseInterface or NULL.');
        }
    }

    /**
     * Resolves the real file a LINK route should serve, or null when there is
     * nothing safe to serve.
     *
     * For directory links the captured suffix is appended to the source root
     * and the resolved real path is verified to stay inside that root, which
     * prevents path-traversal (e.g. "../secret"). For file links the source is
     * returned as-is when it still exists.
     *
     * @param string $source The link source (a directory or a file).
     * @param array<int, mixed> $arguments The matched route arguments.
     * @return string|null The absolute file path to serve, or null.
     */
    private function linkServePath(string $source, array $arguments): ?string
    {
        if (\is_dir($source)) {
            if (($root = \realpath($source)) === false) {
                return null;
            }
            $suffix = isset($arguments[0]) ? \ltrim((string)$arguments[0], '/\\') : '';
            $target = \realpath($root . \DIRECTORY_SEPARATOR . $suffix);
            if ($target === false || !\is_file($target)) {
                return null;
            }
            if ($target !== $root && \strncmp($target, $root . \DIRECTORY_SEPARATOR, \strlen($root) + 1) !== 0) {
                return null;
            }
            return $target;
        }

        return \is_file($source) ? $source : null;
    }

    private function execute($execute, array $arguments, int $type = self::ROUTE): ResponseInterface
    {
        if ($type === self::LINK) {
            $path = $this->linkServePath(\is_string($execute) ? $execute : '', $arguments);
            if ($path === null) {
                return $this->notFound();
            }
            $size = (int)\filesize($path);
            $mime = (string)\mime_content_type($path);
            if ($size < 2097152) {
                $this->response->getBody()->write((string)@\file_get_contents($path));
                return $this->response = $this->response->withHeader('Content-Type', $mime)
                    ->withHeader('Content-Length', (string)$size);
            }
            @\header('Content-Type: ' . $mime . ';');
            @\header('Content-Length: ' . $size . ';');
            \readfile($path);
            exit;
        }
        $reflection = is_array($execute) ? new \ReflectionMethod($execute[0], $execute[1]) : new \ReflectionFunction($execute);
        ob_start(function ($tmp) {
            $this->content .= $tmp;
        });
        $res = call_user_func_array($execute, $this->resolveParameters($reflection, $arguments));
        ob_end_clean();
        if ($res instanceof ResponseInterface) {
            $this->response = $res;
            $res = null;
        } elseif ($res instanceof StreamInterface) {
            $this->response = $this->response->withBody($res);
            $res = null;
        } else {
            $this->content .= (string)$res;
        }
        if (!empty($this->content) && $this->response->getBody()->isWritable()) {
            $this->response->getBody()->write($this->content);
            $this->content = '';
        }
        return $this->response;
    }

    private function resolveParameters(\Reflector $reflector, array $parameters): array
    {
        $arguments = [];
        $i = 0;
        $getParameters = $reflector->getParameters();
        foreach ($getParameters as $parameter) {
            $class = null;
            if (($type = $parameter->getType()) !== null) {
                $class = (!$type->isBuiltin()) ? new \ReflectionClass($type->getName()) : null;
            }
            if ($class === null) {
                if (isset($parameters[$i])) {
                    $arguments[] = $parameters[$i];
                    ++$i;
                    continue;
                }
                if ($parameter->isDefaultValueAvailable()) {
                    $arguments[] = $parameter->getDefaultValue();
                    ++$i;
                    continue;
                }
                if ($parameter->allowsNull()) {
                    $arguments[] = null;
                    ++$i;
                }
                continue;
            }
            if (!$this->argumentNewInstance) {
                if ($class->isInstance($this->request)) {
                    $arguments[] = $this->request;
                    continue;
                }
                if ($class->isInstance($this->response)) {
                    $arguments[] = $this->response;
                    continue;
                }
            }
            if ($class->isInstantiable()) {
                $arguments[] = $this->getClassContainer($class);
            }
        }
        return $arguments;
    }

    private function getClassContainer(\ReflectionClass $class): object
    {
        if (!$this->argumentNewInstance) {
            if ($class->isInstance($this->request)) {
                return $this->request;
            }
            if ($class->isInstance($this->response)) {
                return $this->response;
            }
        }
        if (isset($this->container) && is_object($this->container)) {
            return $this->container->get($class->getName());
        }
        if (($constructor = $class->getConstructor()) === null) {
            return $class->newInstance();
        }
        $parameters = $constructor->getParameters();
        if (empty($parameters)) {
            return $class->newInstance();
        }
        $arguments = [];
        foreach ($parameters as $parameter) {
            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }
            if ($parameter->hasType()) {
                if (($type = $parameter->getType()) !== null) {
                    if (!$type->isBuiltin()) {
                        $arguments[] = $this->getClassContainer(new \ReflectionClass($type->getName()));
                        continue;
                    }
                }
            }
            if ($parameter->allowsNull()) {
                $arguments[] = null;
                continue;
            }
            throw new RouterException('Unable to resolve parameter "'.$parameter->getName().'" of class "'.$class->getName().'".');
        }
        return $class->newInstanceArgs($arguments);
    }

    private function controllerFind($class): string
    {
        if (\class_exists($class) || \is_object($class)) {
            return $class;
        }

        if (empty($this->configs['namespaces']['controller'])) {
            throw new RouterException('"' . $class . '" controller not found.');
        }
        $controller = \rtrim($this->configs['namespaces']['controller'], '\\') . '\\' . \ltrim($class, '\\');
        if (\class_exists($controller)) {
            return $controller;
        }
        if (!empty($this->configs['paths']['controller'])) {
            $path = \rtrim($this->configs['paths']['controller'], '\\/') . \DIRECTORY_SEPARATOR . $class . '.php';
            if (\is_file($path)) {
                require $path;
                if (\class_exists($controller)) {
                    return $controller;
                }
            }
        }
        throw new RouterException('"' . $controller . '" controller not found.');
    }

    private function getControllerMethod($execute, array $route_options = []): array
    {
        if (is_array($execute)) {
            if (array_diff_key($execute, array_keys($execute))) {
                $parse = [
                    'controller'    => key($execute),
                    'method'        => current($execute),
                ];
            } else {
                $parse = [
                    'controller'        => $execute[0],
                    'method'            => $execute[1],
                ];
            }
            return $parse;
        }

        if (\is_string($execute)) {
            $split = false;
            foreach (['::', '@', '->'] as $separator) {
                if (\strpos($execute, $separator) === false) {
                    continue;
                }
                $split = \explode($separator, $execute, 2);
                break;
            }
            if ($split === false) {
                throw new RouterException('The requested controller and method are not understood.');
            }
            return [
                'controller'    => $split[0],
                'method'        => $split[1],
            ];
        }

        throw new RouterException('The requested controller and method are not understood.');
    }

    private function getPatterns(): array
    {
        $res = ['keys' => [], 'values' => []];
        foreach ($this->patterns as $key => $value) {
            $res['keys'][] = '#' . $key . '#';
            $res['values'][] = $value;
        }
        $res['keys'][] = '#{[A-Za-z0-9]+}#';
        $res['values'][] = '([\w\-_]+)';
        return $res;
    }

    private function optionsMerge(array $array, array ...$arrays): array
    {
        $data = \array_merge_recursive($array, ...$arrays);
        if (isset($data['as']) && \is_array($data['as'])) {
            $data['as'] = \implode('', $data['as']);
        }
        if (isset($data['prefix']) && \is_array($data['prefix'])) {
            $data['prefix'] = \implode('', $data['prefix']);
        }
        if (isset($data['base_path'])) {
            if (\is_array($data['base_path'])) {
                $data['base_path'] = \str_replace('//', '/', \trim(\implode('', $data['base_path']), '/'));
            }
            if ($data['base_path'] == '/') {
                unset($data['base_path']);
            }
        }
        if (isset($data['variable_method'])) {
            unset($data['variable_method']);
        }
        if (isset($data['namespaces'])) {
            if (isset($data['namespaces']['controller']) && \is_array($data['namespaces']['controller'])) {
                $data['namespaces']['controller'] = \str_replace('\\\\', '\\', \implode('\\', $data['namespaces']['controller']));
            }
            if (isset($data['namespaces']['middleware']) && \is_array($data['namespaces']['middleware'])) {
                $data['namespaces']['middleware'] = \str_replace('\\\\', '\\', \implode('\\', $data['namespaces']['middleware']));
            }
        }
        if (isset($data['paths'])) {
            if (isset($data['paths']['controller']) && \is_array($data['paths']['controller'])) {
                $data['paths']['controller'] = \str_replace('\\\\', '\\', \implode('\\', $data['paths']['controller']));
            }
            if (isset($data['paths']['middleware']) && \is_array($data['paths']['middleware'])) {
                $data['paths']['middleware'] = \str_replace('\\\\', '\\', \implode('\\', $data['paths']['middleware']));
            }
        }
        return $data;
    }

    private function registerMethodsCheck($methods): array
    {
        if (\is_array($methods)) {
            foreach ($methods as $method) {
                if (!\in_array($method, self::SUPPORTED_METHODS)) {
                    throw new InvalidArgumentException('The "' . (string)$method . '" method is not supported. Supported methods are: ' . \implode(', ', self::SUPPORTED_METHODS));
                }
            }
            return $methods;
        }
        return $this->registerMethodsCheck(\explode('|', \strtoupper($methods)));
    }

    private function methodRouteRegister(int $id, array $methods): void
    {
        foreach ($methods as $method) {
            $this->methodIds[$method][] = $id;
        }
    }

    private function currentIpBoot(): void
    {
        if (($ip = \getenv('HTTP_CLIENT_IP')) !== false) {
            $this->currentIp = $ip;
            return;
        }
        if (($ip = \getenv('HTTP_X_FORWARDED_FOR')) !== false) {
            if (\strstr($ip, ',')) {
                $parse = \explode(',', $ip, 2);
                $ip = \trim($parse[0]);
            }
            $this->currentIp = $ip;
            return;
        }
        $this->currentIp = ($_SERVER['REMOTE_ADDR'] ?? null);
    }

    private function currentMethodBoot(): void
    {
        $this->currentMethod = \strtoupper($this->request->getMethod());
        if ($this->configs['variable_method'] === false || !isset($_REQUEST['_method'])) {
            return;
        }
        $method = \strtoupper($_REQUEST['_method']);
        if (!\in_array($method, self::SUPPORTED_METHODS, true)) {
            return;
        }
        $this->currentMethod = $method;
    }

    protected function cacheStart(): void
    {
        if (!isset($this->cacheConfigs['enable']) || $this->cacheConfigs['enable'] === false) {
            return;
        }
        if (!isset($this->cacheConfigs['path']) || empty($this->cacheConfigs['path'])) {
            return;
        }
        if (!\is_file($this->cacheConfigs['path'])) {
            return;
        }
        if (($read = @\file_get_contents($this->cacheConfigs['path'])) === false) {
            throw new RouterException('Cannot read router cache file (' . $this->cacheConfigs['path'] . ')');
        }
        $ttl = (int)($this->cacheConfigs['ttl'] ?? 86400);
        $data = \unserialize($read);
        if (!isset($data['created_at'])) {
            return;
        }
        if (($data['created_at'] + $ttl) < \time()) {
            return;
        }
        if (!isset($data['data'])) {
            return;
        }
        $data = $data['data'];
        $this->routes = $data['routes'];
        $this->names = $data['names'];
        $this->methodIds = $data['methodIds'];
        $this->id = $data['id'];
        $this->error404 = $data['error_404'];
        $this->cacheStatus = true;
    }

    protected function cacheStop(): void
    {
        if (!isset($this->cacheConfigs['enable']) || $this->cacheConfigs['enable'] === false) {
            return;
        }
        if (!isset($this->cacheConfigs['path']) || empty($this->cacheConfigs['path'])) {
            return;
        }
        if ($this->cacheStatus !== false) {
            return;
        }
        $data = [
            'created_at'    => \time(),
            'data'          => [
                'id'        => $this->id,
                'routes'    => $this->routes,
                'names'     => $this->names,
                'methodIds' => $this->methodIds,
                'error_404' => $this->error404,
            ],
        ];
        // Invoked from __destruct(); a failed write must never throw here, as
        // exceptions raised during destruction are fatal. Report and move on.
        if (@\file_put_contents($this->cacheConfigs['path'], \serialize($data)) === false) {
            \trigger_error(
                'Failed to create router cache file (' . $this->cacheConfigs['path'] . ').',
                \E_USER_WARNING
            );
        }
    }

    private function confirmIpAddresses($ip): array
    {
        if (\is_string($ip)) {
            $ip = [$ip];
        }
        if (!\is_array($ip)) {
            throw new InvalidArgumentException('It can be just an IP address or a string of IP addresses.');
        }
        $res = [];
        foreach ($ip as $row) {
            if (\filter_var($row, \FILTER_VALIDATE_IP) === false) {
                throw new InvalidArgumentException('It can be just an IP address or a string of IP addresses.');
            }
            $res[] = $row;
        }
        return $res;
    }

    private function notFound(): ResponseInterface
    {
        $this->response = $this->response->withStatus(404);
        if (empty($this->error404['execute'])) {
            throw new PageNotFoundException('Error 404 : Page Not Found');
        }
        $this->currentArguments = $this->error404['options']['arguments'];
        if (\is_callable($this->error404['execute'])) {
            $this->currentController = '__CALLABLE__';
            $this->currentControllerMethod = '';
            return $this->response = $this->execute($this->error404['execute'], $this->error404['options']['arguments']);
        }
        $parse = $this->getControllerMethod($this->error404['execute'], $this->error404['options']);
        $parse['controller'] = $this->controllerFind($parse['controller']);
        $controller = $this->getClassContainer(new \ReflectionClass($parse['controller']));
        $this->currentController = \get_class($controller);
        $this->currentControllerMethod = $parse['method'];

        return $this->response = $this->execute([$controller, $parse['method']], $this->error404['options']['arguments']);
    }

}
