<?php

return [
    'settings' => [
        'displayErrorDetails' => true,

        // Monolog settings
        'logger'              => [
            'name' => 'slim-app',
            'path' => __DIR__ . '/../var/logs/app.log',
        ],

        // Templates settings
        'twig'                => [
            'path'  => __DIR__ . '/Templates',
            'cache' => __DIR__ . '/../var/cache',
        ],

        // Storage for private OAuth apps
        'oauth'               => [
            '680ef7583f177ef707ebd0800de41c13f86270ed92948442a91c7e21cf5a0bbe' => [
                'grantType' => 'client_credentials',
                'appId'     => '1',
                'appName'   => 'front',
                'appRole'   => 'all'
            ],
            'd5f14b4a435a5ef685bbaedbdd49de9fa7bd728344451113b15e9b0fd29e183a' => [
                'grantType' => 'client_credentials',
                'appId'     => '2',
                'appName'   => 'mobile app',
                'appRole'   => 'all'
            ],
            'ce42585fd8ab9272455bca6db8ef84eeef8989ab70c21529125c944ee1c93bed' => [
                'grantType' => 'client_credentials',
                'appId'     => '3',
                'appName'   => 'system',
                'appRole'   => 'system'
            ]
        ],

        // Files
        'files'               => [
            'basePath' => '/var/www/files',
            'baseUrl'  => '/files'
        ],

        // MySQL
        'mysql'               => [
            'host'   => 'mysql.bindeo.com',
            'user'   => 'API',
            'pass'   => 'a1607b03e86453ebaf35bec81b4194ae',
            'scheme' => 'API'
        ],

        // Email configurations
        'email'               => [
            'current' => 'mailgun',
            'mailgun' => [
                'domain' => 'mg.bindeo.com',
                'key'    => 'key-7068eebbe6808e44b7816aa7b88ba21a',
                'from'   => 'mail@bindeo.com'
            ]
        ],

        // Maxmind - http://geolite.maxmind.com/download/geoip/database/GeoLite2-City.mmdb.gz
        'maxmind'             => '/usr/local/share/geoIP/GeoLite2-City.mmdb',

        // BlockChain
        'blockchain'          => [
            'bitcoin' => [
                'host' => 'bitcoin.bindeo.com',
                //'port' => 8332, // Real net
                'port' => 18332, // Test net
                'user' => '5658a02023c98f90ade9974a7504c945d46db41011001a19d4ec4d3e96190423'
            ]
        ],

        // Host and front urls for emails
        'front_urls'          => [
            'host'             => 'https://',
            'login'            => 'www.bindeo.com/login',
            'validation_token' => 'www.bindeo.com/user/validate',
            'review_contract'  => 'www.bindeo.com/contracts/review'
        ],

        // Scripts path
        'scripts'             => [
            'status' => 'enabled',
            'path'   => '/var/www/html/bindeo/private_api/scripts'
        ]
    ]
];