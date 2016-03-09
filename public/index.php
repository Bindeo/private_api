<?php

if (PHP_SAPI == 'cli-server') {
    // To help the built-in PHP dev server, check if the request was actually for
    // something which should probably be served as a static file
    $file = __DIR__ . $_SERVER['REQUEST_URI'];
    if (is_file($file)) {
        return false;
    }
}

// Define application environment
defined('ENV') || define('ENV', (getenv('ENV') ? getenv('ENV') : 'production'));
if (ENV == "development") {
    if (getenv('DEVELOPER')) {
        define("DEVELOPER", getenv('DEVELOPER'));
    } else {
        define("DEVELOPER", current(explode('@', getenv('SERVER_ADMIN'))));
    }
}

require __DIR__ . '/../vendor/autoload.php';

session_start();

// Instantiate the app
$settings = require __DIR__ . '/../src/settings.php';

if (ENV == "development") {
    require __DIR__ . '/../src/settings_dev.php';
}

$app = new \Slim\App($settings);

// Set up dependencies
require __DIR__ . '/../src/dependencies.php';

// Register middleware
require __DIR__ . '/../src/middleware.php';

// Register routes
require __DIR__ . '/../src/routes.php';

// Rewrite error handlers
require __DIR__ . '/../src/errors.php';

// Run app
$app->run();
