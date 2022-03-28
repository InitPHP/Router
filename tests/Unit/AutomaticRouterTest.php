<?php
declare(strict_types=1);

namespace Tests\Unit;

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
                        'Tests\\Unit\\ExampleController',
                        'index'
                    ],
                    'options'   => []
                ],
                '/api/create'    => [
                    'execute'   => [
                        'Tests\\Unit\\ExampleController',
                        'getCreate'
                    ],
                    'options'   => []
                ],
                '/api/show/:string'    => [
                    'execute'   => [
                        'Tests\\Unit\\ExampleController',
                        'getShow'
                    ],
                    'options'   => []
                ],
                '/api/edit/:int'    => [
                    'execute'   => [
                        'Tests\\Unit\\ExampleController',
                        'getEdit'
                    ],
                    'options'   => []
                ],
            ],
            'POST'      => [
                '/api'    => [
                    'execute'   => [
                        'Tests\\Unit\\ExampleController',
                        'postIndex'
                    ],
                    'options'   => []
                ]
            ],
            'PUT'       => [
                '/api/update/:int' => [
                    'execute'   => [
                        'Tests\\Unit\\ExampleController',
                        'putUpdate'
                    ],
                    'options'   => []
                ]
            ],
            'PATCH'     => [
                '/api/update/:int' => [
                    'execute'   => [
                        'Tests\\Unit\\ExampleController',
                        'patchUpdate'
                    ],
                    'options'   => []
                ]
            ],
            'DELETE'    => [
                '/api/delete/:int' => [
                    'execute'   => [
                        'Tests\\Unit\\ExampleController',
                        'deleteDelete'
                    ],
                    'options'   => []
                ]
            ]
        ];

        $this->assertEquals($expected, $this->router->getRoutes(null));
    }

}
