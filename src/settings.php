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
            '75be9f5e5234406d544d84e32e1747e4' => [
                'grantType' => 'client_credentials',
                'clientId'  => '1',
                'appName'   => 'front',
                'appRole'   => 'all'
            ]
        ],

        // Files
        'files'               => [
            'development' => [
                'basePath' => '/var/www/files',
                'baseUrl'  => '/files'
            ]
        ],

        // MySQL
        'mysql'               => [
            'development' => [
                'host'   => 'localhost',
                'user'   => 'API',
                'pass'   => 'dd9b15947a493f7ce7067a41f8a3edd1',
                'scheme' => 'API'
            ]
        ],

        // Email configurations
        'email'               => [
            'current' => 'mailgun',
            'mailgun' => [
                'development' => [
                    'domain' => 'sandbox31d6f38203e342f9af992a85c6dad951.mailgun.org',
                    'key'    => 'key-7068eebbe6808e44b7816aa7b88ba21a',
                    'from'   => 'mail@bindeo.com'
                ],
                'production'  => [
                    'domain' => 'mg.bindeo.com',
                    'key'    => 'key-7068eebbe6808e44b7816aa7b88ba21a',
                    'from'   => 'mail@bindeo.com'
                ]
            ]
        ],

        // Maxmind
        'maxmind'             => '/usr/local/share/geoIP/GeoLite2-City.mmdb',

        // BlockChain
        'blockchain'          => [
            'development' => [
                'bitcoin' => [
                    'host' => '127.0.0.1',
                    'port' => 18332,
                    'user' => '8JXHicdG9N2aaTWatmmneiZxQYSjFtEkeLzxyHTSwYuf'
                ]
            ],
            'production'  => [
                'bitcoin' => [
                    'host' => '127.0.0.1',
                    'port' => 8332,
                    'user' => '8JXHicdG9N2aaTWatmmneiZxQYSjFtEkeLzxyHTSwYuf'
                ]
            ]
        ]
    ],
];
