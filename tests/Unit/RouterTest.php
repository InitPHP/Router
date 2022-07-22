<?php
/**
 * RouterTest.php
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

use InitPHP\HTTP\Request;
use InitPHP\HTTP\Response;
use InitPHP\HTTP\Stream;
use InitPHP\Router\Exception\InvalidArgumentException;
use InitPHP\Router\Exception\PageNotFoundException;
use InitPHP\Router\Router;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class RouterTest extends \PHPUnit\Framework\TestCase
{

    /** @var RequestInterface */
    protected $request;

    /** @var ResponseInterface */
    protected $response;

    /** @var Router */
    protected $router;

    private $options = [
        'paths'         => [
            'controller'    => null,
            'middleware'    => null,
        ],
        'namespaces'    => [
            'controller'    => null,
            'middleware'    => null,
        ],
    ];

    protected function setUp(): void
    {
        $this->request = new Request('GET', 'http://www.example.com/', [], null, '1.1');
        $stream = new Stream('', null);
        $this->response = new Response(200, [], $stream, '1.1');

        $this->router = new Router($this->request, $this->response);
        parent::setUp();
    }

    public function testRegisterARoute()
    {
        $this->router->register('GET', '/', ['Home', 'index']);

        $expected = [
            'http://www.example.com/' => [
                'execute'   => ['Home', 'index'],
                'options'   => $this->options,
            ]
        ];

        $this->assertEquals($expected, $this->router->getRoutes('get'));
        $this->router->destroy();
    }

    public function testRegisterMultipleRoutesWithAString()
    {
        $this->router->add('GET|POST', '/', ['Home', 'index']);

        $expected = [
            'GET'   => [
                'http://www.example.com/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => $this->options,
                ]
            ],
            'POST'  => [
                'http://www.example.com/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => $this->options,
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->destroy();
    }

    public function testRegisterMultipleRoutesWithAArray()
    {
        $this->router->add(['POST', 'GET'], '/', ['Home', 'index']);

        $expected = [
            'POST'  => [
                'http://www.example.com/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => $this->options,
                ]
            ],
            'GET'   => [
                'http://www.example.com/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => $this->options,
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->destroy();
    }

    public function testRegisterAGetRoute()
    {
        $this->router->get('/', ['Home', 'index']);

        $expected = [
            'GET'   => [
                'http://www.example.com/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => $this->options,
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->destroy();
    }

    public function testRegisterAPostRoute()
    {
        $this->router->post('/', ['Home', 'index']);

        $expected = [
            'POST'   => [
                'http://www.example.com/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => $this->options,
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->destroy();
    }

    public function testRegisterAPutRoute()
    {
        $this->router->put('/', ['Home', 'index']);

        $expected = [
            'PUT'   => [
                'http://www.example.com/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => $this->options,
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->destroy();
    }

    public function testRegisterADeleteRoute()
    {
        $this->router->delete('/', ['Home', 'index']);

        $expected = [
            'DELETE'   => [
                'http://www.example.com/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => $this->options,
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->destroy();
    }

    public function testRegisterAOptionsRoute()
    {
        $this->router->options('/', ['Home', 'index']);

        $expected = [
            'OPTIONS'   => [
                'http://www.example.com/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => $this->options,
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->destroy();
    }

    public function testRegisterAPatchRoute()
    {
        $this->router->patch('/', ['Home', 'index']);

        $expected = [
            'PATCH'   => [
                'http://www.example.com/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => $this->options,
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->destroy();
    }

    public function testRegisterAHeadRoute()
    {
        $this->router->head('/', ['Home', 'index']);

        $expected = [
            'HEAD'   => [
                'http://www.example.com/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => $this->options,
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->destroy();
    }

    public function testRegisterAAnyRoute()
    {
        $this->router->any('/', ['Home', 'index']);

        $expected = [
            'ANY'   => [
                'http://www.example.com/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => $this->options,
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->destroy();
    }

    public function testRegisterGroupRoute()
    {
        $this->router->group('/admin', function (Router $route) {
            $route->get('/', ['Admin', 'dashboard']);
            $route->get('/login', ['Admin', 'login']);
        });

        $expected = [
            'GET'   => [
                'http://www.example.com/admin/'   => [
                    'execute'   => ['Admin', 'dashboard'],
                    'options'   => \array_merge($this->options, ['prefix' => '/admin']),
                ],
                'http://www.example.com/admin/login'   => [
                    'execute'   => ['Admin', 'login'],
                    'options'   => \array_merge($this->options, ['prefix' => '/admin']),
                ]
            ]
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->destroy();
    }

    public function testRegisterNestedGroupRoute()
    {
        $this->router->group('/admin', function (Router $route) {
            $route->get('/', ['Admin', 'dashboard']);
            $route->group('/posts', function (Router $route) {
                $route->get('/list', ['AdminPost', 'list']);
                $route->post('/add', ['AdminPost', 'add']);
                $route->delete('/delete/{id}', ['AdminPost', 'delete']);
            });
            $route->get('/login', ['Admin', 'login']);
        });

        $expected = [
            'GET'   => [
                'http://www.example.com/admin/'   => [
                    'execute'   => ['Admin', 'dashboard'],
                    'options'   => \array_merge($this->options, ['prefix' => '/admin']),
                ],
                'http://www.example.com/admin/login'   => [
                    'execute'   => ['Admin', 'login'],
                    'options'   => \array_merge($this->options, ['prefix' => '/admin']),
                ],
                'http://www.example.com/admin/posts/list' => [
                    'execute'   => ['AdminPost', 'list'],
                    'options'   => \array_merge($this->options, ['prefix' => '/admin/posts']),
                ],
            ],
            'POST'  => [
                'http://www.example.com/admin/posts/add' => [
                    'execute'   => ['AdminPost', 'add'],
                    'options'   => \array_merge($this->options, ['prefix' => '/admin/posts']),
                ]
            ],
            'DELETE'    => [
                'http://www.example.com/admin/posts/delete/{id}' => [
                    'execute'   => ['AdminPost', 'delete'],
                    'options'   => \array_merge($this->options, ['prefix' => '/admin/posts']),
                ]
            ]
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->destroy();
    }

    public function testRegisterADomainGroupRoute()
    {
        $this->router->get('/', ['Main', 'index']);

        $this->router->domain('{slug}.example.com', function (Router $route) {
            $route->get('/', ['Subdomain', 'index']);
            $route->get('/about', ['Subdomain', 'about']);
            $route->group('/admin', function (Router $route) {
                $route->get('/', ['SubdomainAdmin', 'dashboard']);
                $route->get('/login', ['SubdomainAdmin', 'login']);
            });
            $route->get('/contact', ['Subdomain', 'contact']);
        });

        $this->router->get('/about', ['Main', 'about']);

        $expected = [
            'GET'   => [
                'http://www.example.com/'   => [
                    'execute'   => ['Main', 'index'],
                    'options'   => $this->options,
                ],
                'http://{slug}.example.com/'   => [
                    'execute'   => ['Subdomain', 'index'],
                    'options'   => $this->options,
                ],
                'http://{slug}.example.com/about'   => [
                    'execute'   => ['Subdomain', 'about'],
                    'options'   => $this->options,
                ],
                'http://{slug}.example.com/admin/'   => [
                    'execute'   => ['SubdomainAdmin', 'dashboard'],
                    'options'   => \array_merge($this->options, ['prefix' => '/admin']),
                ],
                'http://{slug}.example.com/admin/login'   => [
                    'execute'   => ['SubdomainAdmin', 'login'],
                    'options'   => \array_merge($this->options, ['prefix' => '/admin']),
                ],
                'http://{slug}.example.com/contact'   => [
                    'execute'   => ['Subdomain', 'contact'],
                    'options'   => $this->options,
                ],
                'http://www.example.com/about'   => [
                    'execute'   => ['Main', 'about'],
                    'options'   => $this->options,
                ]
            ]
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->destroy();
    }


    public function testRegisterAPortGroupRoute()
    {
        $this->router->get('/', ['Main', 'index']);

        $this->router->port(9000, function (Router $route) {
            $route->get('/', ['Api', 'main']);
            $route->group('/user', function (Router $route) {
                $route->get('/', ['Api', 'user']);
                $route->post('/add', ['Api', 'add']);
            });
            $route->get('/random', ['Api', 'random']);
        });

        $this->router->get('/about', ['Main', 'about']);

        $expected = [
            'GET'   => [
                'http://www.example.com/'   => [
                    'execute'   => ['Main', 'index'],
                    'options'   => $this->options,
                ],
                'http://www.example.com:9000/'   => [
                    'execute'   => ['Api', 'main'],
                    'options'   => $this->options,
                ],
                'http://www.example.com:9000/user/'   => [
                    'execute'   => ['Api', 'user'],
                    'options'   => \array_merge($this->options, ['prefix' => '/user']),
                ],
                'http://www.example.com:9000/random'   => [
                    'execute'   => ['Api', 'random'],
                    'options'   => $this->options,
                ],
                'http://www.example.com/about'   => [
                    'execute'   => ['Main', 'about'],
                    'options'   => $this->options,
                ]
            ],
            'POST'  => [
                'http://www.example.com:9000/user/add'    => [
                    'execute'   => ['Api', 'add'],
                    'options'   => \array_merge($this->options, ['prefix' => '/user']),
                ]
            ]
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->destroy();
    }

    public function testRegisterAIPGroupRoute()
    {
        $this->router->get('/', ['Main', 'index']);

        $this->router->ip(['192.168.1.10', '192.168.1.11'], function (Router $route) {
            $route->get('/login', ['Admin', 'login']);
            $route->group('/admin', function (Router $route) {
                $route->get('/', ['Admin', 'dashboard']);
                $route->get('/profile', ['Admin', 'profile']);
            });
            $route->get('/logout', ['Admin', 'logout']);
        });

        $this->router->get('/about', ['Main', 'about']);

        $options = \array_merge($this->options, ['ip'    => ['192.168.1.10', '192.168.1.11']]);

        $expected = [
            'GET'   => [
                'http://www.example.com/'   => [
                    'execute'   => ['Main', 'index'],
                    'options'   => $this->options,
                ],
                'http://www.example.com/admin/'   => [
                    'execute'   => ['Admin', 'dashboard'],
                    'options'   => \array_merge($options, ['prefix' => '/admin']),
                ],
                'http://www.example.com/admin/profile'   => [
                    'execute'   => ['Admin', 'profile'],
                    'options'   => \array_merge($options, ['prefix' => '/admin']),
                ],
                'http://www.example.com/logout'   => [
                    'execute'   => ['Admin', 'logout'],
                    'options'   => $options,
                ],
                'http://www.example.com/login'   => [
                    'execute'   => ['Admin', 'login'],
                    'options'   => $options,
                ],
                'http://www.example.com/about'   => [
                    'execute'   => ['Main', 'about'],
                    'options'   => $this->options,
                ]
            ]
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->destroy();
    }

    public function testThrowsRegisterARouteNotSupportedMethod()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->router->register('ABC', '/', ['Home', 'index']);
    }

    public function testThrowsRegisterARouteNotSupportedExecute()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->router->register('GET', '/', true);
    }

    /**
     * @dataProvider routerDispatchArguments
     */
    public function testThrowsRouteResolveNotFound(
        string $uri,
        string $method
    )
    {
        $this->router->get('/', ['Main', 'index']);
        $this->router->post('/about', ['Main', 'about']);


        $this->router->resolve($method, $uri);
        $this->expectException(PageNotFoundException::class);
        $this->router->dispatch();

        $this->router->destroy();
    }

    public function routerDispatchArguments()
    {
        return [
            ['http://www.example.com/', 'post'],
            ['http://www.example.com/about', 'get'],
            ['http://www.example.com/contact', 'delete']
        ];
    }

    public function testRouteResolve()
    {
        $this->router->get('/', ['Home', 'index']);
        $this->router->get('/contact', ['Home', 'contact']);
        $this->router->get('/about', ['Home', 'about']);

        $expected = [
            'id'        => 3,
            'methods'   => ['GET'],
            'path'      => 'http://www.example.com/about',
            'execute'   => ['Home', 'about'],
            'options'   => $this->options,
            'arguments' => [],
        ];

        $this->assertEquals($expected, $this->router->resolve('GET', 'http://www.example.com/about'));
        $this->router->destroy();
    }

    public function testRoutePatternResolve()
    {
        $this->router->get('/', ['Home', 'index']);
        $this->router->get('/user/{string}/{id}', ['User', 'profile']);

        $expected = [
            'id'        => 2,
            'methods'   => ['GET'],
            'path'      => 'http://www.example.com/user/{string}/{id}',
            'execute'   => ['User', 'profile'],
            'options'   => $this->options,
            'arguments' => [
                'muhametsafak',
                12
            ],
        ];

        $this->assertEquals($expected, $this->router->resolve('GET', 'http://www.example.com/user/muhametsafak/12'));
        $this->router->destroy();
    }
}