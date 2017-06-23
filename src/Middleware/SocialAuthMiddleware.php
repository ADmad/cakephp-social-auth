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
use Cake\Event\EventManagerTrait;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Network\Exception\BadRequestException;
use Cake\Routing\Router;
use RuntimeException;
use SocialConnect\Auth\Service;
use SocialConnect\Common\Entity\User as SocialConnectUser;
use SocialConnect\Provider\AccessTokenInterface;
use SocialConnect\Provider\Session\Session;

class SocialAuthMiddleware
{
    use EventManagerTrait;
    use InstanceConfigTrait;
    use ModelAwareTrait;

    /**
     * Default config.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'requestMethod' => 'POST',
        'loginRedirect' => '/',
        'userEntity' => false,
        'userModel' => 'Users',
        'finder' => 'all',
        'fields' => [
            'email' => 'email',
            'password' => 'password',
        ],
        'sessionKey' => 'Auth.User',
        'newUserCallback' => 'newUser',
        'serviceConfig' => [],
    ];

    /**
     * SocialConnect service.
     *
     * @var \SocialConnect\Auth\Service
     */
    protected $_service;

    /**
     * User model instance.
     *
     * @var \Cake\Datasource\RepositoryInterface
     */
    protected $_userModel;

    /**
     * Constructor.
     *
     * @param array $config Configuration.
     */
    public function __construct(array $config = [])
    {
        $this->config($config);
    }

    /**
     * Serve assets if the path matches one.
     *
     * @param \Cake\Http\ServerRequest $request The request.
     * @param \Cake\Http\Response $response The response.
     * @param callable $next Callback to invoke the next middleware.
     *
     * @throws \Cake\Network\Exception\BadRequestException If login action is
     *  called with incorrect request method.
     *
     * @return \Psr\Http\Message\ResponseInterface A response.
     */
    public function __invoke(ServerRequest $request, Response $response, $next)
    {
        $action = $request->getParam('action');

        if ($request->getParam('plugin') !== 'ADmad/SocialAuth'
            && $request->getParam('controller') !== 'Auth'
        ) {
            return $next($request, $response);
        }

        if (!in_array($action, ['login', 'callback'])) {
            return $next($request, $response);
        }

        $config = $this->config();
        $providerName = $request->getParam('provider');

        if ($action === 'login') {
            if ($request->getMethod() !== $config['requestMethod']) {
                throw new BadRequestException();
            }

            $provider = $this->_getService($request)->getProvider($providerName);
            $authUrl = $provider->makeAuthUrl();

            return $response->withLocation($authUrl);
        }

        $user = $this->_getUser($providerName, $request);
        $user->unsetProperty($config['fields']['password']);

        if (!$config['userEntity']) {
            $user = $user->toArray();
        }

        $request->session()->write($config['sessionKey'], $user);

        return $response->withLocation(Router::url($config['loginRedirect']), true);
    }

    /**
     * Get user record.
     *
     * @param string $providerName Provider name.
     * @param \Cake\Http\ServerRequest $request Request instance.
     *
     * @return array|\Cake\Database\EntityInterface|bool User array or entity
     *   on success, false on failure.
     */
    protected function _getUser($providerName, ServerRequest $request)
    {
        $userModel = $this->config('userModel');

        $this->loadModel('ADmad/SocialAuth.SocialProfiles');
        $this->SocialProfiles->belongsTo($userModel);

        $this->_userModel = $this->loadModel($userModel);

        $provider = $this->_getService($request)->getProvider($providerName);
        $accessToken = $provider->getAccessTokenByRequestParameters($request->getQueryParams());
        $identity = $provider->getIdentity($accessToken);

        $user = null;
        $profile = $this->SocialProfiles->find()
            ->where([
                $this->SocialProfiles->aliasField('provider') => $providerName,
                $this->SocialProfiles->aliasField('identifier') => $identity->id,
            ])
            ->first();

        $finder = $this->config('finder');
        if ($profile) {
            $userId = $profile->user_id;
            $user = $this->_userModel->get($userId);
            $user = $this->_userModel->find($finder)
                ->where([
                    $this->_userModel->aliasField($this->_userModel->primaryKey()) => $userId,
                ])
                ->first();

            // User record exists but finder conditions did not match,
            // so just update social profile record and return false.
            if (!$user) {
                $profile = $this->_getProfileEntity($providerName, $identity, $accessToken, $profile);
                if ($profile->isDirty()) {
                    $this->_saveProfile($profile);
                }

                return false;
            }
        }

        $profile = $this->_getProfileEntity($providerName, $identity, $accessToken, $profile ?: null);

        if (!$user) {
            $user = $this->_newUser($profile);
        }
        $profile->user_id = $user->id;

        if ($profile->isDirty()) {
            $this->_saveProfile($profile);
        }

        $user->set('social_profile', $profile);
        $user->unsetProperty('password');

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
    protected function _getProfileEntity(
        $providerName,
        SocialConnectUser $identity,
        AccessTokenInterface $accessToken,
        EntityInterface $profile = null
    ) {
        if ($profile === null) {
            $profile = $this->SocialProfiles->newEntity([
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
                default:
                    $data[$key] = $value;
                    break;
            }
        }

        return $this->SocialProfiles->patchEntity($profile, $data);
    }

    /**
     * Get new user entity.
     *
     * It dispatches a `SocialConnect.newUser` event. A listener must return
     * an entity for new user record.
     *
     * @param \Cake\Datasource\EntityInterface $profile Social profile entity.
     *
     * @return \Cake\Datasource\EntityInterface User entity.
     */
    protected function _newUser(EntityInterface $profile)
    {
        $callbackMethod = $this->config('newUserCallback');

        $user = call_user_func([$this->_userModel, $callbackMethod], $profile);

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
        if (!$this->SocialProfiles->save($profile)) {
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

        $serviceConfig = $this->config('serviceConfig');
        if (empty($serviceConfig)) {
            Configure::load('social_auth');
            $serviceConfig = Configure::consume('SocialAuth');
        }

        $serviceConfig['redirectUri'] = Router::url([
            'plugin' => 'ADmad/SocialAuth',
            'controller' => 'Auth',
            'action' => 'callback',
        ], true);

        $request->session()->start();

        $this->_service = new Service(
            new Client(),
            new Session(),
            $serviceConfig
        );

        return $this->_service;
    }
}
