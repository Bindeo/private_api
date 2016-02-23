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

// Templates
$container['view'] = function ($c) {
    $view = new Slim\Views\Twig($c->get('settings')['twig']['path'], [
        'cache'       => $c->get('settings')['twig']['cache'],
        'auto_reload' => (ENV == 'development')
    ]);

    //$view->addExtension(new \Slim\Views\TwigExtension($c->get('router'), $c->get('request')->getUri()));

    return $view;
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

// Emails
$container['Api\Model\Email\Email'] = function ($c) {
    $conf = $c->get('settings')['email'];

    return \Api\Model\Email\EmailManager::factory($conf['current'], $conf[$conf['current']][ENV]);
};

// Repositories
$container['Api\Repository\Users'] = function ($c) {
    return new Api\Repository\Users($c->get('Api\Model\General\Database'), $c->get('MaxMind\Db\Reader'));
};

$container['Api\Repository\StoreData'] = function ($c) {
    return new Api\Repository\StoreData($c->get('Api\Model\General\Database'), $c->get('MaxMind\Db\Reader'));
};

// Models
$container['Api\Model\Accounts'] = function ($c) {
    return new Api\Model\Accounts($c->get('Api\Repository\Users'), $c->get('logger'), $c->get('Api\Model\Email\Email'),
        $c->get('view'));
};

$container['Api\Model\StoreData'] = function ($c) {
    $c->get('Api\Lib\BlockChain\BlockChain');

    return new Api\Model\StoreData($c->get('Api\Repository\StoreData'), $c->get('Api\Repository\Users'),
        $c->get('Api\Model\General\FilesStorage'), $c->get('logger'));
};

// Controllers
$container['Api\Controller\Accounts'] = function ($c) {
    return new Api\Controller\Accounts($c->get('Api\Model\Accounts'));
};

$container['Api\Controller\Users'] = function ($c) {
    return new Api\Controller\Users($c->get('Api\Repository\Users'));
};

$container['Api\Controller\StoreData'] = function ($c) {
    return new Api\Controller\StoreData($c->get('Api\Model\StoreData'));
};