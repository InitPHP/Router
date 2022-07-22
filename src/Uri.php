<?php
/**
 * Uri.php
 *
 * This file is part of Router.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.1
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Router;

use InitPHP\Router\Exception\InvalidArgumentException;

/**
 * This class was created to quickly and accurately define the URLs of the routes.
 */
final class Uri
{
    protected $scheme = '';

    protected $userInfo = '';

    protected $host = '';

    /** @var null|int */
    protected $port = null;

    protected $path = '';

    public function __construct(string $url)
    {
        $this->boot($url);
    }

    public function __toString()
    {
        return ($this->scheme !== '' ? $this->scheme . ':' : '')
            . '//'
            . ($this->userInfo !== '' ? $this->userInfo . '@' : '')
            . ($this->host !== '' ? $this->host : '')
            . ($this->port !== null ? ':' . $this->port : '')
            . '/'
            . (!empty($this->path) ? \ltrim($this->path, '/') : '');
    }

    public function withScheme(string $scheme): self
    {
        if(!\in_array($scheme, ['http', 'https'], true)){
            return $this;
        }
        $this->scheme = $scheme;
        return $this;
    }

    public function withHost($host): self
    {
        $host = \strtolower($host);
        if($host === $this->host){
            return $this;
        }
        $this->host = $host;
        return $this;
    }

    public function withPort(int $port): self
    {
        $this->port = $port;
        return $this;
    }

    public function withPath($path): self
    {
        $this->path = $path;
        return $this;
    }

    public function boot(string $url)
    {
        if($url === ''){
            return;
        }
        if(($parse = \parse_url($url)) === FALSE){
            throw new InvalidArgumentException(\sprintf('Unable to parse URI: "%s"', $url));
        }
        $this->scheme = isset($parse['scheme']) ? \strtolower($parse['scheme']) : 'http';
        $this->userInfo = $parse['user'] ?? '';
        $this->host = isset($parse['host']) ? \strtolower($parse['host']) : '';

        if(isset($parse['port']) && ($parse['port'] != 80 || $parse['port'] != 443)){
            $this->port = $parse['port'];
        }
        $this->path = $parse['path'] ?? '/';
    }

}
