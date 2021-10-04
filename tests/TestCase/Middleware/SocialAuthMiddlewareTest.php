<?php
declare(strict_types=1);

namespace ADmad\SocialAuth\Test\TestCase\Middleware;

use ADmad\SocialAuth\Middleware\SocialAuthMiddleware;
use ADmad\SocialAuth\Model\Entity\SocialProfile;
use ADmad\SocialAuth\Model\Table\SocialProfilesTable;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\Http\Exception\MethodNotAllowedException;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Cake\TestSuite\TestCase;
use SocialConnect\Common\Entity\User;
use SocialConnect\Provider\AccessTokenInterface;
use SocialConnect\Provider\Session\Dummy;
use TestApp\Http\TestRequestHandler;

/**
 * @property \Cake\Http\ServerRequest $request
 * @property \Psr\Http\Server\RequestHandlerInterface $handler
 */
class SocialAuthMiddlewareTest extends TestCase
{
    protected $fixtures = [
        'plugin.ADmad/SocialAuth.Users',
        'plugin.ADmad/SocialAuth.SocialProfiles',
    ];

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

        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($this->handler->called);
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
        $middleware->process($request, $this->handler);
    }

    /**
     * Test that IDENTITY_MISMATCH_ERROR occurs when a social profile is already
     * associated with a user and that user does not match the user record set
     * in the session.
     *
     * @return void
     * @see https://github.com/ADmad/cakephp-social-auth/pull/108
     */
    public function testIdentityMismatchError()
    {
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/social-auth/callback/facebook',
        ]);
        $request = $request->withAttribute('params', [
            'plugin' => 'ADmad/SocialAuth',
            'controller' => 'Auth',
            'action' => 'callback',
            'provider' => 'facebook',
        ]);
        $request->getSession()->write('Auth', ['id' => 1]);

        $this->getTableLocator()->get(SocialProfilesTable::class)
            ->save(new SocialProfile([
                'user_id' => 2,
                'provider' => 'facebook',
                'identifier' => 'fbid',
                'email' => 'fb@fb.test',
                'access_token' => '',
            ]));

        $middleware = $this->getMockBuilder(SocialAuthMiddleware::class)
            ->onlyMethods(['_getSocialIdentity'])
            ->getMock();

        $accessToken = $this->getMockBuilder(AccessTokenInterface::class)
            ->getMock();

        $identity = new User();
        $identity->id = 'fbid';
        $identity->email = 'fb@fb.test';

        $middleware->expects($this->once())
            ->method('_getSocialIdentity')
            ->willReturn(['identity' => $identity, 'access_token' => $accessToken]);

        EventManager::instance()->on(
            SocialAuthMiddleware::EVENT_BEFORE_REDIRECT,
            function (EventInterface $event, $url, string $status) {
                $this->assertSame(SocialAuthMiddleware::AUTH_STATUS_IDENTITY_MISMATCH, $status);
            }
        );

        $middleware->process($request, $this->handler);
    }
}
