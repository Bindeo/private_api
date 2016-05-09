<?php
/*
 * Config file for DEVELOPMENT, copy this file to settings_dev.php and overwrite all that you need
 */
// Files paths
$settings['settings']['files'] = [
    'basePath' => '/var/www/files',
    'baseUrl'  => '/files'
];

// MySQL development connection data
$settings['settings']['mysql'] = [
    'host'   => 'localhost',
    'user'   => 'API',
    'pass'   => 'dd9b15947a493f7ce7067a41f8a3edd1',
    'scheme' => 'API'
];

// Blockchain development connection data
$settings['settings']['blockchain'] = [
    'bitcoin' => [
        'host' => '127.0.0.1',
        'port' => 18332,
        'user' => '8JXHicdG9N2aaTWatmmneiZxQYSjFtEkeLzxyHTSwYuf'
    ]
];

// Mailgun sandbox info
$settings['settings']['email']['mailgun'] = [
    'domain' => 'sandbox31d6f38203e342f9af992a85c6dad951.mailgun.org',
    'key'    => 'key-7068eebbe6808e44b7816aa7b88ba21a',
    'from'   => 'mail@bindeo.com'
];

// MessageBird text messages sender
$settings['settings']['phone']['messagebird'] = [
    'key'    => 'test_T7cyZseVx71msE2gMcHbk00gs'
];

// Maxmind db route
$settings['settings']['maxmind'] = '/usr/local/share/geoIP/GeoLite2-City.mmdb';

// Host and front urls for emails
$settings['settings']['front_urls']['host'] = 'https://';

// Scripts path
$settings['scripts']['path'] = 'export HOME=/tmp && /var/www/html/bindeo/private_api/scripts';
// Scripts status, set to disabled to avoid script launching, ie you are in windows environment
$settings['scripts']['status'] = 'enabled';