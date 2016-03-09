<?php
/*
 * Config file for DEVELOPMENT, copy this file to settings_dev.php and overwrite all that you need
 */
$settings['settings']['files']['development'] = [
    'basePath' => '/var/www/files',
    'baseUrl'  => '/files'
];
$settings['settings']['mysql']['development'] = [
    'host'   => 'localhost',
    'user'   => 'API',
    'pass'   => 'dd9b15947a493f7ce7067a41f8a3edd1',
    'scheme' => 'API'
];
$settings['settings']['blockchain']['development'] = [
    'bitcoin' => [
        'host' => '127.0.0.1',
        'port' => 18332,
        'user' => '8JXHicdG9N2aaTWatmmneiZxQYSjFtEkeLzxyHTSwYuf'
    ]
];
// http://dev.maxmind.com/geoip/geoip2/geolite2/
$settings['settings']['maxmind'] = '/usr/local/share/geoIP/GeoLite2-City.mmdb';
