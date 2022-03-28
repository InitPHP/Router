<?php
declare(strict_types=1);

namespace Tests\Unit;

use \InitPHP\Router\Exception\{RouteNotFound, UnsupportedMethod};
use \InitPHP\HTTP\{Request, Response, Stream};
use \InitPHP\Router\Router;
use \PHPUnit\Framework\TestCase;
use \Psr\Http\Message\{RequestInterface, ResponseInterface};

class RouterTest extends TestCase
{
    protected RequestInterface $request;
    protected ResponseInterface $response;
    protected Router $router;

    protected function setUp(): void
    {
        $this->request = new Request('GET', '/', [], null, '1.1');
        $stream = new Stream('', null);
        $this->response = new Response(200, [], $stream, '1.1');

        $this->router = new Router($this->request, $this->response);
        parent::setUp();
    }

    public function testRegisterARoute()
    {
        $this->router->register('GET', '/', ['Home', 'index']);

        $expected = [
            '/' => [
                'execute'   => ['Home', 'index'],
                'options'   => []
            ]
        ];

        $this->assertEquals($expected, $this->router->getRoutes('get'));
        $this->router->routesReset();
    }

    public function testRegisterMultipleRoutesWithAString()
    {
        $this->router->add('GET|POST', '/', ['Home', 'index']);

        $expected = [
            'GET'   => [
                '/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => []
                ]
            ],
            'POST'  => [
                '/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => []
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->routesReset();
    }

    public function testRegisterMultipleRoutesWithAArray()
    {
        $this->router->add(['POST', 'GET'], '/', ['Home', 'index']);

        $expected = [
            'POST'  => [
                '/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => [],
                ]
            ],
            'GET'   => [
                '/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => [],
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->routesReset();
    }

    public function testRegisterAGetRoute()
    {
        $this->router->get('/', ['Home', 'index']);

        $expected = [
            'GET'   => [
                '/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => [],
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->routesReset();
    }

    public function testRegisterAPostRoute()
    {
        $this->router->post('/', ['Home', 'index']);

        $expected = [
            'POST'   => [
                '/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => [],
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->routesReset();
    }

    public function testRegisterAPutRoute()
    {
        $this->router->put('/', ['Home', 'index']);

        $expected = [
            'PUT'   => [
                '/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => [],
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->routesReset();
    }

    public function testRegisterADeleteRoute()
    {
        $this->router->delete('/', ['Home', 'index']);

        $expected = [
            'DELETE'   => [
                '/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => [],
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->routesReset();
    }

    public function testRegisterAOptionsRoute()
    {
        $this->router->options('/', ['Home', 'index']);

        $expected = [
            'OPTIONS'   => [
                '/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => [],
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->routesReset();
    }

    public function testRegisterAPatchRoute()
    {
        $this->router->patch('/', ['Home', 'index']);

        $expected = [
            'PATCH'   => [
                '/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => [],
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->routesReset();
    }

    public function testRegisterAHeadRoute()
    {
        $this->router->head('/', ['Home', 'index']);

        $expected = [
            'HEAD'   => [
                '/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => [],
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->routesReset();
    }

    public function testRegisterAAjaxGetRoute()
    {
        $this->router->xget('/', ['Home', 'index']);

        $expected = [
            'XGET'   => [
                '/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => [],
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->routesReset();
    }

    public function testRegisterAAjaxPostRoute()
    {
        $this->router->xpost('/', ['Home', 'index']);

        $expected = [
            'XPOST'   => [
                '/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => [],
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->routesReset();
    }

    public function testRegisterAAjaxPutRoute()
    {
        $this->router->xput('/', ['Home', 'index']);

        $expected = [
            'XPUT'   => [
                '/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => [],
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->routesReset();
    }

    public function testRegisterAAjaxDeleteRoute()
    {
        $this->router->xdelete('/', ['Home', 'index']);

        $expected = [
            'XDELETE'   => [
                '/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => [],
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->routesReset();
    }

    public function testRegisterAAjaxOptionsRoute()
    {
        $this->router->xoptions('/', ['Home', 'index']);

        $expected = [
            'XOPTIONS'   => [
                '/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => [],
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->routesReset();
    }

    public function testRegisterAAjaxPatchRoute()
    {
        $this->router->xpatch('/', ['Home', 'index']);

        $expected = [
            'XPATCH'   => [
                '/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => [],
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->routesReset();
    }

    public function testRegisterAAjaxHeadRoute()
    {
        $this->router->xhead('/', ['Home', 'index']);

        $expected = [
            'XHEAD'   => [
                '/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => [],
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->routesReset();
    }

    public function testRegisterAAnyRoute()
    {
        $this->router->any('/', ['Home', 'index']);

        $expected = [
            'ANY'   => [
                '/' => [
                    'execute'   => ['Home', 'index'],
                    'options'   => [],
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->routesReset();
    }

    public function testRegisterGroupRoute()
    {
        $this->router->group('/admin', function (Router $route) {
            $route->get('/', ['Admin', 'dashboard']);
            $route->get('/login', ['Admin', 'login']);
        });

        $expected = [
            'GET'   => [
                '/admin/'   => [
                    'execute'   => ['Admin', 'dashboard'],
                    'options'   => [],
                ],
                '/admin/login'   => [
                    'execute'   => ['Admin', 'login'],
                    'options'   => [],
                ]
            ]
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->routesReset();
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
                '/admin/'   => [
                    'execute'   => ['Admin', 'dashboard'],
                    'options'   => [],
                ],
                '/admin/login'   => [
                    'execute'   => ['Admin', 'login'],
                    'options'   => [],
                ],
                '/admin/posts/list' => [
                    'execute'   => ['AdminPost', 'list'],
                    'options'   => [],
                ],
            ],
            'POST'  => [
                '/admin/posts/add' => [
                    'execute'   => ['AdminPost', 'add'],
                    'options'   => [],
                ]
            ],
            'DELETE'    => [
                '/admin/posts/delete/{id}' => [
                    'execute'   => ['AdminPost', 'delete'],
                    'options'   => [],
                ]
            ]
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->routesReset();
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
                '/'   => [
                    'execute'   => ['Main', 'index'],
                    'options'   => [],
                ],
                '{slug}.example.com/'   => [
                    'execute'   => ['Subdomain', 'index'],
                    'options'   => [
                        'domain'    => '{slug}.example.com'
                    ],
                ],
                '{slug}.example.com/about'   => [
                    'execute'   => ['Subdomain', 'about'],
                    'options'   => [
                        'domain'    => '{slug}.example.com'
                    ],
                ],
                '{slug}.example.com/admin/'   => [
                    'execute'   => ['SubdomainAdmin', 'dashboard'],
                    'options'   => [
                        'domain'    => '{slug}.example.com'
                    ],
                ],
                '{slug}.example.com/admin/login'   => [
                    'execute'   => ['SubdomainAdmin', 'login'],
                    'options'   => [
                        'domain'    => '{slug}.example.com'
                    ],
                ],
                '{slug}.example.com/contact'   => [
                    'execute'   => ['Subdomain', 'contact'],
                    'options'   => [
                        'domain'    => '{slug}.example.com'
                    ],
                ],
                '/about'   => [
                    'execute'   => ['Main', 'about'],
                    'options'   => [],
                ]
            ]
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->routesReset();
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
                '/'   => [
                    'execute'   => ['Main', 'index'],
                    'options'   => [],
                ],
                ':9000/'   => [
                    'execute'   => ['Api', 'main'],
                    'options'   => [
                        'port'  => 9000,
                    ],
                ],
                ':9000/user/'   => [
                    'execute'   => ['Api', 'user'],
                    'options'   => [
                        'port'  => 9000,
                    ],
                ],
                ':9000/random'   => [
                    'execute'   => ['Api', 'random'],
                    'options'   => [
                        'port'  => 9000,
                    ],
                ],
                '/about'   => [
                    'execute'   => ['Main', 'about'],
                    'options'   => [],
                ]
            ],
            'POST'  => [
                ':9000/user/add'    => [
                    'execute'   => ['Api', 'add'],
                    'options'   => [
                        'port'  => 9000
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->routesReset();
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

        $expected = [
            'GET'   => [
                '/'   => [
                    'execute'   => ['Main', 'index'],
                    'options'   => [],
                ],
                '[192.168.1.10,192.168.1.11]/admin/'   => [
                    'execute'   => ['Admin', 'dashboard'],
                    'options'   => [
                        'ip'    => ['192.168.1.10', '192.168.1.11']
                    ],
                ],
                '[192.168.1.10,192.168.1.11]/admin/profile'   => [
                    'execute'   => ['Admin', 'profile'],
                    'options'   => [
                        'ip'    => ['192.168.1.10', '192.168.1.11']
                    ],
                ],
                '[192.168.1.10,192.168.1.11]/logout'   => [
                    'execute'   => ['Admin', 'logout'],
                    'options'   => [
                        'ip'    => ['192.168.1.10', '192.168.1.11']
                    ],
                ],
                '[192.168.1.10,192.168.1.11]/login'   => [
                    'execute'   => ['Admin', 'login'],
                    'options'   => [
                        'ip'    => ['192.168.1.10', '192.168.1.11']
                    ],
                ],
                '/about'   => [
                    'execute'   => ['Main', 'about'],
                    'options'   => [],
                ]
            ]
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));

        $this->router->routesReset();
    }

    public function testThrowsRegisterARouteNotSupportedMethod()
    {
        $this->expectException(UnsupportedMethod::class);
        $this->router->register('ABC', '/', ['Home', 'index']);
    }

    public function testThrowsRegisterARouteNotSupportedExecute()
    {
        $this->expectException(\InvalidArgumentException::class);
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

        $this->expectException(RouteNotFound::class);
        $this->router->resolve($method, $uri);

        $this->router->routesReset();
    }

    public function routerDispatchArguments()
    {
        return [
            ['/', 'post'],
            ['/about', 'get'],
            ['/contact', 'delete']
        ];
    }

    public function testRouteResolve()
    {
        $this->router->get('/', ['Home', 'index']);
        $this->router->get('/about', ['Home', 'about']);
        $this->router->get('/contact', ['Home', 'contact']);

        $expected = [
            'execute'   => ['Home', 'about'],
            'options'   => [],
            'arguments' => [],
        ];

        $this->assertEquals($expected, $this->router->resolve('get', '/about'));
        $this->router->routesReset();
    }

    public function testRoutePatternResolve()
    {
        $this->router->get('/', ['Home', 'index']);
        $this->router->get('/user/{string}/{id}', ['User', 'profile']);

        $expected = [
            'execute'   => ['User', 'profile'],
            'options'   => [],
            'arguments' => [
                'muhametsafak',
                12
            ],
        ];

        $this->assertEquals($expected, $this->router->resolve('get', '/user/muhametsafak/12'));
        $this->router->routesReset();
    }

}
