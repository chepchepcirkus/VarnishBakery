<?php

use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Event\EventManager;
use VarnishBakery\Model\Listener;

// Set Listener
$listener = new Listener();
EventManager::instance()->on($listener);

// Set Configuration
Configure::write('varnish_bakery', [
        'vcl' => [
            'backend_host' => '127.0.0.1',
            'backend_port' => '8080',
            'vcl_template' => $path = Plugin::path('VarnishBakery') . 'vcl/dummy-4.0.2.vcl',
            'debug_mode' => "true"
        ],
        'varnish' => [
            'host' => '127.0.0.1',
            'port' => '6082',
            'secret' => "YOUR_SECRET"
        ],
        'no_cache_routes' => [
            'varnish-bakery/*'
        ]
]);

