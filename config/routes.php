<?php
/**
 * ADmad\SocialAuth plugin.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */
use Cake\Routing\Router;

Router::plugin(
    'ADmad/SocialAuth',
    ['path' => '/social-auth'],
    function ($routes) {
        $routes->connect(
            '/login/:provider',
            ['controller' => 'Auth', 'action' => 'login'],
            ['pass' => ['provider']]
        );
        $routes->connect(
            '/callback/:provider',
            ['controller' => 'Auth', 'action' => 'callback'],
            ['pass' => ['provider']]
        );
        $routes->connect(
            '/callback',
            ['controller' => 'Auth', 'action' => 'callback']
        );
    }
);
