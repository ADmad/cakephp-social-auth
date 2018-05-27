<?php

namespace ADmad\SocialAuth\Test\TestCase\Middleware;

use ADmad\SocialAuth\Middleware\SocialAuthMiddleware;
use Cake\Http\Exception\MethodNotAllowedException;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;

/**
 * Test for SocialAuthMiddleware.
 */
class SocialAuthMiddlewareTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->response = new Response();

        include PLUGIN_ROOT . '/config/routes.php';
        Router::$initialized = true;
    }

    protected function _getNext()
    {
        return function ($req, $res) {
            return $res;
        };
    }

    public function testPassOnToNextForNonAuthUrls()
    {
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/',
        ]);

        $called = false;
        $next = function ($req, $res) use (&$called) {
            $called = true;

            return $res;
        };

        $middleware = new SocialAuthMiddleware();
        $middleware($request, $this->response, $next);

        $this->assertTrue($called);
    }

    public function testLoginUrl()
    {
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/social-auth/login/facebook',
            'REQUEST_METHOD' => 'POST',
        ]);
        $request = $request->withAttribute('params', [
            'plugin' => 'ADmad/SocialAuth',
            'controller' => 'Auth',
            'action' => 'login',
            'provider' => 'facebook',
        ]);

        $middleware = new SocialAuthMiddleware([
            'serviceConfig' => [
                'provider' => [
                    'facebook' => [
                        'applicationId' => '<application id>',
                        'applicationSecret' => '<application secret>',
                        'scope' => [
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
        ]);
        $response = $middleware($request, $this->response, $this->_getNext());

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertNotEmpty($response->getHeaderLine('Location'));
    }

    public function testLoginUrlException()
    {
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/social-auth/login/facebook',
        ]);
        $request = $request->withAttribute('params', [
            'plugin' => 'ADmad/SocialAuth',
            'controller' => 'Auth',
            'action' => 'login',
            'provider' => 'facebook',
        ]);

        $class = MethodNotAllowedException::class;
        if (!class_exists($class)) {
            $class = 'Cake\Network\Exception\MethodNotAllowedException';
        }
        $this->expectException($class);

        $middleware = new SocialAuthMiddleware();
        $response = $middleware($request, $this->response, $this->_getNext());
    }
}
