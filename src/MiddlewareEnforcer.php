<?php
/**
 * MiddlewareEnforcer.php
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

use \InitPHP\Router\Exception\MiddlewareException;
use \Psr\Http\Message\{RequestInterface, ResponseInterface};

use function is_array;
use function array_merge;
use function is_string;
use function call_user_func_array;
use function class_exists;
use function rtrim;
use function ltrim;
use function is_file;

final class MiddlewareEnforcer
{

    public const BEFORE = 0;
    public const AFTER = 1;

    protected ?string $namespace = null, $path = null;

    protected array $middlewares = [
        self::BEFORE    => [],
        self::AFTER     => [],
    ];

    protected array $classes = [];
    protected array $objects = [];

    public function __construct(?string $path = null, ?string $namespace = null)
    {
        $this->path = $path;
        $this->namespace = $namespace;
    }

    public function __destruct()
    {
        unset($this->middlewares, $this->namespace, $this->path);
    }

    public function add($middleware, int $pos = self::BEFORE)
    {
        if(is_array($middleware)){
            $this->middlewares[$pos] = array_merge($this->middlewares[$pos], $middleware);
        }else{
            $this->middlewares[$pos][] = $middleware;
        }
    }

    public function process(RequestInterface $request, ResponseInterface $response, array $arguments, int $position = self::BEFORE): ResponseInterface
    {

        foreach ($this->middlewares[$position] as $middleware) {
            if(is_string($middleware)){
                $class = $this->findMiddlewareClass($middleware);
                $object = $this->middlewareObject($class);
                $method = $position === self::BEFORE ? 'before' : 'after';
                $response = call_user_func_array([$object, $method], [$request, $response, $arguments]);
                continue;
            }
            if($middleware instanceof \Closure){
                $res = call_user_func_array($middleware, [$request, $response, $arguments]);
                if(!($res instanceof ResponseInterface)){
                    throw new MiddlewareException('Middleware should return a "\\Psr\\Http\\Message\\ResponseInterface".');
                }
                continue;
            }
            throw new MiddlewareException('Middleware must be a \\Closure or \\InitPHP\\Router\\Middleware class.');
        }
        return $response;
    }


    private function findMiddlewareClass(string $class): string
    {
        if(isset($this->classes[$class])){
            return $this->classes[$class];
        }
        if(class_exists($class)){
            return $this->classes[$class] = $class;
        }
        if($this->namespace !== null){
            $tmpClass = $class;
            $class = rtrim($this->namespace, '\\') . '\\' . ltrim($class, '\\');
            if(class_exists($class)){
                return $this->classes[$tmpClass] = $class;
            }
        }
        if($this->path !== null){
            $path = rtrim($this->path, '\\/') . '/' . ($tmpClass ?? $class) . '.php';
            if(is_file($path)){
                require_once $path;
                if(class_exists($class)){
                    return $this->classes[($tmpClass ?? $class)] = $class;
                }
            }
        }
        throw new MiddlewareException('Middleware class "' . $class . '" not found.');
    }

    private function middlewareObject(string $class)
    {
        if(isset($this->objects[$class])){
            return $this->objects[$class];
        }
        $obj = new $class();
        if($obj instanceof Middleware){
            return $this->objects[$class] = $obj;
        }
        throw new MiddlewareException('Middleware class should extend "\\InitPHP\\Router\\Middleware" class.');
    }

}
