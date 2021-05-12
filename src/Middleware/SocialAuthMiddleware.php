<?php
declare(strict_types=1);

/**
 * ADmad\SocialAuth plugin.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */

namespace ADmad\SocialAuth\Middleware;

use Cake\Core\Configure;
use Cake\Core\InstanceConfigTrait;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Event\EventManager;
use Cake\Http\Client;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Http\Session as CakeSession;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Routing\Router;
use Cake\Utility\Hash;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use SocialConnect\Auth\Service;
use SocialConnect\Common\Entity\User as SocialConnectUser;
use SocialConnect\Common\Exception as SocialConnectException;
use SocialConnect\Common\HttpStack;
use SocialConnect\Provider\AccessTokenInterface;
use SocialConnect\Provider\Exception\InvalidResponse;
use SocialConnect\Provider\Session\Session as SocialConnectSession;
use SocialConnect\Provider\Session\SessionInterface;
use Zend\Diactoros\RequestFactory;
use Zend\Diactoros\StreamFactory;

class SocialAuthMiddleware implements MiddlewareInterface, EventDispatcherInterface
{
    use EventDispatcherTrait;
    use InstanceConfigTrait;
    use LocatorAwareTrait;

    /**
     * The query string key used for remembering the referrered page when
     * getting redirected to login.
     *
     * @var string
     */
    public const QUERY_STRING_REDIRECT = 'redirect';

    /**
     * The name of the event that is fired for a new user.
     *
     * @var string
     */
    public const EVENT_CREATE_USER = 'SocialAuth.createUser';

    /**
     * The name of the event that is fired after user identification.
     *
     * @var string
     */
    public const EVENT_AFTER_IDENTIFY = 'SocialAuth.afterIdentify';

    /**
     * The name of the event that is fired before redirection after authentication success/failure
     *
     * @var string
     */
    public const EVENT_BEFORE_REDIRECT = 'SocialAuth.beforeRedirect';

    /**
     * Auth success status.
     *
     * @var string
     */
    public const AUTH_STATUS_SUCCESS = 'success';

    /**
     * Auth provider failure status.
     *
     * @var string
     */
    public const AUTH_STATUS_PROVIDER_FAILURE = 'provider_failure';

    /**
     * Auth finder failure status.
     *
     * @var string
     */
    public const AUTH_STATUS_FINDER_FAILURE = 'finder_failure';

    /**
     * Default config.
     *
     * ### Options
     *
     * - `requestMethod`: Request method type. Default "POST".
     * - `loginUrl`: Login page URL. In case of auth failure user is redirected
     *   to this login page with "error" query string var.
     * - `userEntity`: Whether to return entity or array for user. Default `false`.
     * - `userModel`: User model name. Default "Users".
     * - `profileModel`: Social profile model. Default "ADmad/SocialAuth.SocialProfiles".
     * - `finder`: Table finder. Default "all".
     * - `fields`: Specify password field for removal in returned user identity.
     *   Default `['password' => 'password']`.
     * - `sessionKey`: Session key to write user record to. Default "Auth".
     * - `getUserCallback`: The callback method which will be called on user
     *   model for getting user record matching social profile. Defaults "getUser".
     * - `serviceConfig`: SocialConnect/Auth service providers config.
     * - `logErrors`: Whether social connect errors should be logged. Default `true`.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'requestMethod' => 'POST',
        'loginUrl' => '/users/login',
        'loginRedirect' => '/',
        'userEntity' => false,
        'userModel' => 'Users',
        'profileModel' => 'ADmad/SocialAuth.SocialProfiles',
        'finder' => 'all',
        'fields' => [
            'password' => 'password',
        ],
        'sessionKey' => 'Auth',
        'getUserCallback' => 'getUser',
        'serviceConfig' => [],
        'logErrors' => true,
    ];

    /**
     * SocialConnect service.
     *
     * @var \SocialConnect\Auth\Service|null
     */
    protected $_service;

    /**
     * Session for SocialConnect service.
     *
     * @var \SocialConnect\Provider\Session\SessionInterface|null
     */
    protected $_session;

    /**
     * User model instance.
     *
     * @var \Cake\ORM\Table
     */
    protected $_userModel;

    /**
     * Profile model instance.
     *
     * @var \Cake\ORM\Table
     */
    protected $_profileModel;

    /**
     * Error.
     *
     * @var string
     */
    protected $_error;

    /**
     * Constructor.
     *
     * @param array $config Configuration.
     * @param \Cake\Event\EventManager|null $eventManager Event manager instance.
     * @param \SocialConnect\Provider\Session\SessionInterface|null $session Session handler for SocialConnect Service
     */
    public function __construct(
        array $config = [],
        ?EventManager $eventManager = null,
        ?SessionInterface $session = null
    ) {
        $this->setConfig($config);

        if ($eventManager !== null) {
            $this->setEventManager($eventManager);
        }

        $this->_session = $session;
    }

    /**
     * Handle authentication.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @param \Psr\Http\Server\RequestHandlerInterface $handler The request handler.
     * @return \Psr\Http\Message\ResponseInterface A response.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $params = $request->getAttribute('params');
        $action = Hash::get($params, 'action');

        if (
            Hash::get($params, 'plugin') !== 'ADmad/SocialAuth'
            || Hash::get($params, 'controller') !== 'Auth'
            || !in_array($action, ['login', 'callback'], true)
        ) {
            return $handler->handle($request);
        }

        $method = '_handle' . ucfirst($action) . 'Action';

        return $this->{$method}($request);
    }

    /**
     * Handle login action, initiate authentication process.
     *
     * @param \Cake\Http\ServerRequest $request The request.
     * @return \Cake\Http\Response A response.
     */
    protected function _handleLoginAction(ServerRequest $request): Response
    {
        $request->allowMethod($this->getConfig('requestMethod'));

        $providerName = $request->getParam('provider');
        $provider = $this->_getService($request)->getProvider($providerName);
        $authUrl = $provider->makeAuthUrl();

        $this->_setRedirectUrl($request);

        return (new Response())->withLocation($authUrl);
    }

    /**
     * Handle callback action.
     *
     * @param \Cake\Http\ServerRequest $request The request.
     * @return \Cake\Http\Response A response.
     */
    protected function _handleCallbackAction(ServerRequest $request): Response
    {
        $this->_setupModelInstances();

        $config = $this->getConfig();
        $providerName = $request->getParam('provider');
        $response = new Response();

        $profile = $this->_getProfile($providerName, $request);
        if (!$profile) {
            $redirectUrl = $this->_triggerBeforeRedirect($request, $config['loginUrl'], $this->_error);

            return $response->withLocation(
                Router::url($redirectUrl, true)
            );
        }

        $user = $this->_getUser($profile, $request->getSession());
        if (!$user) {
            $redirectUrl = $this->_triggerBeforeRedirect($request, $config['loginUrl'], $this->_error);

            return $response->withLocation(
                Router::url($config['loginUrl'], true)
            );
        }

        $user->unset($config['fields']['password']);

        $event = $this->dispatchEvent(self::EVENT_AFTER_IDENTIFY, ['user' => $user]);
        $result = $event->getResult();
        if ($result !== null) {
            $user = $result;
        }

        if (!$config['userEntity']) {
            $user = $user->toArray();
        }

        $request->getSession()->write($config['sessionKey'], $user);

        $redirectUrl = $this->_triggerBeforeRedirect($request, $this->_getRedirectUrl($request));

        return $response->withLocation(
            Router::url($redirectUrl, true)
        );
    }

    /**
     * Setup model instances.
     *
     * @return void
     */
    protected function _setupModelInstances(): void
    {
        $this->_profileModel = $this->getTableLocator()->get($this->getConfig('profileModel'));
        $this->_profileModel->belongsTo($this->getConfig('userModel'));

        $this->_userModel = $this->getTableLocator()->get($this->getConfig('userModel'));
    }

    /**
     * Get social profile record.
     *
     * @param string $providerName Provider name.
     * @param \Cake\Http\ServerRequest $request Request instance.
     * @return \Cake\Datasource\EntityInterface|null
     */
    protected function _getProfile($providerName, ServerRequest $request): ?EntityInterface
    {
        try {
            $provider = $this->_getService($request)->getProvider($providerName);
            $accessToken = $provider->getAccessTokenByRequestParameters($request->getQueryParams());
            $identity = $provider->getIdentity($accessToken);

            if (!$identity->id) {
                throw new RuntimeException(
                    "`id` field is empty for the identity returned by `{$providerName}` provider."
                );
            }
        } catch (SocialConnectException $e) {
            $this->_error = self::AUTH_STATUS_PROVIDER_FAILURE;

            if ($this->getConfig('logErrors')) {
                Log::error($this->_getLogMessage($request, $e));
            }

            return null;
        }

        /** @var \Cake\Datasource\EntityInterface|null $profile */
        $profile = $this->_profileModel->find()
            ->where([
                $this->_profileModel->aliasField('provider') => $providerName,
                $this->_profileModel->aliasField('identifier') => $identity->id,
            ])
            ->first();

        return $this->_patchProfile(
            $providerName,
            $identity,
            $accessToken,
            $profile ?: null
        );
    }

    /**
     * Get user record.
     *
     * @param \Cake\Datasource\EntityInterface $profile Social profile entity
     * @param \Cake\Http\Session $session Session instance.
     * @return \Cake\Datasource\EntityInterface|null User array or entity
     *   on success, null on failure.
     */
    protected function _getUser(EntityInterface $profile, CakeSession $session): ?EntityInterface
    {
        $user = null;

        /** @var string $userPkField */
        $userPkField = $this->_userModel->getPrimaryKey();

        if ($profile->get('user_id')) {
            /** @var \Cake\Datasource\EntityInterface $user */
            $user = $this->_userModel->find()
                ->where([
                    $this->_userModel->aliasField($userPkField) => $profile->get('user_id'),
                ])
                ->find($this->getConfig('finder'))
                ->first();
        }

        if (!$user) {
            if ($profile->get('user_id')) {
                $this->_error = self::AUTH_STATUS_FINDER_FAILURE;

                return null;
            }

            $user = $this->_getUserEntity($profile, $session);
            $profile->set('user_id', $user->get($userPkField));
        }

        if ($profile->isDirty()) {
            $this->_saveProfile($profile);
        }

        $user->set('social_profile', $profile);
        $user->unset($this->getConfig('fields.password'));

        return $user;
    }

    /**
     * Get social profile entity.
     *
     * @param string $providerName Provider name.
     * @param \SocialConnect\Common\Entity\User $identity Social connect entity.
     * @param \SocialConnect\Provider\AccessTokenInterface $accessToken Access token
     * @param \Cake\Datasource\EntityInterface $profile Social profile entity
     * @return \Cake\Datasource\EntityInterface
     */
    protected function _patchProfile(
        $providerName,
        SocialConnectUser $identity,
        AccessTokenInterface $accessToken,
        ?EntityInterface $profile = null
    ): EntityInterface {
        if ($profile === null) {
            $profile = $this->_profileModel->newEntity([
                'provider' => $providerName,
            ]);
        }

        $data = [
            'access_token' => $accessToken,
        ];

        foreach (get_object_vars($identity) as $key => $value) {
            switch ($key) {
                case 'id':
                    $data['identifier'] = $value;
                    break;
                case 'lastname':
                    $data['last_name'] = $value;
                    break;
                case 'firstname':
                    $data['first_name'] = $value;
                    break;
                case 'birthday':
                    $data['birth_date'] = $value;
                    break;
                case 'emailVerified':
                    $data['email_verified'] = $value;
                    break;
                case 'fullname':
                    $data['full_name'] = $value;
                    break;
                case 'sex':
                    $data['gender'] = $value;
                    break;
                case 'pictureURL':
                    $data['picture_url'] = $value;
                    break;
                default:
                    $data[$key] = $value;
                    break;
            }
        }

        return $this->_profileModel->patchEntity($profile, $data);
    }

    /**
     * Get new user entity.
     *
     * The method specified in "getUserCallback" will be called on the User model
     * with profile entity. The method should return a persisted user entity.
     *
     * @param \Cake\Datasource\EntityInterface $profile Social profile entity.
     * @param \Cake\Http\Session $session Session instance.
     * @return \Cake\Datasource\EntityInterface User entity.
     */
    protected function _getUserEntity(EntityInterface $profile, CakeSession $session): EntityInterface
    {
        $event = $this->dispatchEvent(self::EVENT_CREATE_USER, [
            'profile' => $profile,
            'session' => $session,
        ]);

        $user = $event->getResult();
        if ($user === null) {
            $user = call_user_func(
                [$this->_userModel, $this->getConfig('getUserCallback')],
                $profile,
                $session
            );
        }

        if (!($user instanceof EntityInterface)) {
            throw new RuntimeException('The callback for new user must return an entity.');
        }

        return $user;
    }

    /**
     * Save social profile entity.
     *
     * @param \Cake\Datasource\EntityInterface $profile Social profile entity.
     * @throws \RuntimeException Thrown when unable to save social profile.
     * @return void
     */
    protected function _saveProfile(EntityInterface $profile): void
    {
        if (!$this->_profileModel->save($profile)) {
            throw new RuntimeException('Unable to save social profile.');
        }
    }

    /**
     * Get social connect service instance.
     *
     * @param \Cake\Http\ServerRequest $request Request instance.
     * @return \SocialConnect\Auth\Service
     */
    protected function _getService(ServerRequest $request): Service
    {
        if ($this->_service !== null) {
            return $this->_service;
        }

        $serviceConfig = $this->getConfig('serviceConfig');
        if (empty($serviceConfig)) {
            Configure::load('social_auth');
            $serviceConfig = Configure::consume('SocialAuth');
        }

        /** @psalm-suppress PossiblyInvalidArrayOffset */
        if (!isset($serviceConfig['redirectUri'])) {
            $serviceConfig['redirectUri'] = Router::url([
                'plugin' => 'ADmad/SocialAuth',
                'controller' => 'Auth',
                'action' => 'callback',
                '${provider}',
            ], true);
        }

        $request->getSession()->start();

        $httpStack = new HttpStack(
            new Client(),
            new RequestFactory(),
            new StreamFactory()
        );

        /** @psalm-suppress PossiblyNullArgument */
        $this->_service = new Service(
            $httpStack,
            $this->_session ?: new SocialConnectSession(),
            $serviceConfig
        );

        return $this->_service;
    }

    /**
     * Save URL to redirect to after authentication to session.
     *
     * @param \Cake\Http\ServerRequest $request Request instance.
     * @return void
     */
    protected function _setRedirectUrl(ServerRequest $request): void
    {
        $request->getSession()->delete('SocialAuth.redirectUrl');

        /** @var string $redirectUrl */
        $redirectUrl = $request->getQuery(static::QUERY_STRING_REDIRECT);
        if (
            empty($redirectUrl)
            || substr($redirectUrl, 0, 1) !== '/'
            || substr($redirectUrl, 0, 2) === '//'
        ) {
            return;
        }

        $request->getSession()->write('SocialAuth.redirectUrl', $redirectUrl);
    }

    /**
     * Get URL to redirect to after authentication.
     *
     * @param \Cake\Http\ServerRequest $request Request instance.
     * @return string|array
     */
    protected function _getRedirectUrl(ServerRequest $request)
    {
        $redirectUrl = $request->getSession()->read('SocialAuth.redirectUrl');
        if ($redirectUrl) {
            $request->getSession()->delete('SocialAuth.redirectUrl');

            return $redirectUrl;
        }

        return $this->getConfig('loginRedirect');
    }

    /**
     * Trigger "beforeRedirect" event.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request instance.
     * @param string|array $redirectUrl Redirect URL.
     * @param string $status Auth status.
     * @return string|array
     */
    protected function _triggerBeforeRedirect(
        $request,
        $redirectUrl,
        string $status = self::AUTH_STATUS_SUCCESS
    ) {
        $event = $this->dispatchEvent(self::EVENT_BEFORE_REDIRECT, [
            'redirectUrl' => $redirectUrl,
            'status' => $status,
            'request' => $request,
        ]);

        $result = $event->getResult();
        if ($result !== null) {
            $redirectUrl = $result;
        }

        return $redirectUrl;
    }

    /**
     * Generate the error log message.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The current request.
     * @param \Exception $exception The exception to log a message for.
     * @return string Error message
     */
    protected function _getLogMessage($request, $exception): string
    {
        $message = sprintf(
            '[%s] %s',
            get_class($exception),
            $exception->getMessage()
        );

        $message .= "\nRequest URL: " . $request->getRequestTarget();

        $referer = $request->getHeaderLine('Referer');
        if ($referer) {
            $message .= "\nReferer URL: " . $referer;
        }

        if ($exception instanceof InvalidResponse) {
            $response = $exception->getResponse();
            $message .= "\nProvider Response: " . ($response ? (string)$response->getBody() : 'n/a');
        }

        $message .= "\nStack Trace:\n" . $exception->getTraceAsString() . "\n\n";

        return $message;
    }
}
