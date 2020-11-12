# CakePHP SocialAuth Plugin

[![Total Downloads](https://img.shields.io/packagist/dt/ADmad/cakephp-social-auth.svg?style=flat-square)](https://packagist.org/packages/admad/cakephp-social-auth)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)

A CakePHP plugin which allows you authenticate using social providers like
Facebook/Google/Twitter etc. using [SocialConnect/auth](https://github.com/SocialConnect/auth)
social sign on library.

## Installation

Run:

```
composer require admad/cakephp-social-auth
```

## Setup

Load the plugin by running following command in terminal:

```
bin/cake plugin load ADmad/SocialAuth
```

## Database

This plugin requires a migration to generate a `social_profiles` table, and it
can be generated via the official Migrations plugin as follows:

```shell
bin/cake migrations migrate -p ADmad/SocialAuth
```

## Usage

### Middleware config

The plugin provides a `\ADmad\SocialAuth\Middleware\SocialAuthMiddleware` which
handles authentication process through social providers.

You can configure the middleware in your `Application::middleware()` method as shown:

```php
// src/Application.php

// Be sure to add SocialAuthMiddleware after RoutingMiddleware
$middlewareQueue->add(new \ADmad\SocialAuth\Middleware\SocialAuthMiddleware([
    // Request method type use to initiate authentication.
    'requestMethod' => 'POST',
    // Login page URL. In case of auth failure user is redirected to login
    // page with "error" query string var.
    'loginUrl' => '/users/login',
    // URL to redirect to after authentication (string or array).
    'loginRedirect' => '/',
    // Boolean indicating whether user identity should be returned as entity.
    'userEntity' => false,
    // User model.
    'userModel' => 'Users',
    // Social profile model.
    'socialProfileModel' => 'ADmad/SocialAuth.SocialProfiles',
    // Finder type.
    'finder' => 'all',
    // Fields.
    'fields' => [
        'password' => 'password',
    ],
    // Session key to which to write identity record to.
    'sessionKey' => 'Auth.User',
    // The method in user model which should be called in case of new user.
    // It should return a User entity.
    'getUserCallback' => 'getUser',
    // SocialConnect Auth service's providers config. https://github.com/SocialConnect/auth/blob/master/README.md
    'serviceConfig' => [
        'provider' => [
            'facebook' => [
                'applicationId' => '<application id>',
                'applicationSecret' => '<application secret>',
                'scope' => [
                    'email',
                ],
                'options' => [
                    'identity.fields' => [
                    'email',
                    // To get a full list of all possible values, refer to
                    // https://developers.facebook.com/docs/graph-api/reference/user
                    ],
                ],
            ],
            'google' => [
                'applicationId' => '<application id>',
                'applicationSecret' => '<application secret>',
                'scope' => [
                    'https://www.googleapis.com/auth/userinfo.email',
                    'https://www.googleapis.com/auth/userinfo.profile',
                ],
            ],
        ],
    ],
    // Whether social connect errors should be logged. Default `true`.
    'logErrors' => true,
]));
```

### Login links

On your login page you can create links to initiate authentication using required
providers. E.g.

```php
echo $this->Form->postLink(
    'Login with Facebook',
    [
        'prefix' => false,
        'plugin' => 'ADmad/SocialAuth',
        'controller' => 'Auth',
        'action' => 'login',
        'provider' => 'facebook',
        '?' => ['redirect' => $this->request->getQuery('redirect')]
    ]
);
```

We use a `POST` link here instead of a normal link to prevent search bots and other
crawlers from following the link. If you prefer using GET you can still do so by
configuring the middleware with `'requestMethod' => 'GET'`. In this case it's
advisable to add `nofollow` attribute to the link.

### Authentication process

Depending on the provider name in the login URL the authentication process is
initiated.

Once a user is authenticated through the provider, the middleware gets the user
profile from the identity provider and using that tries to find the corresponding
user record using the user model. If no user is found it calls the `getUser` method
of your user model. The method recieves social profile model entity and session
instance as argument and must return an entity for the user.

```php
// src/Model/Table/UsersTable.php

public function getUser(\Cake\Datasource\EntityInterface $profile, \Cake\Http\Session $session) {
    // Make sure here that all the required fields are actually present
    if (empty($profile->email)) {
        throw new \RuntimeException('Could not find email in social profile.');
    }

    // If you want to associate the social entity with currently logged in user
    // use the $session argument to get user id and find matching user entity.
    $userId = $session->read('Auth.User.id');
    if ($userId) {
        return $this->get($userId);
    }

    // Check if user with same email exists. This avoids creating multiple
    // user accounts for different social identities of same user. You should
    // probably skip this check if your system doesn't enforce unique email
    // per user.
    $user = $this->find()
        ->where(['email' => $profile->email])
        ->first();

    if ($user) {
        return $user;
    }

    // Create new user account
    $user = $this->newEntity(['email' => $profile->email]);
    $user = $this->save($user);

    if (!$user) {
        throw new \RuntimeException('Unable to save new user');
    }

    return $user;
}
```

Upon successful authentication an `SocialAuth.afterIdentify` event is
dispatched with the user entity and session object. You can setup a listener for this event to
perform required tasks after a successful authentication. The listener can
optionally return a user entity as event result.

The user identity is persisted to session under key you have specified in
middleware config (`Auth.User` by default).

In case of authentication failure user is redirected back to login URL with
`error` query string variable. It can have one of these values:

- `provider_failure`: Auth through provider failed. Details will be logged in
  `error.log` if `logErrors` option is set to `true`.
- `finder_failure`: Finder failed to return user record. An e.g. of this is
  a user has been authenticated through provider but your finder has condition
  to not return inactivate user.

### Event Listener

To set up a listener for the `SocialAuth.afterIdentify` event, you can for example
add this to your `UsersTable::initialize()` method:
```php
use Cake\Event\EventManager;

// at the end of the initialize() method
EventManager::instance()->on('SocialAuth.afterIdentify', [$this, 'afterIdentify']);
```

Then create such method in this Table class:
```php
    /**
     * @param \Cake\Event\EventInterface $event
     * @param \App\Model\Entity\User $user
     * @param \Cake\Http\Session $session
     * @return \App\Model\Entity\User
     */
    public function afterIdentify(EventInterface $event, $user, $session)
    {
        // You can access the profile through $user->social_profile->...

        // Additional mapping operations
        // $user->last_login = date('Y-m-d H:i:s');

        $this->saveOrFail($user);

        return $user;
    }
```

### Flash messages
You can use the existence of this `error` query string for a flash message feedback:
```php
if ($this->request->getQuery('error')) {
    $this->Flash->error(__('Login failed. Please try again.'));
}
```
With [Flash plugin](https://github.com/dereuromark/cakephp-flash) you can also use `transientError()` here to avoid session usage.

For successful login and flash you can use the above event and manually write to the session:
```php
    /**
     * @param \Cake\Event\EventInterface $event
     * @param \App\Model\Entity\User $user
     * @param \Cake\Http\Session $session
     *
     * @return null
     */
    public function afterIdentify(EventInterface $event, User $user, Session $session)
    {
        $messages = (array)$session->read('Flash.flash');
        $messages[] = [
            'message' => __('You are now logged in'),
            'key' => 'flash',
            'element' => 'flash/success',
            'params' => [],
        ];
        $session->write('Flash.flash', $messages);

        return null;
    }
```
Since we redirect afterwards, we must write to the session here.


Copyright
---------
Copyright 2017-Present ADmad

License
-------
[See LICENSE](LICENSE.txt)
