<?php
/**
 * ADmad\SocialAuth plugin.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */

namespace ADmad\SocialAuth\Middleware;

use ADmad\SocialAuth\Http\Client;
use Cake\Core\Configure;
use Cake\Core\InstanceConfigTrait;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\ModelAwareTrait;
use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Event\EventManager;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Log\Log;
use Cake\Routing\Router;
use RuntimeException;
use SocialConnect\Auth\Service;
use SocialConnect\Common\Entity\User as SocialConnectUser;
use SocialConnect\Common\Exception as SocialConnectException;
use SocialConnect\Provider\AccessTokenInterface;
use SocialConnect\Provider\Exception\InvalidResponse;
use SocialConnect\Provider\Session\Session;

class SocialAuthMiddleware implements EventDispatcherInterface
{
    use EventDispatcherTrait;
    use InstanceConfigTrait;
    use ModelAwareTrait;

    /**
     * The query string key used for remembering the referrered page when
     * getting redirected to login.
     */
    const QUERY_STRING_REDIRECT = 'redirect';

    /**
     * The name of the event that is fired after user identification.
     */
    const EVENT_AFTER_IDENTIFY = 'SocialAuth.afterIdentify';

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
     * - `sessionKey`: Session key to write user record to. Default "Auth.User".
     * - `getUserCallback`: The callback method which will be called on user
     *   model for getting user record matching social profile. Defaults "getUser".
     * - `serviceConfig`: SocialConnect/Auth service providers config.
     * - `httpClient`: The HTTP Client to use for SocialConnect Auth service.
     *   Either a  class name string or instance. Defaults to `'ADmad\SocialAuth\Http\Client'`.
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
        'sessionKey' => 'Auth.User',
        'getUserCallback' => 'getUser',
        'serviceConfig' => [],
        'httpClient' => Client::class,
        'logErrors' => true,
    ];

    /**
     * SocialConnect service.
     *
     * @var \SocialConnect\Auth\Service|null
     */
    protected $_service;

    /**
     * User model instance.
     *
     * @var \Cake\ORM\Table|null
     */
    protected $_userModel;

    /**
     * Profile model instance.
     *
     * @var \Cake\ORM\Table|null
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
     */
    public function __construct(array $config = [], EventManager $eventManager = null)
    {
        $this->setConfig($config);

        if ($eventManager !== null) {
            $this->setEventManager($eventManager);
        }
    }

    /**
     * Handle authentication.
     *
     * @param \Cake\Http\ServerRequest $request The request.
     * @param \Cake\Http\Response $response The response.
     * @param callable $next Callback to invoke the next middleware.
     *
     * @return \Cake\Http\Response A response.
     */
    public function __invoke(ServerRequest $request, Response $response, $next)
    {
        $action = $request->getParam('action');

        if ($request->getParam('plugin') !== 'ADmad/SocialAuth'
            || $request->getParam('controller') !== 'Auth'
            || !in_array($action, ['login', 'callback'], true)
        ) {
            return $next($request, $response);
        }

        $method = '_handle' . ucfirst($action) . 'Action';

        return $this->{$method}($request, $response);
    }

    /**
     * Handle login action, initiate authentication process.
     *
     * @param \Cake\Http\ServerRequest $request The request.
     * @param \Cake\Http\Response $response The response.
     *
     * @return \Cake\Http\Response A response.
     */
    protected function _handleLoginAction(ServerRequest $request, Response $response)
    {
        $request->allowMethod($this->getConfig('requestMethod'));

        $providerName = $request->getParam('provider');
        $provider = $this->_getService($request)->getProvider($providerName);
        $authUrl = $provider->makeAuthUrl();

        $this->_setRedirectUrl($request);

        return $response->withLocation($authUrl);
    }

    /**
     * Handle callback action.
     *
     * @param \Cake\Http\ServerRequest $request The request.
     * @param \Cake\Http\Response $response The response.
     *
     * @return \Cake\Http\Response A response.
     */
    protected function _handleCallbackAction(ServerRequest $request, Response $response)
    {
        $this->_setupModelInstances();

        $config = $this->getConfig();
        $providerName = $request->getParam('provider');

        $profile = $this->_getProfile($providerName, $request);
        if (!$profile) {
            return $response->withLocation(
                Router::url($config['loginUrl'], true) . '?error=' . $this->_error
            );
        }

        $user = $this->_getUser($profile);
        if (!$user) {
            return $response->withLocation(
                Router::url($config['loginUrl'], true) . '?error=' . $this->_error
            );
        }

        $user->unsetProperty($config['fields']['password']);

        if (!$config['userEntity']) {
            $user = $user->toArray();
        }

        $event = $this->dispatchEvent(self::EVENT_AFTER_IDENTIFY, ['user' => $user]);
        $result = $event->getResult();
        if ($result !== null) {
            $user = $event->getResult();
        }

        $request->getSession()->write($config['sessionKey'], $user);

        return $response->withLocation(
            Router::url($this->_getRedirectUrl($request), true)
        );
    }

    /**
     * Setup model instances.
     *
     * @return void
     */
    protected function _setupModelInstances()
    {
        $this->_profileModel = $this->loadModel($this->getConfig('profileModel'));
        $this->_profileModel->belongsTo($this->getConfig('userModel'));

        $this->_userModel = $this->loadModel($this->getConfig('userModel'));
    }

    /**
     * Get social profile record.
     *
     * @param string $providerName Provider name.
     * @param \Cake\Http\ServerRequest $request Request instance.
     *
     * @return \Cake\Datasource\EntityInterface|null
     */
    protected function _getProfile($providerName, ServerRequest $request)
    {
        try {
            $provider = $this->_getService($request)->getProvider($providerName);
            $accessToken = $provider->getAccessTokenByRequestParameters($request->getQueryParams());
            $identity = $provider->getIdentity($accessToken);
        } catch (SocialConnectException $e) {
            $this->_error = 'provider_failure';

            if ($this->getConfig('logErrors')) {
                Log::error($this->_getLogMessage($request, $e));
            }

            return null;
        }

        $profile = $this->_profileModel->find()
            ->where([
                $this->_profileModel->aliasField('provider') => $providerName,
                $this->_profileModel->aliasField('identifier') => $identity->id,
            ])
            ->first();

        $profile = $this->_patchProfile(
            $providerName,
            $identity,
            $accessToken,
            $profile ?: null
        );
        if ($profile->isDirty()) {
            $this->_saveProfile($profile);
        }

        return $profile;
    }

    /**
     * Get user record.
     *
     * @param \Cake\Datasource\EntityInterface $profile Social profile entity
     *
     * @return array|\Cake\Datasource\EntityInterface|null User array or entity
     *   on success, null on failure.
     */
    protected function _getUser($profile)
    {
        $user = null;

        if ($profile->get('user_id')) {
            $userPkField = $this->_userModel->aliasField((string)$this->_userModel->getPrimaryKey());

            $user = $this->_userModel->find()
                ->where([
                    $userPkField => $profile->get('user_id'),
                ])
                ->find($this->getConfig('finder'))
                ->first();
        }

        if (!$user) {
            if ($profile->get('user_id')) {
                $this->_error = 'finder_failure';

                return null;
            }

            $user = $this->_getUserEntity($profile);
            $profile->set('user_id', $user->id);
        }

        if ($profile->isDirty()) {
            $this->_saveProfile($profile);
        }

        $user->set('social_profile', $profile);
        $user->unsetProperty($this->getConfig('fields.password'));

        return $user;
    }

    /**
     * Get social profile entity.
     *
     * @param string $providerName Provider name.
     * @param \SocialConnect\Common\Entity\User $identity Social connect entity.
     * @param \SocialConnect\Provider\AccessTokenInterface $accessToken Access token
     * @param \Cake\Datasource\EntityInterface $profile Social profile entity
     *
     * @return \Cake\Datasource\EntityInterface
     */
    protected function _patchProfile(
        $providerName,
        SocialConnectUser $identity,
        AccessTokenInterface $accessToken,
        EntityInterface $profile = null
    ) {
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
     *
     * @return \Cake\Datasource\EntityInterface User entity.
     */
    protected function _getUserEntity(EntityInterface $profile)
    {
        $callbackMethod = $this->getConfig('getUserCallback');

        $user = call_user_func([$this->_userModel, $callbackMethod], $profile);

        if (!($user instanceof EntityInterface)) {
            throw new RuntimeException('"getUserCallback" method must return a user entity.');
        }

        return $user;
    }

    /**
     * Save social profile entity.
     *
     * @param \Cake\Datasource\EntityInterface $profile Social profile entity.
     *
     * @throws \RuntimeException Thrown when unable to save social profile.
     *
     * @return void
     */
    protected function _saveProfile(EntityInterface $profile)
    {
        if (!$this->_profileModel->save($profile)) {
            throw new RuntimeException('Unable to save social profile.');
        }
    }

    /**
     * Get social connect service instance.
     *
     * @param \Cake\Http\ServerRequest $request Request instance.
     *
     * @return \SocialConnect\Auth\Service
     */
    protected function _getService(ServerRequest $request)
    {
        if ($this->_service !== null) {
            return $this->_service;
        }

        $serviceConfig = $this->getConfig('serviceConfig');
        if (empty($serviceConfig)) {
            Configure::load('social_auth');
            $serviceConfig = Configure::consume('SocialAuth');
        }

        $serviceConfig['redirectUri'] = Router::url([
            'plugin' => 'ADmad/SocialAuth',
            'controller' => 'Auth',
            'action' => 'callback',
        ], true);

        $request->getSession()->start();

        $httpClient = $this->getConfig('httpClient');
        if (is_string($httpClient)) {
            $httpClient = new $httpClient();
        }

        $this->_service = new Service(
            $httpClient,
            new Session(),
            $serviceConfig
        );

        return $this->_service;
    }

    /**
     * Save URL to redirect to after authentication to session.
     *
     * @param \Cake\Http\ServerRequest $request Request instance.
     *
     * @return void
     */
    protected function _setRedirectUrl(ServerRequest $request)
    {
        $request->getSession()->delete('SocialAuth.redirectUrl');

        $redirectUrl = $request->getQuery(static::QUERY_STRING_REDIRECT);
        if (empty($redirectUrl)
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
     *
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
     * Generate the error log message.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The current request.
     * @param \Exception $exception The exception to log a message for.
     *
     * @return string Error message
     */
    protected function _getLogMessage($request, $exception)
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

        if ($exception instanceof InvalidResponse && $exception->getResponse()) {
            $message .= "\nProvider Response: " . $exception->getResponse()->getBody();
        }

        $message .= "\nStack Trace:\n" . $exception->getTraceAsString() . "\n\n";

        return $message;
    }
}
