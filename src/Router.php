<?php

/**
 * Router.php
 *
 * This file is part of Router.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Router;

use InitPHP\Router\Cache\RouteCache;
use InitPHP\Router\Dispatch\Dispatcher;
use InitPHP\Router\Dispatch\MiddlewareRunner;
use InitPHP\Router\Exception\{InvalidArgumentException, PageNotFoundException, RouterException};
use InitPHP\Router\Http\RequestContext;
use InitPHP\Router\Link\FileLinkHandler;
use InitPHP\Router\Matching\PatternRegistry;
use InitPHP\Router\Matching\RouteMatcher;
use InitPHP\Router\Resolver\ControllerResolver;
use InitPHP\Router\Resolver\ParameterResolver;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\{RequestInterface, ResponseInterface};

/**
 * HTTP router: registers routes, resolves the one matching the current request
 * and dispatches it through the middleware/handler pipeline to a PSR-7 response.
 *
 * The work is delegated to focused collaborators (route collection, matcher,
 * dispatcher, middleware runner, controller/parameter resolvers, link handler
 * and cache); this class is the registration façade and public API.
 */
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

    protected PatternRegistry $patternRegistry;

    protected ControllerResolver $controllerResolver;

    protected ParameterResolver $parameterResolver;

    protected FileLinkHandler $fileLinkHandler;

    protected MiddlewareRunner $middlewareRunner;

    protected Dispatcher $dispatcher;

    protected RouteCache $cache;

    protected RequestInterface $request;

    protected ResponseInterface $response;

    protected Uri $uri;

    protected ?ContainerInterface $container = null;

    protected RouteCollection $collection;

    protected RouteMatcher $matcher;

    /** @var array<string, mixed> */
    protected array $error404 = [
        'execute'   => null,
        'options'   => [
            'arguments' => [],
        ],
    ];

    /** @var array<string, mixed> */
    protected array $groupOptions = [];

    private ?Route $currentRoute = null;

    private string $currentUrl = '';

    private ?string $currentIp = null;

    private string $currentMethod = '';

    /**
     * @param array<string, mixed> $configs Router configuration: paths,
     * namespaces, base_path, variable_method, argument_new_instance, plus the
     * optional "container" (PSR-11) and "cache" sub-arrays.
     */
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
        $this->patternRegistry = new PatternRegistry();
        $this->fileLinkHandler = new FileLinkHandler();
        $this->collection = new RouteCollection(self::SUPPORTED_METHODS);
        $this->matcher = new RouteMatcher($this->collection, $this->patternRegistry, $this->fileLinkHandler);
        $this->controllerResolver = new ControllerResolver(
            \is_string($this->configs['namespaces']['controller'] ?? null) ? $this->configs['namespaces']['controller'] : null,
            \is_string($this->configs['paths']['controller'] ?? null) ? $this->configs['paths']['controller'] : null
        );
        $this->parameterResolver = new ParameterResolver(
            $this->request,
            $this->argumentNewInstance,
            $this->container
        );
        $this->middlewareRunner = new MiddlewareRunner($this->request, $this->parameterResolver);
        $this->dispatcher = new Dispatcher(
            $this->controllerResolver,
            $this->parameterResolver,
            $this->middlewareRunner,
            $this->fileLinkHandler
        );
        $this->uri = new Uri($this->request->getUri()->__toString());

        $this->currentUrl = $this->uri->__toString();
        $context = new RequestContext(
            $this->request,
            (bool) $this->configs['variable_method'],
            self::SUPPORTED_METHODS
        );
        $this->currentMethod = $context->getMethod();
        $this->currentIp = $context->getClientIp();

        $this->cache = new RouteCache($this->cacheConfigs);
        $this->cacheStart();
    }

    public function __destruct()
    {
        $this->cacheStop();
        $this->destroy();
    }

    /**
     * Returns the registered routes as plain arrays.
     *
     * With no method, routes are grouped by HTTP method; with a method, only
     * that method's routes (keyed by path) are returned. Each entry is
     * ['execute' => handler, 'options' => array].
     *
     * @return array<string, mixed>
     */
    public function getRoutes(?string $method = null): array
    {
        $res = [];
        if ($method === null) {
            foreach ($this->collection->all() as $route) {
                foreach ($route->getMethods() as $routeMethod) {
                    $res[$routeMethod][$route->getPath()] = [
                        'execute'   => $route->getHandler(),
                        'options'   => $route->getOptions(),
                    ];
                }
            }

            return $res;
        }
        foreach ($this->collection->ofMethod(\strtoupper($method)) as $route) {
            $res[$route->getPath()] = [
                'execute'   => $route->getHandler(),
                'options'   => $route->getOptions(),
            ];
        }

        return $res;
    }

    /**
     * Clears all registered routes and resets the router to its initial state.
     */
    public function destroy(): void
    {
        $this->collection->clear();
        $this->dispatcher->reset();
        $this->error404 = [
            'execute'   => null,
            'options'   => [
                'arguments' => [],
            ],
        ];
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
        return $this->dispatcher->getCurrentController();
    }

    /**
     * Returns the controller method that handled the current route.
     *
     * @return string|null The method name, an empty string when a closure
     * handled the route, or null before dispatch().
     */
    public function getCurrentControllerMethod(): ?string
    {
        return $this->dispatcher->getCurrentControllerMethod();
    }

    /**
     * Returns the arguments extracted from the current route.
     *
     * @return array<int, mixed>
     */
    public function getCurrentArguments(): array
    {
        return $this->dispatcher->getCurrentArguments();
    }

    /**
     * Registers a route for one or more HTTP methods.
     *
     * @param string|list<string> $methods An array, or a "|"-separated string, of supported methods.
     * @param string $path The route path (group prefix and base path are prepended).
     * @param string|callable|array<int|string, mixed> $execute A closure, a
     * "Controller@method" string, or a [class, method] handler.
     * @param array<string, mixed> $options
     * @return $this
     * @throws InvalidArgumentException When $execute or $methods has an unsupported type, or a method is not supported.
     * @throws RouterException When the route name is already taken.
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

        $id = $this->collection->nextId();

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
            if ($this->collection->hasName($name)) {
                throw new RouterException('The name "' . $name . '" is already in use by another route.');
            }
            $this->collection->setName($name, $id);
        }
        $this->collection->add(new Route($id, $methods, $uri->__toString(), $execute, $options));

        return $this;
    }

    /**
     * Registers a virtual link that serves a file or a directory's files.
     *
     * For a directory, requests under the link path are mapped to files inside
     * it (with path-traversal protection at serve time).
     *
     * @param string $link The URL path to expose.
     * @param string $source An existing file or directory to serve.
     * @param array<string, mixed> $options
     * @return $this
     * @throws InvalidArgumentException When $source does not exist.
     */
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
     * @param string|callable|array<int|string, mixed> $execute
     * @param array<string, mixed> $options
     * @return $this
     */
    public function add($methods, string $path, $execute, array $options = []): self
    {
        return $this->register($methods, $path, $execute, $options);
    }

    /**
     * @see Router::register()
     * @param string $path
     * @param string|callable|array<int|string, mixed> $execute
     * @param array<string, mixed> $options
     * @return $this
     */
    public function get(string $path, $execute, array $options = []): self
    {
        return $this->register(['GET'], $path, $execute, $options);
    }

    /**
     * @see Router::register()
     * @param string $path
     * @param string|callable|array<int|string, mixed> $execute
     * @param array<string, mixed> $options
     * @return $this
     */
    public function post(string $path, $execute, array $options = []): self
    {
        return $this->register(['POST'], $path, $execute, $options);
    }

    /**
     * @see Router::register()
     * @param string $path
     * @param string|callable|array<int|string, mixed> $execute
     * @param array<string, mixed> $options
     * @return $this
     */
    public function put(string $path, $execute, array $options = []): self
    {
        return $this->register(['PUT'], $path, $execute, $options);
    }

    /**
     * @see Router::register()
     * @param string $path
     * @param string|callable|array<int|string, mixed> $execute
     * @param array<string, mixed> $options
     * @return $this
     */
    public function delete(string $path, $execute, array $options = []): self
    {
        return $this->register(['DELETE'], $path, $execute, $options);
    }

    /**
     * @see Router::register()
     * @param string $path
     * @param string|callable|array<int|string, mixed> $execute
     * @param array<string, mixed> $options
     * @return $this
     */
    public function options(string $path, $execute, array $options = []): self
    {
        return $this->register(['OPTIONS'], $path, $execute, $options);
    }

    /**
     * @see Router::register()
     * @param string $path
     * @param string|callable|array<int|string, mixed> $execute
     * @param array<string, mixed> $options
     * @return $this
     */
    public function patch(string $path, $execute, array $options = []): self
    {
        return $this->register(['PATCH'], $path, $execute, $options);
    }

    /**
     * @see Router::register()
     * @param string $path
     * @param string|callable|array<int|string, mixed> $execute
     * @param array<string, mixed> $options
     * @return $this
     */
    public function head(string $path, $execute, array $options = []): self
    {
        return $this->register(['HEAD'], $path, $execute, $options);
    }

    /**
     * @see Router::register()
     * @param string $path
     * @param string|callable|array<int|string, mixed> $execute
     * @param array<string, mixed> $options
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
     * Groups the routes registered inside the callback under a shared path prefix.
     *
     * @param string $prefix
     * @param \Closure(Router): void $group Receives this router to register the grouped routes.
     * @param array<string, mixed> $options
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
     * Groups the routes registered inside the callback under a host (domain).
     *
     * @param string $host
     * @param \Closure(Router): void $group
     * @param array<string, mixed> $options
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
     * Groups routes that only respond on a specific port.
     *
     * @param int $port
     * @param \Closure(Router): void $group
     * @param array<string, mixed> $options
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
     * Groups routes that only respond to requests from the given client IP(s).
     *
     * @param string|list<string> $ip A single IP address or a list of them.
     * @param \Closure(Router): void $group
     * @param array<string, mixed> $options
     * @throws InvalidArgumentException When an IP address is not valid.
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
     * Registers the seven RESTful routes for a resource controller
     * (index, create, store, show, edit, update, destroy), each with a
     * generated route name based on the prefix.
     *
     * @param string $prefix The resource path/name prefix (e.g. "photos").
     * @param string $controller The controller class (resolved via the configured namespace).
     * @throws RouterException When the controller cannot be located.
     */
    public function resource(string $prefix, string $controller): void
    {
        if ($this->cacheStatus) {
            return;
        }
        $prefix = \trim($prefix, '/');
        $name_prefix = \str_replace('/', '.', $prefix);
        $controller = (string) $this->controllerResolver->find($controller);

        $this->register(['GET'], ('/' . $prefix), ($controller . '::index'), ['name' => ($name_prefix . '.index')])
            ->register(['GET'], ('/' . $prefix . '/create'), ($controller . '::create'), ['name' => ($name_prefix . '.create')])
            ->register(['POST'], ('/' . $prefix), ($controller . '::store'), ['name' => ($name_prefix . '.store')])
            ->register(['GET'], ('/' . $prefix . '/{' . $prefix . '}'), ($controller . '::show'), ['name' => ($name_prefix . '.show')])
            ->register(['GET'], ('/' . $prefix . '/{' . $prefix . '}/edit'), ($controller . '::edit'), ['name' => ($name_prefix . '.edit')])
            ->register(['PUT', 'PATCH'], ('/' . $prefix . '/{' . $prefix . '}'), ($controller . '::update'), ['name' => ($name_prefix . '.update')])
            ->register(['DELETE'], ('/' . $prefix . '/{'.$prefix.'}'), ($controller . '::destroy'), ['name' => ($name_prefix . '.destroy')]);
    }

    /**
     * Assigns a name to the most recently registered route.
     *
     * @param string $name
     * @return $this
     * @throws RouterException When there is no route to name, or the name is already in use.
     */
    public function name(string $name): self
    {
        if ($this->cacheStatus) {
            return $this;
        }
        $route = $this->collection->last();
        if ($route === null) {
            throw new RouterException('The route to add the name could not be found.');
        }
        $as = $route->getOption('as');
        if (\is_string($as)) {
            $name = $as . $name;
        }
        $name = \trim($name);
        if ($this->collection->hasName($name)) {
            throw new RouterException('The name "' . $name . '" is already in use by another route.');
        }
        $this->collection->setName($name, $route->getId());
        return $this;
    }

    /**
     * Builds a URL for a named route, substituting the given arguments and
     * stripping any unused optional segments. Returns the name unchanged when
     * no route has that name.
     *
     * @param string $name
     * @param array<string, scalar|\Stringable|null> $arguments Values keyed by parameter name.
     * @return string
     */
    public function route(string $name, array $arguments = []): string
    {
        $name = \trim($name);
        $route = $this->collection->byName($name);
        if ($route === null) {
            return $name;
        }
        $path = $route->getPath();
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
     * Attaches a middleware (filter) to the most recently registered route.
     *
     * @param string|callable $filter A callable, or the name of a middleware
     * class extending {@see Middleware}.
     * @param int $position One of Router::BEFORE, Router::AFTER or Router::BOTH.
     * @return $this
     * @throws RouterException When there is no route to attach to, the filter
     * type is invalid, or $position is not a valid constant.
     */
    public function filter($filter, int $position = self::BOTH): self
    {
        if ($this->cacheStatus) {
            return $this;
        }
        $route = $this->collection->last();
        if ($route === null) {
            throw new RouterException('The route to add the filter could not be found.');
        }
        if (!\is_string($filter) && !\is_callable($filter)) {
            throw new RouterException('Middleware/filter can only be string or callable.');
        }
        if (!\in_array($position, [self::BEFORE, self::BOTH, self::AFTER], true)) {
            throw new RouterException('The value given for the parameter $position is not valid.');
        }
        $bucket = match ($position) {
            self::BEFORE => 'before',
            self::AFTER  => 'after',
            default      => 'both',
        };
        $route->addMiddleware($bucket, $filter);
        return $this;
    }

    /**
     * Alias of {@see Router::filter()}.
     *
     * @param string|callable $middleware
     * @param int $position One of Router::BEFORE, Router::AFTER or Router::BOTH.
     * @return $this
     */
    public function middleware($middleware, int $position = self::BOTH): self
    {
        return $this->filter($middleware, $position);
    }

    /**
     * Registers a custom named parameter pattern (e.g. "year" => "[0-9]{4}").
     *
     * @param string $key The pattern name, with or without ":"/"{}" decoration.
     * @param string $pattern A regular expression; wrapped in a group if needed.
     * @return $this
     */
    public function pattern(string $key, string $pattern): self
    {
        $this->patternRegistry->add($key, $pattern);
        return $this;
    }

    /**
     * Alias of {@see Router::pattern()}.
     *
     * @param string $key
     * @param string $pattern
     * @return $this
     */
    public function where(string $key, string $pattern): self
    {
        return $this->pattern($key, $pattern);
    }

    /**
     * Resolves the current request (if not already resolved) and runs it
     * through the full pipeline, returning the resulting response.
     *
     * @return ResponseInterface
     * @throws PageNotFoundException When no route matches and no 404 handler is set.
     * @throws RouterException When a controller/middleware cannot be resolved.
     * @throws \ReflectionException When a handler cannot be reflected.
     */
    public function dispatch(): ResponseInterface
    {
        if (!isset($this->currentRoute)) {
            $this->currentRoute = $this->resolve($this->currentMethod, $this->currentUrl);
        }

        return $this->response = $this->dispatcher->dispatch($this->currentRoute, $this->response, $this->error404);
    }

    /**
     * Resolves the route matching the given method and URL.
     *
     * @return Route|null The matched route (carrying its captured arguments), or null.
     */
    public function resolve(string $method, string $current_url): ?Route
    {
        $this->currentUrl = $current_url;
        $this->currentMethod = \strtoupper($method);

        return $this->currentRoute = $this->matcher->match($this->currentMethod, $current_url, $this->currentIp);
    }

    /**
     * Merges the global config, the active group options and the per-route
     * options into one options array.
     *
     * "prefix" and "as" accumulate across levels (so nested groups build up a
     * combined path/name prefix); nested arrays (namespaces, paths) are merged
     * leaf-wise; every other key is overridden by the later source. The
     * transient "variable_method" key and a redundant "/" base path are dropped.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> ...$overrides
     * @return array<string, mixed>
     */
    private function optionsMerge(array $base, array ...$overrides): array
    {
        $data = $base;
        foreach ($overrides as $options) {
            foreach ($options as $key => $value) {
                if (($key === 'prefix' || $key === 'as')
                    && \is_string($value)
                    && \is_string($data[$key] ?? null)) {
                    $data[$key] .= $value;
                    continue;
                }
                if (\is_array($value) && \is_array($data[$key] ?? null)) {
                    $data[$key] = \array_replace($data[$key], $value);
                    continue;
                }
                $data[$key] = $value;
            }
        }
        unset($data['variable_method']);
        if (($data['base_path'] ?? null) === '/') {
            unset($data['base_path']);
        }

        return $data;
    }

    /**
     * @param string|list<string> $methods
     * @return list<string>
     * @throws InvalidArgumentException When a method is not supported.
     */
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

    protected function cacheStart(): void
    {
        if (($data = $this->cache->load()) === null) {
            return;
        }
        $this->collection->fromCache($data);
        if (\is_array($data['error_404'] ?? null)) {
            $this->error404 = $data['error_404'];
        }
        $this->cacheStatus = true;
    }

    protected function cacheStop(): void
    {
        if ($this->cacheStatus !== false) {
            return;
        }
        $this->cache->save($this->collection->toCache() + ['error_404' => $this->error404]);
    }

    /**
     * @param string|list<string> $ip
     * @return list<string>
     * @throws InvalidArgumentException When an IP address is not valid.
     */
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

}
