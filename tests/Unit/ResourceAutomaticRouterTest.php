<?php
/**
 * ResourceAutomaticRouterTest.php
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

use \InitPHP\HTTP\Message\{Request, Response, Stream};
use InitPHP\Router\Router;
use \Psr\Http\Message\{RequestInterface, ResponseInterface};

class ResourceAutomaticRouterTest extends \PHPUnit\Framework\TestCase
{
    /** @var RequestInterface */
    protected $request;

    /** @var ResponseInterface */
    protected $response;

    /** @var Router */
    protected $router;

    protected function setUp(): void
    {
        $this->request = new Request('GET', 'http://www.example.com/', [], null, '1.1');
        $this->response = new Response(200, [], new Stream('', null), '1.1');
        $this->router = new Router($this->request, $this->response, [
            'paths'         => [
                'controller'    => __DIR__ . '/',
            ],
            'namespaces'    => [
                'controller'    => '\\Tests\\Router\\Unit',
            ]
        ]);
        parent::setUp();
    }

    public function testResourceControllerAutomaticRoutesRegister()
    {
        $this->router->resource('photos', 'ExampleController');

        $options = [
            'paths'     => [
                'controller'    => __DIR__ . '/',
            ],
            'namespaces' => [
                'controller'    => '\Tests\Router\Unit',
            ],
        ];

        $expected = [
            'GET'       => [
                'http://www.example.com/photos' => [
                    'execute'   => '\Tests\Router\Unit\ExampleController::index',
                    'options'   => $options,
                ],
                'http://www.example.com/photos/create' => [
                    'execute'   => '\Tests\Router\Unit\ExampleController::create',
                    'options'   => $options,
                ],
                'http://www.example.com/photos/{photos}' => [
                    'execute'   => '\Tests\Router\Unit\ExampleController::show',
                    'options'   => $options,
                ],
                'http://www.example.com/photos/{photos}/edit' => [
                    'execute'   => '\Tests\Router\Unit\ExampleController::edit',
                    'options'   => $options,
                ]
            ],
            'POST'      => [
                'http://www.example.com/photos' => [
                    'execute'   => '\Tests\Router\Unit\ExampleController::store',
                    'options'   => $options,
                ]
            ],
            'PUT'       => [
                'http://www.example.com/photos/{photos}' => [
                    'execute'   => '\Tests\Router\Unit\ExampleController::update',
                    'options'   => $options,
                ]
            ],
            'PATCH'     => [
                'http://www.example.com/photos/{photos}' => [
                    'execute'   => '\Tests\Router\Unit\ExampleController::update',
                    'options'   => $options,
                ]
            ],
            'DELETE'    => [
                'http://www.example.com/photos/{photos}' => [
                    'execute'   => '\Tests\Router\Unit\ExampleController::destroy',
                    'options'   => $options,
                ]
            ],
        ];

        $this->assertEquals($expected, $this->router->getRoutes());
    }


}

