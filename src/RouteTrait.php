<?php
/**
 * RouteTrait.php
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

use \InitPHP\Router\Exception\RouterException;

use function is_string;
use function is_array;
use function explode;

trait RouteTrait
{

    /**
     * @param string|string[] $methods
     * @param string $path
     * @param string|\Closure|array $execute
     * @param array $options
     * @return $this
     * @throws Exception\UnsupportedMethod
     * @throws RouterException
     */
    public function add($methods, string $path, $execute, array $options = []): self
    {
        if(is_string($methods)){
            $methods = explode('|', $methods);
        }
        if(!is_array($methods)){
            throw new RouterException('\$methods must be an array. Or string separated by "|".');
        }
        foreach ($methods as $method) {
            $this->register($method, $path, $execute, $options);
        }
        return $this;
    }

    /**
     * @param string $path
     * @param string|\Closure|array $execute
     * @param array $options
     * @return $this
     * @throws Exception\UnsupportedMethod
     */
    public function get(string $path, $execute, array $options = []): self
    {
        return $this->register('GET', $path, $execute, $options);
    }

    /**
     * @param string $path
     * @param string|\Closure|array $execute
     * @param array $options
     * @return $this
     * @throws Exception\UnsupportedMethod
     */
    public function post(string $path, $execute, array $options = []): self
    {
        return $this->register('POST', $path, $execute, $options);
    }

    /**
     * @param string $path
     * @param string|\Closure|array $execute
     * @param array $options
     * @return $this
     * @throws Exception\UnsupportedMethod
     */
    public function put(string $path, $execute, array $options = []): self
    {
        return $this->register('PUT', $path, $execute, $options);
    }

    /**
     * @param string $path
     * @param string|\Closure|array $execute
     * @param array $options
     * @return $this
     * @throws Exception\UnsupportedMethod
     */
    public function delete(string $path, $execute, array $options = []): self
    {
        return $this->register('DELETE', $path, $execute, $options);
    }

    /**
     * @param string $path
     * @param string|\Closure|array $execute
     * @param array $options
     * @return $this
     * @throws Exception\UnsupportedMethod
     */
    public function options(string $path, $execute, array $options = []): self
    {
        return $this->register('OPTIONS', $path, $execute, $options);
    }

    /**
     * @param string $path
     * @param string|\Closure|array $execute
     * @param array $options
     * @return $this
     * @throws Exception\UnsupportedMethod
     */
    public function patch(string $path, $execute, array $options = []): self
    {
        return $this->register('PATCH', $path, $execute, $options);
    }

    /**
     * @param string $path
     * @param string|\Closure|array $execute
     * @param array $options
     * @return $this
     * @throws Exception\UnsupportedMethod
     */
    public function head(string $path, $execute, array $options = []): self
    {
        return $this->register('HEAD', $path, $execute, $options);
    }

    /**
     * @param string $path
     * @param string|\Closure|array $execute
     * @param array $options
     * @return $this
     * @throws Exception\UnsupportedMethod
     */
    public function xget(string $path, $execute, array $options = []): self
    {
        return $this->register('XGET', $path, $execute, $options);
    }

    /**
     * @param string $path
     * @param string|\Closure|array $execute
     * @param array $options
     * @return $this
     * @throws Exception\UnsupportedMethod
     */
    public function xpost(string $path, $execute, array $options = []): self
    {
        return $this->register('XPOST', $path, $execute, $options);
    }

    /**
     * @param string $path
     * @param string|\Closure|array $execute
     * @param array $options
     * @return $this
     * @throws Exception\UnsupportedMethod
     */
    public function xput(string $path, $execute, array $options = []): self
    {
        return $this->register('XPUT', $path, $execute, $options);
    }

    /**
     * @param string $path
     * @param string|\Closure|array $execute
     * @param array $options
     * @return $this
     * @throws Exception\UnsupportedMethod
     */
    public function xdelete(string $path, $execute, array $options = []): self
    {
        return $this->register('XDELETE', $path, $execute, $options);
    }

    /**
     * @param string $path
     * @param string|\Closure|array $execute
     * @param array $options
     * @return $this
     * @throws Exception\UnsupportedMethod
     */
    public function xoptions(string $path, $execute, array $options = []): self
    {
        return $this->register('XOPTIONS', $path, $execute, $options);
    }

    /**
     * @param string $path
     * @param string|\Closure|array $execute
     * @param array $options
     * @return $this
     * @throws Exception\UnsupportedMethod
     */
    public function xpatch(string $path, $execute, array $options = []): self
    {
        return $this->register('XPATCH', $path, $execute, $options);
    }

    /**
     * @param string $path
     * @param string|\Closure|array $execute
     * @param array $options
     * @return $this
     * @throws Exception\UnsupportedMethod
     */
    public function xhead(string $path, $execute, array $options = []): self
    {
        return $this->register('XHEAD', $path, $execute, $options);
    }

    /**
     * @param string $path
     * @param string|\Closure|array $execute
     * @param array $options
     * @return $this
     * @throws Exception\UnsupportedMethod
     */
    public function any(string $path, $execute, array $options = []): self
    {
        return $this->register('ANY', $path, $execute, $options);
    }

}
