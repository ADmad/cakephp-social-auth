CakePHP SocialAuth Plugin
=========================

[![Total Downloads](https://img.shields.io/packagist/dt/ADmad/cakephp-social-auth.svg?style=flat-square)](https://packagist.org/packages/admad/cakephp-social-auth)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)

A CakePHP plugin which allows you authenticate using social providers like
Facebook/Google/Twitter etc. using [SocialConnect/auth](https://github.com/SocialConnect/auth)
social sign on library.

Requirements
------------

* CakePHP 3.4+.

Installation
------------

Run:

```
composer require admad/cakephp-social-auth
```

Setup
-----

Load the plugin by running following command in terminal:

```
bin/cake plugin load ADmad/SocialAuth -b -r
```

or by manually adding following line to your app's `config/bootstrap.php`:

```php
Plugin::load('ADmad/SocialAuth', ['bootstrap' => true, 'routes' => true]);
```

Database
--------

This plugin requires a migration to generate a `social_profiles` table, and it
can be generated via the official Migrations plugin as follows:

```shell
bin/cake migrations migrate -p ADmad/SocialAuth
```

Usage
-----

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
    // URL string or array to redirect to after authentication.
    'loginRedirect' => '/',
    // Boolean indicating whether user identity should be returned as entity.
    'userEntity' => false,
    // User model.
    'userModel' => 'Users',
    // Finder type.
    'finder' => 'all',
    // Fields.
    'fields' => [
        'password' => 'password',
    ],
    // Session key to which to write identity record to.
    'sessionKey' => 'Auth.User',
    // The methods in user model which should be called in case of new user.
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
                'fields' => [
                    'email',
                    // To get a full list of all posible values, refer to
                    // https://developers.facebook.com/docs/graph-api/reference/user 
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
]));
```

On your login page you can create links to initiate authentication using required
providers.

```php
echo $this->Form->postLink(
    'Login with Facebook',
    [
        'plugin' => 'ADmad/SocialAuth',
        'controller' => 'Auth',
        'action' => 'login',
        'provider' => 'facebook',
        '?' => ['redirect' => $this->request->getQuery('redirect')]
    ]
);
```

We use a POST link here instead of a normal link to prevent search bots and other
crawlers from following the link. (Adding "nofollow" attribute to link doesn't
suffice as it's often ignored by bots/crawlers.) If you prefer using GET you can
still do so by configuring the middleware with `'requestMethod' => 'GET'`.

Once a user is authenticated through the provider the middleware gets the user
profile from the identity provider and using that tries to find the corresponding
user record using the user model. If no user is found it calls the `getUser` method
of your user model. The method recieves social profile model entity as argument
and return an entity for the user. E.g.

```php
// src/Model/Table/UsersTable.php

public function getUser(\Cake\Datasource\EntityInterface $profile) {
    // Make sure here that all the required fields are actually present
    if (empty($profile->email)) {
        throw new \RuntimeException('Could not find email in social profile.');
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

In case of authentication failure user is redirected back to login URL with
`error` query string variable. It can have one of these values:

- `provider_failure`: Auth through provider failed.
- `finder_failure`: Finder failed to return user record. An e.g. of this is
  a user has been authenticated through provider but your finder has condition
  to not return inactivate user.

Copyright
---------
Copyright 2017 ADmad

License
-------
[See LICENSE](LICENSE.txt)
