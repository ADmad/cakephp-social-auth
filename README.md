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
    'sessionKey' => 'Auth',
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
    // Instance of `\SocialConnect\Auth\CollectionFactory`. If none provided one will be auto created. Default `null`.
    'collectionFactory' => null,
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
use \Cake\Datasource\EntityInterface;
use \Cake\Http\Session;

public function getUser(EntityInterface $profile, Session $session)
{
    // Make sure here that all the required fields are actually present
    if (empty($profile->email)) {
        throw new \RuntimeException('Could not find email in social profile.');
    }

    // If you want to associate the social entity with currently logged in user
    // use the $session argument to get user id and find matching user entity.
    $userId = $session->read('Auth.id');
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

Instead of adding a `getUser` method to your `UsersTable` you can also setup a
listener for the `SocialAuth.createUser` callback and return a `User` entity from
the listener callback, in a similar way as shown above.

Upon successful authentication the user identity is persisted to the session
under the key you have specified in the middleware config (`Auth.User` by default).

After that the user is redirected to protected page they tried to access before
login or to the URL specified in `loginRedirect` config.

In case of authentication failure the user is redirected back to login URL.

### Events

#### SocialAuth.createUser

After authentication from the social auth provider if a related use record is not
found then `SocialAuth.createUser` is triggered. As an alternative to adding a
new `createUser()` method in your `UsersTable` as mentioned above you can instead
use this event to return an entity for a new user.

#### SocialAuth.afterIdentify

Upon successful authentication a `SocialAuth.afterIdentify` event is
dispatched with the user entity. You can setup a listener for this event to
perform required tasks. The listener can optionally return a user entity as
event result.

#### SocialAuth.beforeRedirect

After the completion of authentication process before the user is redirected
to required URL a `SocialAuth.beforeRedirect` event is triggered. This event
for e.g. can be used to set a visual notification like flash message to indicate
the result of the authentication process to the user.

Here's an e.g. listener with callbacks to the above method:

```php
// src/Event/SocialAuthListener.php

namespace App\Event;

use ADmad\SocialAuth\Middleware\SocialAuthMiddleware;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\Event\EventListenerInterface;
use Cake\Http\ServerRequest;
use Cake\I18n\FrozenTime;
use Cake\ORM\Locator\LocatorAwareTrait;

class SocialAuthListener implements EventListenerInterface
{
    use LocatorAwareTrait;

    public function implementedEvents(): array
    {
        return [
            SocialAuthMiddleware::EVENT_AFTER_IDENTIFY => 'afterIdentify',
            SocialAuthMiddleware::EVENT_BEFORE_REDIRECT => 'beforeRedirect',
            // Uncomment below if you want to use the event listener to return
            // an entity for a new user instead of directly using `createUser()` table method.
            // SocialAuthMiddleware::EVENT_CREATE_USER => 'createUser',
        ];
    }

    public function afterIdentify(EventInterface $event, EntityInterface $user): EntityInterface
    {
        // Update last login time
        $user->set('last_login', new FrozenTime());

        // You can access the profile using $user->social_profile

        $this->getTableLocator()->get('Users')->saveOrFail($user);

        return $user;
    }

    /**
     * @param \Cake\Event\EventInterface $event
     * @param string|array $url
     * @param string $status
     * @param \Cake\Http\ServerRequest $request
     * @return void
     */
    public function beforeRedirect(EventInterface $event, $url, string $status, ServerRequest $request): void
    {
        // Set flash message
        switch ($status) {
            case SocialAuthMiddleware::AUTH_STATUS_SUCCESS:
                $request->getFlash()->error('You are now logged in.');
                break;

            // Auth through provider failed. Details will be logged in
            // `error.log` if `logErrors` option is set to `true`.
            case SocialAuthMiddleware::AUTH_STATUS_PROVIDER_FAILURE:

            // Table finder failed to return user record. An e.g. of this is a
            // user has been authenticated through provider but your finder has
            // a condition to not return an inactivated user.
            case SocialAuthMiddleware::AUTH_STATUS_FINDER_FAILURE:
                $request->getFlash()->error('Authentication failed.');
                break;

            case SocialAuthMiddleware::AUTH_STATUS_IDENTITY_MISMATCH:
                $request->getFlash()->error('The social profile is already linked to another user.');
                break;
        }

        // You can return a modified redirect URL if needed.
    }

    public function createUser(EventInterface $event, EntityInterface $profile, Session $session): EntityInterface
    {
        // Create and save entity for new user as shown in "createUser()" method above

        return $user;
    }
}
```

Attach the listener in your `Application` class:

```php
// src/Application.php
use App\Event\SocialAuthListener;
use Cake\Event\EventManager;

// In Application::bootstrap() or Application::middleware()
EventManager::instance()->on(new SocialAuthListener());
```

### Extend with custom providers

In order to enable custom providers (those not pre-included with `SocialConnect/Auth`)
you can extend the middleware configuration with `collectionFactory` and passing in
your own instance of `SocialConnect\Auth\CollectionFactory`.

For e.g. create your custom provider at `src/Authenticator/MyProvider.php`.
Check the providers in `vendor/socialconnect/auth/src/(OAuth1|OAuth2|OpenIDConnect)/Provider/`
for examples.

Create an instance of `CollectionFactory`.

```php
$collectionFactory = new \SocialConnect\Auth\CollectionFactory();
$collectionFactory->register(\App\Authenticator\MyProvider::NAME, \App\Authenticator\MyProvider::class);
```

Then set the factory instance in the middlware config shown above:
```
...
'collectionFactory' => $collectionFactory
...
```

Copyright
---------
Copyright 2017-Present ADmad

License
-------
[See LICENSE](LICENSE.txt)
