<?php
/**
 * ControllerHandler.php
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

use function explode;
use function implode;
use function end;
use function substr;
use function trim;
use function strtolower;
use function strtoupper;
use function in_array;
use function preg_split;
use function array_shift;

final class ControllerHandler
{

    protected string $prefix = '';
    protected string $controller = '';
    protected string $controllerClassName = '';
    protected Router $router;
    protected array $names = [];
    /** @var \ReflectionMethod[]  */
    protected array $methods;
    /** @var false|array|string[] */
    protected $parsed;

    public function __construct(string $prefix, string $controller, Router &$router)
    {
        $this->controller = $controller;
        $this->controllerClassName();
        $this->prefixHandler($prefix);
        $this->router = $router;

        $reflection = new \ReflectionClass($this->controller);
        $this->namesPropertyHandler($reflection);
        $this->controllerPublicMethodsFind($reflection);
        foreach ($this->methods as $method) {
            $this->method2RouteHandler($method);
        }

    }

    public function __destruct()
    {
        // TODO: Implement __destruct() method.
    }

    private function controllerClassName(): void
    {
        $parse = explode('\\', $this->controller);
        $name = end($parse);
        if(substr($name, -10) === 'Controller'){
            $name = substr($name, 0, -10);
        }
        $this->controllerClassName = $name;
    }

    private function prefixHandler(string $prefix): void
    {
        $prefix = trim($prefix, "\\/ \t\n\r\0\x0B");
        if(in_array(strtolower($this->controllerClassName), ['main', 'index'], true) !== FALSE){
            $prefix = empty($prefix) ? $this->controllerClassName : $prefix . '/' . $this->controllerClassName;
        }
        $this->prefix = $prefix;
    }

    private function namesPropertyHandler(\ReflectionClass $reflection): void
    {
        if($reflection->hasProperty('names')){
            $names = $reflection->getProperty('names');
            if($names->isPublic() && $names->getType()->getName() === 'array'){
                $this->names = $names->getValue();
            }
        }
    }

    private function controllerPublicMethodsFind(\ReflectionClass $reflection): void
    {
        $this->methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
    }

    private function method2RouteHandler(\ReflectionMethod $method): void
    {
        $name = $method->getName();
        if(substr($name, 0, 2) == '__'){
            return;
        }
        $this->parsed = preg_split('/(?=[A-Z])/', $name, -1, PREG_SPLIT_NO_EMPTY);

        $options = [];
        if(isset($this->names[$name])){
            $options['name'] = $this->names[$name];
        }
        $this->router->register(
            $this->toHttpMethod($name),
            $this->toMethodPath($method->getParameters()),
            [$method->class, $name],
            $options,
        );
    }

    private function toHttpMethod($name): string
    {
        if($this->parsed === FALSE){
            return 'GET';
        }
        $firstLower = strtolower($this->parsed[0]);
        if(in_array($firstLower, ['index', 'main'], true)){
            return 'GET';
        }
        if(in_array($firstLower, ['get', 'post', 'put', 'patch', 'head', 'delete', 'xget', 'xpost', 'xput', 'xpatch', 'xhead', 'xdelete', 'any'], true)){
            if(isset($this->parsed[1])){
                array_shift($this->parsed);
            }
            return strtoupper($firstLower);
        }
        return 'GET';
    }

    /**
     * @param  \ReflectionParameter[] $parameters
     * @return string
     */
    private function toMethodPath(array $parameters): string
    {
        $path = '/';
        if(!empty($this->prefix)){
            $path .= $this->prefix;
        }
        if(isset($this->parsed[0]) &&
            in_array(strtolower($this->parsed[0]), ['main', 'index']) === FALSE){
            $path .= '/' . implode('/', $this->parsed);
        }
        $intId = $floatId = $stringId = $boolId = 0;
        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            if(empty($type) || $parameter->hasType() === FALSE){
                $path .= '/:any';
                continue;
            }
            switch ($type->getName()) {
                case 'int':
                    $path .= '/:int' . ($intId != 0 ? $intId : '');
                    ++$intId;
                    break;
                case 'float':
                    $path .= '/:float' . ($floatId != 0 ? $floatId : '');
                    ++$floatId;
                    break;
                case 'string':
                    $path .= '/:string' . ($stringId != 0 ? $stringId : '');
                    ++$stringId;
                    break;
                case 'bool':
                case 'boolean':
                    $path .= '/:bool' . ($boolId != 0 ? $boolId : '');
                    ++$boolId;
                    break;
            }
        }
        return $path;
    }

}
