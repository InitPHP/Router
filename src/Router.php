<?php
/**
 * Router.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.io/license.txt  MIT
 * @version    1.0
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Router;

use \Psr\Http\Message\{RequestInterface, ResponseInterface, StreamInterface};
use \Closure;
use \InvalidArgumentException;
use \RuntimeException;
use \InitPHP\Router\Exception\{ControllerException, RouterPageNotFoundException, UnsupportedMethod};

use const FILTER_VALIDATE_IP;

use function filter_var;
use function substr;
use function strlen;
use function str_replace;
use function strpos;
use function strtoupper;
use function strtolower;
use function in_array;
use function is_array;
use function is_string;
use function is_callable;
use function is_file;
use function property_exists;
use function method_exists;
use function class_exists;
use function trim;
use function ltrim;
use function rtrim;
use function call_user_func_array;
use function preg_replace;
use function preg_match;
use function array_shift;
use function explode;
use function implode;
use function array_reverse;
use function array_diff_key;
use function array_keys;
use function current;
use function key;
use function array_merge;
use function ob_start;
use function ob_get_contents;
use function ob_end_clean;

class Router
{
    use RouteTrait;

    public const POSITION_BOTH = 0;
    public const POSITION_BEFORE = 1;
    public const POSITION_AFTER = 2;

    protected const SUPPORTED_METHODS = [
        'GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH', 'HEAD',
        'XGET', 'XPOST', 'XPUT', 'XDELETE', 'XOPTIONS', 'XPATCH', 'XHEAD',
        'ANY'
    ];

    protected RequestInterface $request;
    protected ResponseInterface $response;

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
    ];

    protected array $baseOptions = [], $last = [], $probs = [],
            $separator = ['::', '@'], $error404;

    protected ?string $prefix = null, $domain = null;
    protected ?int $port = null;
    protected array $ip = [];

    protected array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
        'OPTIONS' => [],
        'PATCH' => [],
        'HEAD' => [],
        'XGET' => [],
        'XPOST' => [],
        'XPUT' => [],
        'XDELETE' => [],
        'XOPTIONS' => [],
        'XPATCH' => [],
        'XHEAD' => [],
        'ANY' => []
    ];

    protected array $names = [];

    protected array $configs = [
        'basePath'              => '/',
        'variableMethod'        => false,
        'middleware'            => [
            'namespace'     => null,
            'path'          => null,
        ],
        'controller'            => [
            'namespace'     => null,
            'path'          => null,
        ],
    ];

    protected bool $hasRoute = false;

    protected string $requestMethod, $requestHost, $requestPath;
    protected ?string $requestIP = null;
    protected ?int $requestPort = null;


    protected static string $CController, $CMethod;
    protected static array $CArguments = [];

    public function __construct(RequestInterface $request, ResponseInterface $response, array $configs = [])
    {
        $this->request = &$request;
        $this->response = &$response;
        $this->configs = array_merge($this->configs, $configs);
        $this->requestMethod = $this->currentMethod();
        $this->requestHost = $this->request->getUri()->getHost();
        $path = $this->request->getUri()->getPath();
        $this->requestPath = substr($path, strlen($this->configs['basePath']));
        $this->requestPort = $this->requestPort();
        $this->requestIP = $_SERVER['REMOTE_ADDR'] ?? null;
    }

    public function __destruct()
    {
        unset($this->routes, $this->names);
    }

    /**
     * @param string[]|string|Closure[]|Closure $middleware
     * @param int $position <p>[Router::POSITION_BOTH|Router::POSITION_BEFORE|Router::POSITION_AFTER]</p>
     * @return $this
     * @throws InvalidArgumentException <p>If a parameter other than expected is given.</p>
     * @throws RuntimeException <p>If it cannot find the route defined before it.</p>
     */
    public function middleware($middleware, int $position = self::POSITION_BOTH): self
    {
        if(in_array($position, [self::POSITION_BOTH, self::POSITION_BEFORE, self::POSITION_AFTER], true) === FALSE){
            throw new InvalidArgumentException("The value given for the parameter \$position is not valid.");
        }
        if(is_array($middleware)){
            foreach ($middleware as $middle) {
                $this->middleware($middle, $position);
            }
            return $this;
        }
        if(!is_string($middleware) && !($middleware instanceof Closure)){
            throw new InvalidArgumentException('The middleware type can be "string", "string[]", "\\Closure" or "\\Closure[]".');
        }
        if(!isset($this->last['method']) || !isset($this->last['path'])){
            throw new RuntimeException('The last added path could not be found.');
        }
        switch ($position) {
            case self::POSITION_BEFORE:
                $optionsId = 'before_middleware';
                break;
            case self::POSITION_AFTER:
                $optionsId = 'after_middleware';
                break;
            case self::POSITION_BOTH:
            default:
                $optionsId = 'middleware';
        }
        $method = $this->last['method'];
        $path = $this->last['path'];
        $this->routes[$method][$path]['options'][$optionsId][] = $middleware;
        return $this;
    }

    public function name(string $name): self
    {
        if(!isset($this->last['method']) || !isset($this->last['path'])){
            throw new RuntimeException('The last added path could not be found.');
        }
        $method = $this->last['method'];
        $path = $this->last['path'];
        $name = trim($name);
        $this->routes[$method][$path]['options']['name'] = $name;
        $this->names[$name] = $path;
        return $this;
    }

    public function register(string $method, string $path, $execute, array $options = []): self
    {
        if(!is_string($execute) && !is_array($execute) && !is_callable($execute)){
            throw new InvalidArgumentException("\$execute is not valid for " . $path . ".");
        }
        $method = strtoupper($method);
        if(in_array($method, self::SUPPORTED_METHODS, true) === FALSE){
            throw new UnsupportedMethod('The route method (' . $method . ') you want to add is not supported. Supported methods are: ' . implode(', ', self::SUPPORTED_METHODS));
        }
        $paths = $this->variantRoutePath($path);
        $options = array_merge($this->baseOptions, $options);
        if($this->domain !== null){
            $options['domain'] = $this->domain;
        }
        if($this->port !== null){
            $options['port'] = $this->port;
        }
        if(!empty($this->ip)){
            $options['ip'] = $this->ip;
        }
        foreach ($paths as $variant) {
            $path = $variant;
            $this->routes[$method][$path] = [
                'execute'   => $execute,
                'options'   => $options,
            ];
        }
        if(isset($options['name']) && is_string($options['name'])){
            $this->names[$options['name']] = $path;
        }
        $this->last = [
            'path'      => $path,
            'method'    => $method,
        ];
        return $this;
    }

    public function group(string $prefix, Closure $group): void
    {
        $tmpPrefix = $this->prefix ?? '';
        $this->prefix = '/' . $this->normalizePath($tmpPrefix . $prefix);
        call_user_func_array($group, [$this]);
        $this->prefix = empty($tmpPrefix) ? null : $tmpPrefix;
    }

    public function domain(string $domain, Closure $group): void
    {
        $this->domain = $domain;
        call_user_func_array($group, [$this]);
        $this->domain = null;
    }

    public function port(int $port, Closure $group): void
    {
        $this->port = $port;
        call_user_func_array($group, [$this]);
        $this->port = null;
    }

    /**
     * @param string|string[] $ip
     * @param Closure $group
     * @return void
     * @throws InvalidArgumentException
     */
    public function ip($ip, Closure $group): void
    {
        $this->ip = $this->confirmIPAddresses($ip);
        call_user_func_array($group, [$this]);
        $this->ip = [];
    }

    public function controller(string $prefix, string $controller): void
    {
        new ControllerHandler($this->normalizePath($prefix), $this->controllerFind($controller), $this);
    }

    public function error_404($execute, array $options = []): void
    {
        if(isset($options['params'])){
            $arguments = $options['params'];
            unset($options['params']);
        }elseif(isset($options['arguments'])){
            $arguments = $options['arguments'];
            unset($options['arguments']);
        }else{
            $arguments = [];
        }
        $this->error404 = [
            'execute'   => $execute,
            'options'   => array_merge($this->baseOptions, $options),
            'arguments' => $arguments,
        ];
    }

    public function where(string $key, string $pattern): self
    {
        $key = ltrim(trim($key, '{}'), ':');
        $pattern = '(' . trim($pattern, '()') . ')';
        if(isset($this->patterns[':' . $key])){
            throw new InvalidArgumentException();
        }
        $this->patterns[':'.$key] = $pattern;
        $this->patterns['{'.$key.'}'] = $pattern;
        return $this;
    }

    public function currentController(): ?string
    {
        return static::$CController ?? null;
    }

    public function currentCMethod(): ?string
    {
        return static::$CMethod ?? null;
    }

    public function currentCArguments(): array
    {
        return static::$CArguments;
    }

    protected function resolve(): array
    {
        $routes = empty($this->routes['ANY']) ? $this->routes[$this->requestMethod] : array_merge($this->routes['ANY'], $this->routes[$this->requestMethod]);

        $patterns = $this->getPatterns();

        $arguments = [];
        foreach ($routes as $path => $probs) {
            if(isset($probs['options']['port']) && $probs['options']['port'] !== $this->requestPort){
                continue;
            }
            if(isset($probs['options']['domain'])){
                $arguments = [];
                $domain = preg_replace($patterns['keys'], $patterns['values'], $probs['options']['domain']);
                if(preg_match('#^' . $domain . '$#', $this->requestHost, $params)){
                    array_shift($params);
                    $arguments = array_merge($arguments, $params);
                    unset($params);
                }else{
                    continue;
                }
            }
            if(isset($probs['options']['ip'])){
                if(in_array($this->requestIP, $probs['options']['ip']) === FALSE){
                    continue;
                }
            }
            $path = preg_replace($patterns['keys'], $patterns['values'], $path);
            if(preg_match('#^' . $path . '$#', $this->requestPath, $params)){
                array_shift($params);
                $this->hasRoute = true;
                $probs['arguments'] = array_merge($arguments, $params);
                return $probs;
            }
        }

        if($this->hasRoute === FALSE && !isset($this->error404['execute'])){
            throw new RouterPageNotFoundException('Page not found.');
        }
        return $this->error404;
    }

    protected function process(array $route): ResponseInterface
    {
        $middleware = new MiddlewareEnforcer($this->configs['middleware']['path'], $this->configs['middleware']['namespace']);
        $arguments = $route['arguments'] ?? [];

        $both = $route['options']['middleware'] ?? [];
        $before = $route['options']['before_middleware'] ?? [];
        $after = $route['options']['after_middleware'] ?? [];
        $middleware->add((empty($both) ? $before : array_merge($both, $before)), MiddlewareEnforcer::BEFORE);
        $middleware->add((empty($both) ? $after : array_merge($both, $after)), MiddlewareEnforcer::AFTER);

        static::$CArguments = $arguments;

        if(is_callable($route['execute'])){
            $this->response = $middleware->process($this->request, $this->response, $arguments, MiddlewareEnforcer::BEFORE);
            $this->response = $this->execute($route['execute'], $arguments);
            return $this->response = $middleware->process($this->request, $this->response, $arguments, MiddlewareEnforcer::AFTER);
        }

        $which = $this->whichControllerMethod($route['execute']);
        $controller = $this->controllerFind($which['controller']);
        $method = $which['method'];
        if(method_exists($controller, $method) === FALSE){
            throw new RouterPageNotFoundException('Method "' . $method . '" not found in "' . $controller . '"');
        }
        static::$CController = $controller;
        static::$CMethod = $method;

        $controller = new $controller();

        $this->controllerMiddlewarePropertyPrepare($controller, $method, $middleware, self::POSITION_BEFORE);
        $this->response = $middleware->process($this->request, $this->response, $arguments, MiddlewareEnforcer::BEFORE);
        $this->response = $this->execute([$controller, $method], $arguments);
        $this->controllerMiddlewarePropertyPrepare($controller, $method, $middleware, self::POSITION_AFTER);
        $this->response = $middleware->process($this->request, $this->response, $arguments, MiddlewareEnforcer::AFTER);
        return $this->response;
    }

    protected function process404(array $route): ResponseInterface
    {
        $this->response = $this->response->withStatus(404);
        $arguments = $route['arguments'] ?? [];
        static::$CArguments = $arguments;
        if(is_callable($route['execute'])){
            return $this->response = $this->execute($route['execute'], $arguments);
        }
        $which = $this->whichControllerMethod($route['execute']);
        $controller = $this->controllerFind($which['controller']);
        $method = $which['method'];
        if(method_exists($controller, $method) === FALSE){
            throw new RouterPageNotFoundException('Method "' . $method . '" not found in "' . $controller . '"');
        }
        static::$CController = $controller;
        static::$CMethod = $method;

        $controller = new $controller();
        return $this->response = $this->execute([$controller, $method], $arguments);
    }

    public function dispatch(): ResponseInterface
    {
        $route = $this->resolve();
        return $this->hasRoute ? $this->process($route) : $this->process404($route);
    }

    private function execute($execute, array $arguments): ResponseInterface
    {
        $reflection = is_array($execute) ? new \ReflectionMethod($execute[0], $execute[1]) : new \ReflectionFunction($execute);
        ob_start();
        $res = call_user_func_array($execute, $this->resolveParameters($reflection, $arguments));
        if(($content = ob_get_contents()) === FALSE){
            $content = '';
        }
        ob_end_clean();
        if($res instanceof ResponseInterface){
            $this->response = $res;
            $res = null;
        }elseif($res instanceof StreamInterface) {
            $this->response = $this->response->withBody($res);
            $res = null;
        }
        $content .= (string)$res;
        if(!empty($content) && $this->response->getBody()->isWritable()){
            $this->response->getBody()->write($content);
        }
        return $this->response;
    }

    private function resolveParameters(\Reflector $reflector, array $parameters): array
    {
        $arguments = [];
        $i = 0;
        foreach ($reflector->getParameters() as $key => $value) {
            $class = ($value->getType()->isBuiltin()) ? new \ReflectionClass($value->getType()->getName()) : null;
            if($class === null){
                if(!isset($parameters[$i])){
                    continue;
                }
                $arguments[] = $parameters[$i];
                ++$i;
            }
            if($class->isInstance($this->request)){
                $arguments[] = $this->request;
                continue;
            }
            if($class->isInstance($this->response)){
                $arguments[] = $this->response;
                continue;
            }
            if($class->isInstantiable()){
                $class_name = $class->getName();
                $arguments[] = new $class_name();
            }
        }
        return $arguments;
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path, "/\\ \t\n\r\0\x0B");
        $path = str_replace(['\\', '///', '//'], '/', $path);
        return trim($path, '/');
    }

    private function variantRoutePath(string $path): array
    {
        $path = ($this->prefix ?? '') . $path;
        $path = '/' . $this->normalizePath($path);
        if(strpos($path, '?') === FALSE){
            return [$path];
        }
        $variants = [];
        $parse = explode('?', $path);
        $tmp = '';
        foreach ($parse as $optional) {
            $tmp .= $optional;
            $variants[] = $tmp;
        }
        return array_reverse($variants);
    }

    private function currentMethod(): string
    {
        if($this->configs['variableMethod'] !== FALSE && isset($_REQUEST['_method'])){
            $_method = strtoupper($_REQUEST['_method']);
            if(in_array($_method, self::SUPPORTED_METHODS, true)){
                return $_method;
            }
        }
        $xWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        $isAjax = (strtolower($xWith) === 'xmlhttprequest');
        return ($isAjax ? 'X':'')
            . strtoupper($this->request->getMethod());
    }

    private function requestPort(): int
    {
        $uri = $this->request->getUri();
        $port = $uri->getPort();
        if(empty($port)){
            return $uri->getScheme() === 'https' ? 443 : 80;
        }
        return $port;
    }

    private function getPatterns(): array
    {
        $res = ['keys' => [], 'values' => []];
        foreach ($this->patterns as $key => $value) {
            $res['keys'][] = '#' . $key . '#';
            $res['values'][] = $value;
        }
        return $res;
    }

    private function controllerFind(string $class): string
    {
        if(class_exists($class)){
            return $class;
        }
        $tmpClass = $class;
        if($this->configs['controller']['namespace'] !== null){
            $namespace = rtrim($this->configs['controller']['namespace'], '\\');
            $class = $namespace . '\\' . $class;
            if(class_exists($class)){
                return $class;
            }
        }
        if($this->configs['controller']['path'] !== null){
            $path = rtrim($this->configs['controller']['path'], '\\/') . DIRECTORY_SEPARATOR . $tmpClass . '.php';
            if(is_file($path)){
                require_once $path;
            }
            if(class_exists($class)){
                return $class;
            }
        }
        throw new ControllerException('"' . $tmpClass . '" controller not found.');
    }

    private function whichControllerMethod($which): array
    {
        if(is_array($which)){
            if(array_diff_key($which, array_keys($which))){
                return [
                    'controller'    => key($which),
                    'method'        => current($which),
                ];
            }
            return [
                'controller'        => $which[0],
                'method'            => $which[1],
            ];
        }
        if(!is_string($which)){
            throw new ControllerException('The requested controller and method are not understood.');
        }
        foreach ($this->separator as $separator) {
            if(strpos($which, $separator) === FALSE){
                continue;
            }
            [$controller, $method] = explode($separator, $which, 2);
            return [
                'controller'    => $controller,
                'method'        => $method,
            ];
        }
        throw new ControllerException('The requested controller and method are not understood.');
    }

    private function controllerMiddlewarePropertyPrepare(object $controller, string $method, MiddlewareEnforcer &$enforcer, int $pos = self::POSITION_BEFORE): void
    {
        if(property_exists($controller, 'middlewares') === FALSE){
            return;
        }
        $middleware = $controller->middlewares;
        $posId = $pos === self::POSITION_AFTER ? 'after' : 'before';

        $bothMiddleware = $middleware['both'] ?? [];
        $posMiddleware = $middleware[$posId] ?? [];

        if(isset($middleware[$method])){
            $methodMiddleware = $middleware[$method];
            if(isset($methodMiddleware['both']) && is_array($methodMiddleware['both'])){
                $both = array_merge($bothMiddleware, $methodMiddleware['both']);
            }
            if(isset($methodMiddleware[$posId]) && is_array($methodMiddleware[$posId])){
                $posMiddleware = array_merge($posMiddleware, $methodMiddleware[$posId]);
            }
        }
        $enforcer->add(
            (empty($bothMiddleware) ? $posMiddleware : array_merge($bothMiddleware, $posMiddleware)),
            ($pos === self::POSITION_BEFORE ? MiddlewareEnforcer::BEFORE : MiddlewareEnforcer::AFTER)
        );
    }

    private function confirmIPAddresses($ip): array
    {
        if(is_string($ip)){
            $ip = [$ip];
        }
        if(!is_array($ip)){
            throw new InvalidArgumentException('It can be just an IP address or a string of IP addresses.');
        }
        $res = [];
        foreach ($ip as $row) {
            if(filter_var($row, FILTER_VALIDATE_IP)){
                throw new InvalidArgumentException('It can be just an IP address or a string of IP addresses.');
            }
            $res[] = $row;
        }
        return $res;
    }

}
