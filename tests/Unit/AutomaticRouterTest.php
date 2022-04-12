<?php
declare(strict_types=1);

namespace Tests\Router\Unit;

use \InitPHP\HTTP\{Request, Response, Stream};
use \InitPHP\Router\Router;
use \PHPUnit\Framework\TestCase;
use \Psr\Http\Message\{RequestInterface, ResponseInterface};

class AutomaticRouterTest extends TestCase
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

    public function testControllerAutomaticRoutesRegister()
    {
        $this->router->controller(ExampleController::class, '/api');

        $expected = [
            'GET'       => [
                '/api'    => [
                    'execute'   => [
                        'Tests\\Router\\Unit\\ExampleController',
                        'index'
                    ],
                    'options'   => []
                ],
                '/api/create'    => [
                    'execute'   => [
                        'Tests\\Router\\Unit\\ExampleController',
                        'getCreate'
                    ],
                    'options'   => []
                ],
                '/api/show/:string'    => [
                    'execute'   => [
                        'Tests\\Router\\Unit\\ExampleController',
                        'getShow'
                    ],
                    'options'   => []
                ],
                '/api/edit/:int'    => [
                    'execute'   => [
                        'Tests\\Router\\Unit\\ExampleController',
                        'getEdit'
                    ],
                    'options'   => []
                ],
            ],
            'POST'      => [
                '/api'    => [
                    'execute'   => [
                        'Tests\\Router\\Unit\\ExampleController',
                        'postIndex'
                    ],
                    'options'   => []
                ]
            ],
            'PUT'       => [
                '/api/update/:int' => [
                    'execute'   => [
                        'Tests\\Router\\Unit\\ExampleController',
                        'putUpdate'
                    ],
                    'options'   => []
                ]
            ],
            'PATCH'     => [
                '/api/update/:int' => [
                    'execute'   => [
                        'Tests\\Router\\Unit\\ExampleController',
                        'patchUpdate'
                    ],
                    'options'   => []
                ]
            ],
            'DELETE'    => [
                '/api/delete/:int' => [
                    'execute'   => [
                        'Tests\\Router\\Unit\\ExampleController',
                        'deleteDelete'
                    ],
                    'options'   => []
                ]
            ]
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));
    }

}
