<?php
/**
 * Router.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.io/license.txt  MIT
 * @version    1.0.2
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Router;

use \Psr\Http\Message\{RequestInterface, ResponseInterface, StreamInterface};
use \Closure;
use \InvalidArgumentException;
use \RuntimeException;
use InitPHP\Router\Exception\{ClassContainerException, ControllerException, RouteNotFound, UnsupportedMethod};

use const FILTER_VALIDATE_IP;
use const DIRECTORY_SEPARATOR;

use function filter_var;
use function substr;
use function strlen;
use function strtr;
use function strpos;
use function strtoupper;
use function strtolower;
use function in_array;
use function is_array;
use function is_string;
use function is_callable;
use function is_file;
use function file_put_contents;
use function file_get_contents;
use function serialize;
use function unserialize;
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
use function array_pop;
use function end;
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

    protected array $baseOptions = [], $last = [], $groupOptions = [],
            $groupOptionsOrigins = [], $separator = ['::', '@'], $error404;

    protected ?string $prefix = null, $domain = null;
    protected ?int $port = null;
    protected array $ip = [];

    protected array $routes = [];

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
        'cachePath'             => __DIR__ . DIRECTORY_SEPARATOR . 'Cache' . DIRECTORY_SEPARATOR . 'route_cache',
        'container'         => null,
    ];

    protected bool $hasRoute = false;

    protected string $requestMethod, $requestHost, $requestPath, $requestScheme;
    protected ?string $requestIP = null;
    protected ?int $requestPort = null;

    protected bool $cache = false;

    protected static string $CController, $CMethod;
    protected static array $CArguments = [];

    public function __construct(RequestInterface &$request, ResponseInterface &$response, array $configs = [])
    {
        $this->request = &$request;
        $this->response = &$response;
        $this->configs = array_merge($this->configs, $configs);
        $this->requestMethod = $this->currentMethod();
        $this->requestHost = $this->request->getUri()->getHost();
        $path = $this->request->getUri()->getPath();
        $this->requestPath = empty($this->configs['basePath']) ? $path : substr($path, strlen($this->configs['basePath']));
        $this->requestPort = $this->requestPort();
        $this->requestScheme = $this->request->getUri()->getScheme();
        $this->requestIP = $_SERVER['REMOTE_ADDR'] ?? null;
        if(is_file($this->configs['cachePath'])){
            $this->cacheImport();
        }
    }

    public function __destruct()
    {
        $this->routesReset();
        unset($this->names);
    }

    public function getRoutes(?string $method = null): array
    {
        if($method === null){
            return $this->routes;
        }
        $method = strtoupper($method);
        return $this->routes[$method] ?? [];
    }

    public function routesReset(): void
    {
        $this->routes = [];
    }

    /**
     * Adds one or more Middleware to be applied to the last route before it.
     *
     * @param string[]|string|Closure[]|Closure $middleware <p>Middleware to be applied.</p>
     * @param int $position <p>Application position/method. [Router::POSITION_BOTH|Router::POSITION_BEFORE|Router::POSITION_AFTER]</p>
     * @return $this
     * @throws InvalidArgumentException <p>If a parameter other than expected is given.</p>
     * @throws RuntimeException <p>If it cannot find the route defined before it.</p>
     */
    public function middleware($middleware, int $position = self::POSITION_BOTH): self
    {
        if($this->cache){
            return $this;
        }
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

    /**
     * Names the last route added before it.
     *
     * @param string $name <p>The name of the route.</p>
     * @return $this
     */
    public function name(string $name): self
    {
        if($this->cache){
            return $this;
        }
        if(!isset($this->last['method']) || !isset($this->last['path'])){
            throw new RuntimeException('The last added path could not be found.');
        }
        $method = $this->last['method'];
        $path = $this->last['path'];
        $name = ($this->groupOptions['as'] ?? '') . trim($name);
        $this->routes[$method][$path]['options']['name'] = $name;
        $this->names[$name] = $this->last['routePath'];
        return $this;
    }

    /**
     * Generates the url for a named route.
     *
     * @param string $name
     * @param array $arguments
     * @return string
     */
    public function route(string $name, array $arguments = []): string
    {
        $path = $this->names[$name] ?? $name;
        if(empty($arguments)){
            return $path;
        }
        $replace = [];
        foreach ($arguments as $key => $value) {
            $key = trim($key, ':{}');
            $replace[':' . $key] = $value;
            $replace['{' . $key . '}'] = $value;
        }
        return strtr($path, $replace);
    }

    /**
     * Saves a route.
     *
     * @used-by Router::add()
     * @used-by Router::get()
     * @used-by Router::post()
     * @used-by Router::put()
     * @used-by Router::delete()
     * @used-by Router::options()
     * @used-by Router::patch()
     * @used-by Router::head()
     * @used-by Router::xget()
     * @used-by Router::xpost()
     * @used-by Router::xput()
     * @used-by Router::xdelete()
     * @used-by Router::xoptions()
     * @used-by Router::xpatch()
     * @used-by Router::xhead()
     * @param string $method <p>A supported request method.</p>
     * @param string $path <p>The path to run the route.</p>
     * @param string|array|Closure $execute <p>The function or method to be executed when the route is run.</p>
     * @param array $options <p>Associative array that defines route-specific options.</p>
     * @return $this
     * @throws UnsupportedMethod <p>If the request method declared in the $method parameter is not supported.</p>
     * @throws InvalidArgumentException <p>If $execute parameters are an unsupported type.</p>
     */
    public function register(string $method, string $path, $execute, array $options = []): self
    {
        if($this->cache){
            return $this;
        }
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
        if(!empty($this->groupOptions)){
            $groupOptions = $this->groupOptions;
            if(isset($groupOptions['as'])){
                unset($groupOptions['as']);
            }
            foreach ($groupOptions as $key => $value) {
                $options[$key] = $value . ($options[$key] ?? '');
            }
        }
        $base_path = ($options['domain'] ?? '')
                . (isset($options['port']) ? ':' . $options['port'] : '');
        $variant_base_path = $base_path . (isset($options['ip']) ? '[' . implode(',', $options['ip']) . ']' : '');

        if(!empty($this->configs['basePath']) && $this->configs['basePath'] !== '/'){
            $base_path .= $this->configs['basePath'];
        }
        $lastVariant = '';
        foreach ($paths as $variant) {
            $path = $variant_base_path . $variant;
            $this->routes[$method][$path] = [
                'execute'   => $execute,
                'options'   => $options,
            ];
            $lastVariant = $variant;
        }

        $routePath = $this->requestScheme . '://'
                    . ($options['domain'] ?? $this->requestHost)
                    . (isset($options['port']) ? ':' . $options['port'] : (($this->requestPort != 80 && $this->requestPort != 443) ? ':'. $this->requestPort : ''))
                    . ($this->configs['basePath'] !== '/' ? $this->configs['basePath'] : '')
                    . $lastVariant;
        if(isset($options['name']) && is_string($options['name'])){
            $name = ($this->groupOptions['as'] ?? '') . $options['name'];
            $this->names[$name] = $routePath;
            unset($options['name']);
        }

        $this->last = [
            'path'      => $path,
            'method'    => $method,
            'routePath' => $routePath,
        ];
        return $this;
    }


    /**
     * Defines a route for one or more methods.
     *
     * @param string|string[] $methods <p>String or array describing which request methods the route will run. If you are declaring a string for more than one method; Separate methods with a straight line (|).</p>
     * @param string $path <p>The path to run the route.</p>
     * @param string|\Closure|array $execute <p>The function or method to be executed when the route is run.</p>
     * @param array $options <p>Associative array that defines route-specific options.</p>
     * @return $this
     * @throws Exception\UnsupportedMethod <p>If it tries to define a route for an unsupported request method.</p>
     * @throws InvalidArgumentException <p>If $methods or $execute parameters are an unsupported type.</p>
     */
    public function add($methods, string $path, $execute, array $options = []): self
    {
        if($this->cache){
            return $this;
        }
        if(is_string($methods)){
            $methods = explode('|', $methods);
        }
        if(!is_array($methods)){
            throw new InvalidArgumentException('\$methods must be an array. Or string separated by "|".');
        }
        foreach ($methods as $method) {
            $this->register($method, $path, $execute, $options);
        }
        return $this;
    }

    /**
     * Defines a route for the GET request method.
     *
     * @param string $path <p>The path to run the route.</p>
     * @param string|\Closure|array $execute <p>The function or method to be executed when the route is run.</p>
     * @param array $options <p>Associative array that defines route-specific options.</p>
     * @return $this
     * @throws InvalidArgumentException <p>If $execute parameters are an unsupported type.</p>
     */
    public function get(string $path, $execute, array $options = []): self
    {
        return $this->register('GET', $path, $execute, $options);
    }

    /**
     * Defines a route for the POST request method.
     *
     * @param string $path <p>The path to run the route.</p>
     * @param string|\Closure|array $execute <p>The function or method to be executed when the route is run.</p>
     * @param array $options <p>Associative array that defines route-specific options.</p>
     * @return $this
     * @throws InvalidArgumentException <p>If $execute parameters are an unsupported type.</p>
     */
    public function post(string $path, $execute, array $options = []): self
    {
        return $this->register('POST', $path, $execute, $options);
    }

    /**
     * Defines a route for the PUT request method.
     *
     * @param string $path <p>The path to run the route.</p>
     * @param string|\Closure|array $execute <p>The function or method to be executed when the route is run.</p>
     * @param array $options <p>Associative array that defines route-specific options.</p>
     * @return $this
     * @throws InvalidArgumentException <p>If $execute parameters are an unsupported type.</p>
     */
    public function put(string $path, $execute, array $options = []): self
    {
        return $this->register('PUT', $path, $execute, $options);
    }

    /**
     * Defines a route for the DELETE request method.
     *
     * @param string $path <p>The path to run the route.</p>
     * @param string|\Closure|array $execute <p>The function or method to be executed when the route is run.</p>
     * @param array $options <p>Associative array that defines route-specific options.</p>
     * @return $this
     * @throws InvalidArgumentException <p>If $execute parameters are an unsupported type.</p>
     */
    public function delete(string $path, $execute, array $options = []): self
    {
        return $this->register('DELETE', $path, $execute, $options);
    }

    /**
     * Defines a route for the OPTIONS request method.
     *
     * @param string $path <p>The path to run the route.</p>
     * @param string|\Closure|array $execute <p>The function or method to be executed when the route is run.</p>
     * @param array $options <p>Associative array that defines route-specific options.</p>
     * @return $this
     * @throws InvalidArgumentException <p>If $execute parameters are an unsupported type.</p>
     */
    public function options(string $path, $execute, array $options = []): self
    {
        return $this->register('OPTIONS', $path, $execute, $options);
    }

    /**
     * Defines a route for the PATCH request method.
     *
     * @param string $path <p>The path to run the route.</p>
     * @param string|\Closure|array $execute <p>The function or method to be executed when the route is run.</p>
     * @param array $options <p>Associative array that defines route-specific options.</p>
     * @return $this
     * @throws InvalidArgumentException <p>If $execute parameters are an unsupported type.</p>
     */
    public function patch(string $path, $execute, array $options = []): self
    {
        return $this->register('PATCH', $path, $execute, $options);
    }

    /**
     * Defines a route for the HEAD request method.
     *
     * @param string $path <p>The path to run the route.</p>
     * @param string|\Closure|array $execute <p>The function or method to be executed when the route is run.</p>
     * @param array $options <p>Associative array that defines route-specific options.</p>
     * @return $this
     * @throws InvalidArgumentException <p>If $execute parameters are an unsupported type.</p>
     */
    public function head(string $path, $execute, array $options = []): self
    {
        return $this->register('HEAD', $path, $execute, $options);
    }

    /**
     * Defines a route for the Ajax GET request method.
     *
     * @param string $path <p>The path to run the route.</p>
     * @param string|\Closure|array $execute <p>The function or method to be executed when the route is run.</p>
     * @param array $options <p>Associative array that defines route-specific options.</p>
     * @return $this
     * @throws InvalidArgumentException <p>If $execute parameters are an unsupported type.</p>
     */
    public function xget(string $path, $execute, array $options = []): self
    {
        return $this->register('XGET', $path, $execute, $options);
    }

    /**
     * Defines a route for the Ajax POST request method.
     *
     * @param string $path <p>The path to run the route.</p>
     * @param string|\Closure|array $execute <p>The function or method to be executed when the route is run.</p>
     * @param array $options <p>Associative array that defines route-specific options.</p>
     * @return $this
     * @throws InvalidArgumentException <p>If $execute parameters are an unsupported type.</p>
     */
    public function xpost(string $path, $execute, array $options = []): self
    {
        return $this->register('XPOST', $path, $execute, $options);
    }

    /**
     * Defines a route for the Ajax PUT request method.
     *
     * @param string $path <p>The path to run the route.</p>
     * @param string|\Closure|array $execute <p>The function or method to be executed when the route is run.</p>
     * @param array $options <p>Associative array that defines route-specific options.</p>
     * @return $this
     * @throws InvalidArgumentException <p>If $execute parameters are an unsupported type.</p>
     */
    public function xput(string $path, $execute, array $options = []): self
    {
        return $this->register('XPUT', $path, $execute, $options);
    }

    /**
     * Defines a route for the Ajax DELETE request method.
     *
     * @param string $path <p>The path to run the route.</p>
     * @param string|\Closure|array $execute <p>The function or method to be executed when the route is run.</p>
     * @param array $options <p>Associative array that defines route-specific options.</p>
     * @return $this
     * @throws InvalidArgumentException <p>If $execute parameters are an unsupported type.</p>
     */
    public function xdelete(string $path, $execute, array $options = []): self
    {
        return $this->register('XDELETE', $path, $execute, $options);
    }

    /**
     * Defines a route for the Ajax OPTIONS request method.
     *
     * @param string $path <p>The path to run the route.</p>
     * @param string|\Closure|array $execute <p>The function or method to be executed when the route is run.</p>
     * @param array $options <p>Associative array that defines route-specific options.</p>
     * @return $this
     * @throws InvalidArgumentException <p>If $execute parameters are an unsupported type.</p>
     */
    public function xoptions(string $path, $execute, array $options = []): self
    {
        return $this->register('XOPTIONS', $path, $execute, $options);
    }

    /**
     * Defines a route for the Ajax PATCH request method.
     *
     * @param string $path <p>The path to run the route.</p>
     * @param string|\Closure|array $execute <p>The function or method to be executed when the route is run.</p>
     * @param array $options <p>Associative array that defines route-specific options.</p>
     * @return $this
     * @throws InvalidArgumentException <p>If $execute parameters are an unsupported type.</p>
     */
    public function xpatch(string $path, $execute, array $options = []): self
    {
        return $this->register('XPATCH', $path, $execute, $options);
    }

    /**
     * Defines a route for the Ajax HEAD request method.
     *
     * @param string $path <p>The path to run the route.</p>
     * @param string|\Closure|array $execute <p>The function or method to be executed when the route is run.</p>
     * @param array $options <p>Associative array that defines route-specific options.</p>
     * @return $this
     * @throws InvalidArgumentException <p>If $execute parameters are an unsupported type.</p>
     */
    public function xhead(string $path, $execute, array $options = []): self
    {
        return $this->register('XHEAD', $path, $execute, $options);
    }

    /**
     * It defines a route that can run on every request method.
     *
     * @param string $path <p>The path to run the route.</p>
     * @param string|\Closure|array $execute <p>The function or method to be executed when the route is run.</p>
     * @param array $options <p>Associative array that defines route-specific options.</p>
     * @return $this
     * @throws InvalidArgumentException <p>If $execute parameters are an unsupported type.</p>
     */
    public function any(string $path, $execute, array $options = []): self
    {
        return $this->register('ANY', $path, $execute, $options);
    }

    /**
     * Groups routes with a prefix.
     *
     * @param string $prefix <p>The prefix to be added to the route paths.</p>
     * @param Closure $group <p>The function to use for grouping.</p>
     * @param array $options <p>Associative array declaring options to apply to routes under Group.</p>
     * @return void
     */
    public function group(string $prefix, Closure $group, array $options = []): void
    {
        if($this->cache){
            return;
        }
        $this->groupOptionsSetter($options);
        $tmpPrefix = $this->prefix ?? '';
        $this->prefix = $tmpPrefix . $prefix;
        call_user_func_array($group, [$this]);
        $this->prefix = empty($tmpPrefix) ? null : $tmpPrefix;
        $this->groupOptionsReverseSync();
    }

    /**
     * It groups routes under a domain.
     *
     * @param string $domain <p>The domain where the routes within the group will run.</p>
     * @param Closure $group <p>The function to use for grouping.</p>
     * @param array $options <p>Associative array declaring options to apply to routes under Group.</p>
     * @return void
     */
    public function domain(string $domain, Closure $group, array $options = []): void
    {
        if($this->cache){
            return;
        }
        $this->groupOptionsSetter($options);
        $this->domain = $domain;
        call_user_func_array($group, [$this]);
        $this->domain = null;
        $this->groupOptionsReverseSync();
    }

    /**
     * It groups routes to work only on requests coming through a port.
     *
     * @param int $port <p>Required request port to run routes.</p>
     * @param Closure $group <p>The function to use for grouping.</p>
     * @param array $options <p>Associative array declaring options to apply to routes under Group.</p>
     * @return void
     */
    public function port(int $port, Closure $group, array $options = []): void
    {
        if($this->cache){
            return;
        }
        $this->groupOptionsSetter($options);
        $this->port = $port;
        call_user_func_array($group, [$this]);
        $this->port = null;
        $this->groupOptionsReverseSync();
    }

    /**
     * Groups routes to work only on requests from specific IP.
     *
     * @param string|string[] $ip <p>The IP address string or array of IP addresses from which the routes will run.</p>
     * @param Closure $group <p>The function to use for grouping.</p>
     * @param array $options <p>Associative array declaring options to apply to routes under Group.</p>
     * @return void
     */
    public function ip($ip, Closure $group, array $options = []): void
    {
        if($this->cache){
            return;
        }
        $this->groupOptionsSetter($options);
        $this->ip = $this->confirmIPAddresses($ip);
        call_user_func_array($group, [$this]);
        $this->ip = [];
        $this->groupOptionsReverseSync();
    }

    /**
     * Creates routes for methods by analyzing a controller class.
     *
     * @link https://github.com/initphp/router/Wiki/05.Automatic-Routes
     * @param string $controller <p>The Controller class to be analyzed.</p>
     * @param string $prefix <p>The prefix to use in routes. It may be empty string.</p>
     * @return void
     * @throws ControllerException
     */
    public function controller(string $controller, string $prefix = ''): void
    {
        if($this->cache){
            return;
        }
        $tmpPrefix = $this->prefix ?? '';
        new ControllerHandler(($tmpPrefix . $prefix), $this->controllerFind($controller), $this);
        $this->prefix = empty($tmpPrefix) ? null : $tmpPrefix;
    }

    /**
     * Defines the route to run as a 404 error page if no suitable route is found.
     *
     * @link https://github.com/initphp/router/Wiki/06.404-Error
     * @param string|\Closure|array $execute <p>The function or controller method to execute.</p>
     * @param array $options <p>Associative array declaring options.</p>
     * @return void
     */
    public function error_404($execute, array $options = []): void
    {
        if($this->cache){
            return;
        }
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

    /**
     * Defines a custom pattern that can be used on routes.
     *
     * @link https://github.com/initphp/router/Wiki/07.Patterns#add-pattern
     * @param string $key <p></p>
     * @param string $pattern <p></p>
     * @return $this
     * @throws InvalidArgumentException <p>If $key is already defined or predefined.</p>
     */
    public function where(string $key, string $pattern): self
    {
        $key = ltrim(trim($key, '{}'), ':');
        $pattern = '(' . trim($pattern, '()') . ')';
        if(isset($this->patterns[':' . $key])){
            throw new InvalidArgumentException('');
        }
        $this->patterns[':'.$key] = $pattern;
        $this->patterns['{'.$key.'}'] = $pattern;
        return $this;
    }

    /**
     * Returns the full name of the running controller class.
     *
     * @return string|null
     */
    public function currentController(): ?string
    {
        return static::$CController ?? null;
    }

    /**
     * Returns the name of the Controller method that is run.
     *
     * @return string|null
     */
    public function currentCMethod(): ?string
    {
        return static::$CMethod ?? null;
    }

    /**
     * Returns the arguments as an array, if any.
     *
     * @return array
     */
    public function currentCArguments(): array
    {
        return static::$CArguments ?? [];
    }

    /**
     * Loads routes from cache file.
     *
     * @return void
     */
    protected function cacheImport(): void
    {
        if(($read = @file_get_contents($this->configs['cachePath'])) === FALSE){
            return;
        }
        $cache = unserialize($read);
        if(!isset($cache['routes']) || !isset($cache['names'])){
            return;
        }
        $this->routes = $cache['routes'];
        $this->names = $cache['names'];
        $this->cache = true;
    }

    /**
     * It deletes the cache file if any.
     *
     * @return void
     */
    public function cacheDelete(): void
    {
        if(is_file($this->configs['cachePath'])){
            @unlink($this->configs['cachePath']);
        }
    }

    /**
     * Extracts routes to a cache file.
     *
     * @return void
     */
    public function cacheExport(): void
    {
        @file_put_contents($this->configs['cachePath'], serialize(['routes' => $this->routes, 'names' => $this->names]));
    }

    /**
     * It finds the appropriate route, runs it, and returns a response object.
     *
     * @return ResponseInterface
     */
    public function dispatch(): ResponseInterface
    {
        $route = $this->resolve($this->requestMethod, $this->requestPath, [
            'domain'    => $this->requestHost,
            'port'      => $this->requestPort,
            'ip'        => $this->requestIP,
        ]);
        return $this->hasRoute ? $this->process($route) : $this->process404($route);
    }

    public function resolve(string $method, string $path, array $options = []): array
    {
        $method = strtoupper($method);
        $routes = $this->routes[$method] ?? [];
        if(!empty($this->routes['ANY'])){
            $routes = array_merge($this->routes['ANY'], $routes);
        }

        $patterns = $this->getPatterns();

        foreach ($routes as $route => $value) {
            $arguments = [];
            if(isset($value['options']['domain']) && isset($options['domain'])){
                $domain = preg_replace($patterns['keys'], $patterns['values'], $value['options']['domain']);
                if(preg_match('#^' . $domain . '$#', $options['domain'], $params)){
                    array_shift($params);
                    $arguments = array_merge($arguments, $params);
                    unset($params);
                }else{
                    continue;
                }
                $route = substr($route, strlen($value['options']['domain']));
            }
            if(isset($value['options']['port']) && isset($options['port'])){
                if($value['options']['port'] !== $options['port']){
                    continue;
                }
                $route = substr($route, (strlen((string)$value['options']['port']) + 1));
            }
            if(isset($value['options']['ip']) && isset($options['ip'])){
                if(in_array($options['ip'], $value['options']['ip']) === FALSE){
                    continue;
                }
                $route = substr($route, (strlen(implode(',', $value['options']['ip'])) + 2));
            }
            $route = preg_replace($patterns['keys'], $patterns['values'], $route);
            if(preg_match('#^' . $route . '$#', $path, $params)){
                array_shift($params);
                $this->hasRoute = true;
                $value['arguments'] = array_merge($arguments, $params);
                return $value;
            }
        }

        if($this->hasRoute === FALSE && !isset($this->error404['execute'])){
            throw new RouteNotFound('Page not found.');
        }
        return $this->error404;
    }

    protected function process(array $route): ResponseInterface
    {
        if(isset($route['options']['namespace'])){
            $namespace = $route['options']['namespace'];
            $this->configs['controller']['namespace'] .= $namespace;
            $this->configs['controller']['path'] =
                isset($this->configs['controller']['path']) ? rtrim($this->configs['controller']['path'], '\\/') : ''
                    . DIRECTORY_SEPARATOR . $namespace;
        }

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
        $this->controllerMethodPrepare($route['execute']);

        $controller = $this->getClassContainer(new \ReflectionClass(static::$CController));

        $this->controllerMiddlewarePropertyPrepare($controller, static::$CMethod, $middleware, self::POSITION_BEFORE);
        $this->response = $middleware->process($this->request, $this->response, $arguments, MiddlewareEnforcer::BEFORE);
        $this->response = $this->execute([$controller, static::$CMethod], $arguments);
        $this->controllerMiddlewarePropertyPrepare($controller, static::$CMethod, $middleware, self::POSITION_AFTER);
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
        $this->controllerMethodPrepare($route['execute']);
        $controller = new static::$CController();
        return $this->response = $this->execute([$controller, static::$CMethod], $arguments);
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
        $getParameters = $reflector->getParameters();
        foreach ($getParameters as $parameter) {
            $class = null;
            if(($type = $parameter->getType()) !== null){
                $class = (!$type->isBuiltin()) ? new \ReflectionClass($type->getName()) : null;
            }
            if($class === null){
                if(isset($parameters[$i])){
                    $arguments[] = $parameters[$i];
                    ++$i;
                    continue;
                }
                if($parameter->isDefaultValueAvailable()){
                    $arguments[] = $parameter->getDefaultValue();
                    ++$i;
                    continue;
                }
                if($parameter->allowsNull()){
                    $arguments[] = null;
                    ++$i;
                }
                continue;
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
                $arguments[] = $this->getClassContainer($class);
            }
        }
        return $arguments;
    }

    private function getClassContainer(\ReflectionClass $class): object
    {
        if($class->isInstance($this->request)){
            return $this->request;
        }
        if($class->isInstance($this->response)){
            return $this->response;
        }
        if(is_object($this->configs['container'])){
            return $this->configs['container']->get($class->getName());
        }
        if(($constructor = $class->getConstructor()) === null){
            return $class->newInstance();
        }
        $parameters = $constructor->getParameters();
        if(empty($parameters)){
            return $class->newInstance();
        }
        $arguments = [];
        foreach ($parameters as $parameter) {
            if($parameter->isDefaultValueAvailable()){
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }
            if($parameter->hasType()){
                if(($type = $parameter->getType()) !== null){
                    if(!$type->isBuiltin()){
                        $arguments[] = $this->getClassContainer(new \ReflectionClass($type->getName()));
                        continue;
                    }
                }
            }
            if($parameter->allowsNull()){
                $arguments[] = null;
                continue;
            }
            throw new ClassContainerException('Unable to resolve parameter "'.$parameter->getName().'" of class "'.$class->getName().'".');
        }
        return $class->newInstanceArgs($arguments);
    }

    private function variantRoutePath(string $path): array
    {
        $path = ($this->prefix ?? '') . $path;
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
        $res['keys'][] = '#{[A-Za-z0-9]+}#';
        $res['values'][] = '([\w\-_]+)';
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

    private function controllerMethodPrepare($execute)
    {
        $parse = $this->whichControllerMethod($execute);
        $controller = $this->controllerFind($parse['controller']);
        $method = $parse['method'];
        if(method_exists($controller, $method) === FALSE){
            throw new RouteNotFound('Method "' . $method . '" not found in "' . $controller . '"');
        }
        static::$CController = $controller;
        static::$CMethod = $method;
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
            if(filter_var($row, FILTER_VALIDATE_IP) === FALSE){
                throw new InvalidArgumentException('It can be just an IP address or a string of IP addresses.');
            }
            $res[] = $row;
        }
        return $res;
    }

    private function groupOptionsSetter(array $options = []): void
    {
        $this->groupOptionsSync();
        foreach ($options as $key => $value) {
            $this->groupOptions[$key] = ($this->groupOptions[$key] ?? '') . $value;
        }
    }

    private function groupOptionsSync(): void
    {
        $this->groupOptionsOrigins[] = $this->groupOptions;
    }

    private function groupOptionsReverseSync(): void
    {
        if(empty($this->groupOptionsOrigins)){
            $this->groupOptions = [];
            return;
        }
        $this->groupOptions = end($this->groupOptionsOrigins);
        array_pop($this->groupOptionsOrigins);
    }

}
