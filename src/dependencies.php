<?php
// DIC configuration

$container = $app->getContainer();

// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], Monolog\Logger::DEBUG));

    return $logger;
};

// OAuth2
$container['Api\Model\General\OAuthStorage'] = function ($c) {
    return new \Api\Model\General\OAuthStorage($c->get('settings')['oauth']);
};

$container['Api\Model\General\OAuth'] = function ($c) {
    return new \Api\Model\General\OAuth($c->get('Api\Model\General\OAuthStorage'));
};

$container['Api\Middleware\OAuth'] = function ($c) {
    return new Api\Middleware\OAuth($c->get('Api\Model\General\OAuth'));
};

// Maxmind to geolocalize
$container['MaxMind\Db\Reader'] = function ($c) {
    return new \MaxMind\Db\Reader($c->get('settings')['maxmind']);
};

// BlockChain
$container['Api\Lib\BlockChain\BlockChain'] = function ($c) {
    \Api\Lib\BlockChain\BlockChain::setConf($c->get('settings')['blockchain'][ENV]);
};

// Files
$container['Api\Model\General\FilesStorage'] = function ($c) {
    $settings = $c->get('settings')['files'][ENV];

    return new \Api\Model\General\FilesStorage($settings['basePath'], $settings['baseUrl']);
};

// Database
$container['Api\Model\General\Database'] = function ($c) {
    $database = \Api\Model\General\MySQL::getInstance();

    if (!$database->isConnected()) {
        if (!isset($c->get('settings')['mysql'][ENV])) {
            return null;
        }
        $params = $c->get('settings')['mysql'][ENV];

        $database->connect($params["host"], $params["user"], $params["pass"], $params["scheme"]);
    }

    return $database;
};

// Repositories
$container['Api\Repository\Clients'] = function ($c) {
    return new Api\Repository\Clients($c->get('Api\Model\General\Database'), $c->get('MaxMind\Db\Reader'));
};

$container['Api\Repository\StoreData'] = function ($c) {
    return new Api\Repository\StoreData($c->get('Api\Model\General\Database'), $c->get('MaxMind\Db\Reader'));
};

// Models
$container['Api\Model\StoreData'] = function ($c) {
    $c->get('Api\Lib\BlockChain\BlockChain');

    return new Api\Model\StoreData($c->get('Api\Repository\StoreData'), $c->get('Api\Repository\Clients'),
        $c->get('Api\Model\General\FilesStorage'), $c->get('logger'));
};

// Controllers
$container['Api\Controller\Accounts'] = function ($c) {
    return new Api\Controller\Accounts($c->get('Api\Repository\Clients'));
};

$container['Api\Controller\Clients'] = function ($c) {
    return new Api\Controller\Clients($c->get('Api\Repository\Clients'));
};

$container['Api\Controller\StoreData'] = function ($c) {
    return new Api\Controller\StoreData($c->get('Api\Model\StoreData'));
};