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

The plugin provides a `\ADmad\SocialAuth\Middleware\SocialAuthMiddleware` which handles authentication process.
You can configure the middleware in your `Application::middleware()` method as shown:

```php
$middleware->add(new \ADmad\SocialAuth\Middleware\SocialAuthMiddleware([
    // Request method type use to initiate authentication.
    'requestMethod' => 'POST',
    // URL string or array to redirect to after authentication.
    'loginRedirect' => '/',
    // Boolean indicating user identity should be returned as entity.
    'userEntity' => false,
    // User model.
    'userModel' => 'Users',
    // Finder type.
    'finder' => 'all',
    // Fields.
    'fields' => [
        'password' => 'password'
    ],
    // Session key to which to write identity record to.
    'sessionKey' => 'Auth.User',
    // The methods in user model which should be called in case of new user.
    // It should return a User entity.
    'newUserCallback' => 'newUser',
    // SocialConnect Auth service's providers config. https://github.com/SocialConnect/auth/blob/master/README.md
    'serviceConfig' => [
        'provider' => [
            'facebook' => [
                'applicationId' => '<application id>',
                'applicationSecret' => '<application secret>',
                'scope' => [
                    'email'
                ]
            ],
        ]
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
        'provider' => 'facebook'
    ]
);
```

We use a POST link here instead of a normal link to prevent search bots and other
crawlers from following the link. (Adding "nofollow" attribute to link doesn't
suffice as it's often ignored by bots/crawlers.) If you prefer using GET you can
still do so by configuring the middleware with `'requestMethod' => 'GET'`.

Once a user is authenticated through the provider the authenticator gets the user
profile from the identity provider and using that tries to find the corresponding
user record using the user model. If no user is found it calls the `newUser` method
of your user model. The method recieves social profile model entity as argument
and return an entity for the new user. E.g.

```php
// UsersTable.php

public function newUser(EntityInterface $profile) {
    // Make sure here that all the required fields are actually present
    if (empty($profile->email)) {
        throw new \RuntimeException('Could not find email in social profile.');
    }

    $user = $this->newEntity(['email' => $profile->email]);
    $user = $this->save($user);

    if (!$user) {
        throw new \RuntimeException('Unable to save new user');
    }

    return $user;
}
```

Copyright
---------
Copyright 2017 ADmad

License
-------
[See LICENSE](LICENSE.txt)
