<?php

declare(strict_types=1);

namespace ADmad\SocialAuth\Test\TestCase\Middleware;

use ADmad\SocialAuth\Middleware\SocialAuthMiddleware;
use Cake\Http\Exception\MethodNotAllowedException;
use Cake\Http\ServerRequestFactory;
use Cake\TestSuite\TestCase;
use SocialConnect\Provider\Session\Dummy;
use TestApp\Http\TestRequestHandler;

/**
 * Test for SocialAuthMiddleware.
 */
class SocialAuthMiddlewareTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->loadPlugins(['ADmad/SocialAuth']);

        $_SERVER['REQUEST_URI'] = '/';
        $this->request = ServerRequestFactory::fromGlobals();
        $this->request = $this->request->withAttribute('webroot', '/');
        $this->handler = new TestRequestHandler();
    }

    public function testPassOnToNextForNonAuthUrls()
    {
        $middleware = new SocialAuthMiddleware();
        $response = $middleware->process($this->request, $this->handler);

        $this->assertTrue($this->handler->called);
    }

    public function testLoginUrl()
    {
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI'    => '/social-auth/login/facebook',
            'REQUEST_METHOD' => 'POST',
        ]);
        $request = $request->withAttribute('params', [
            'plugin'     => 'ADmad/SocialAuth',
            'controller' => 'Auth',
            'action'     => 'login',
            'provider'   => 'facebook',
        ]);

        $middleware = new SocialAuthMiddleware([
            'serviceConfig' => [
                'provider' => [
                    'facebook' => [
                        'applicationId'     => '<application id>',
                        'applicationSecret' => '<application secret>',
                        'scope'             => [
                            'email',
                        ],
                        'fields' => [
                            'email',
                            // To get a full list of all posible values, refer to
                            // https://developers.facebook.com/docs/graph-api/reference/user
                        ],
                    ],
                ],
            ],
        ], null, new Dummy());

        $response = $middleware->process($request, $this->handler);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertNotEmpty($response->getHeaderLine('Location'));
    }

    public function testLoginUrlException()
    {
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/social-auth/login/facebook',
        ]);
        $request = $request->withAttribute('params', [
            'plugin'     => 'ADmad/SocialAuth',
            'controller' => 'Auth',
            'action'     => 'login',
            'provider'   => 'facebook',
        ]);

        $class = MethodNotAllowedException::class;
        if (!class_exists($class)) {
            $class = 'Cake\Network\Exception\MethodNotAllowedException';
        }
        $this->expectException($class);

        $middleware = new SocialAuthMiddleware();
        $response = $middleware->process($request, $this->handler);
    }
}
