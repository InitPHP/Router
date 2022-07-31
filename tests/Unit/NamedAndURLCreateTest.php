<?php
/**
 * NamedAndURLCreateTest.php
 *
 * This file is part of Router.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.0
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace Tests\Router\Unit;

use \InitPHP\HTTP\{Request, Response, Stream};
use InitPHP\Router\Router;
use \Psr\Http\Message\{RequestInterface, ResponseInterface};

class NamedAndURLCreateTest extends \PHPUnit\Framework\TestCase
{
    /** @var RequestInterface */
    protected $request;

    /** @var ResponseInterface */
    protected $response;

    /** @var Router */
    protected $router;

    protected function setUp(): void
    {
        $this->request = new Request('GET', 'http://localhost/', [], null, '1.1');
        $stream = new Stream('', null);
        $this->response = new Response(200, [], $stream, '1.1');

        $this->router = new Router($this->request, $this->response);
        parent::setUp();
        parent::setUp();
    }


    public function testGeneratingUrlFromNamedRoutes()
    {
        $this->router->get('/home', ['Home', 'index'], ['name' => 'homeIndex']);

        $this->assertEquals('http://localhost/home', $this->router->route('homeIndex'));

        $this->router->destroy();
    }

    public function testGeneratingUrlFromNamedParameterRouters()
    {
        $this->router->get('/user/{id}/{slug}', ['User', 'profile'])->name('user_profile');

        $this->assertEquals('http://localhost/user/5/admin', $this->router->route('user_profile', [
            'id'    => 5,
            'slug'  => 'admin'
        ]));

        $this->router->destroy();
    }

    public function testGeneratingUrlFromNamedRouterGroup()
    {
        $this->router->group('/admin', function (Router $route) {
            $this->router->get('/login', ['Admin', 'login'])->name('login');
            $this->router->get('/', ['Admin', 'index'])->name('dashboard');
        }, ['as' => 'admin.']);

        $this->assertEquals('http://localhost/admin/login', $this->router->route('admin.login'));

        $this->router->destroy();
    }

    public function testGeneratingUrlFromNamedRouterDomainGroup()
    {
        $this->router->domain('{username}.example.com', function (Router $route) {
            $this->router->get('/', ['UserSite', 'index'])->name('index');
        }, ['as' => 'userSite.']);

        $this->assertEquals('http://admin.example.com/', $this->router->route('userSite.index', [
            'username'  => 'admin'
        ]));

        $this->router->destroy();
    }

    public function testGeneratingUrlFromNamedRouterIPGroup()
    {
        $this->router->ip(['127.0.0.1', '192.168.1.1'], function (Router $route) {
            $this->router->get('/admin', ['Admin', 'index'])->name('admin');
        }, ['as' => 'local.']);

        $this->assertEquals('http://localhost/admin', $this->router->route('local.admin'));

        $this->router->destroy();
    }

    public function testGeneratingUrlFromNamedRouterPortGroup()
    {
        $this->router->port(8080, function (Router $route) {
            $this->router->get('/admin/index', ['Admin', 'index'])->name('dashboard');
        }, ['as' => 'admin.']);

        $this->assertEquals('http://localhost:8080/admin/index', $this->router->route('admin.dashboard'));

        $this->router->destroy();
    }

    public function testOptionalUrlParam()
    {
        $this->router->get('/admin/{slug}?', 'AdminController@index')->name('admin');

        $this->assertEquals('http://localhost/admin/', $this->router->route('admin'));
        $this->assertEquals('http://localhost/admin/dashboard', $this->router->route('admin', ['slug' => 'dashboard']));
        $this->router->destroy();
    }

}
