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
    try {
        return new \MaxMind\Db\Reader($c->get('settings')['maxmind']);
    } catch (\Exception $e) {
        throw new \Exception('MaxMindDb not redeable', 503);
    }
};

// BlockChain
$container['Api\Lib\BlockChain\BlockChain'] = function ($c) {
    \Api\Lib\BlockChain\BlockChain::setConf($c->get('settings')['blockchain']);
};

// ScriptLauncher
$container['Api\Model\General\ScriptsLauncher'] = function ($c) {
    \Api\Model\General\ScriptsLauncher::getInstance()->setScripts($c->get('settings')['scripts']);
};

// Files
$container['Api\Model\General\FilesStorage'] = function ($c) {
    $settings = $c->get('settings')['files'];

    return new \Api\Model\General\FilesStorage($c->get('logger'), $settings['basePath'], $settings['baseUrl']);
};

// Database
$container['Api\Model\General\Database'] = function ($c) {
    $database = \Api\Model\General\MySQL::getInstance();

    if (!$database->isConnected()) {
        if (!isset($c->get('settings')['mysql'])) {
            return null;
        }
        $params = $c->get('settings')['mysql'];
        try {
            $database->connect($params["host"], $params["user"], $params["pass"], $params["scheme"]);
        } catch (\Exception $e) {
            throw new \Exception('Database not connected', 503);
        }
    }

    return $database;
};

// Emails
$container['Api\Model\Email\Email'] = function ($c) {
    $conf = $c->get('settings')['email'];

    return \Api\Model\Email\EmailManager::factory($conf['current'], $conf[$conf['current']]);
};

// Text messages
$container['Api\Model\Phone\Phone'] = function ($c) {
    $conf = $c->get('settings')['phone'];

    return \Api\Model\Phone\PhoneManager::factory($conf['current'], $conf[$conf['current']]);
};

// Repositories
$container['Api\Repository\OAuth'] = function ($c) {
    return new Api\Repository\OAuth($c->get('Api\Model\General\Database'));
};

$container['Api\Repository\General'] = function ($c) {
    return new Api\Repository\General($c->get('Api\Model\General\Database'));
};

$container['Api\Repository\Users'] = function ($c) {
    return new Api\Repository\Users($c->get('Api\Model\General\Database'), $c->get('MaxMind\Db\Reader'));
};

$container['Api\Repository\Processes'] = function ($c) {
    return new Api\Repository\Processes($c->get('Api\Model\General\Database'));
};

$container['Api\Repository\StoreData'] = function ($c) {
    return new Api\Repository\StoreData($c->get('Api\Model\General\Database'), $c->get('MaxMind\Db\Reader'));
};

$container['Api\Repository\BulkTransactions'] = function ($c) {
    return new Api\Repository\BulkTransactions($c->get('Api\Model\General\Database'), $c->get('MaxMind\Db\Reader'));
};

// Models
$container['Api\Model\Accounts'] = function ($c) {
    return new Api\Model\Accounts($c->get('Api\Repository\Users'), $c->get('logger'), $c->get('Api\Model\Email\Email'),
        $c->get('view'), $c->get('settings')['front_urls']);
};

$container['Api\Model\BulkTransactions'] = function ($c) {
    $c->get('Api\Lib\BlockChain\BlockChain');

    return new Api\Model\BulkTransactions($c->get('Api\Repository\BulkTransactions'),
        $c->get('Api\Repository\StoreData'), $c->get('Api\Model\General\FilesStorage'), $c->get('logger'));
};

$container['Api\Model\StoreData'] = function ($c) {
    $c->get('Api\Lib\BlockChain\BlockChain');
    $c->get('Api\Model\General\ScriptsLauncher');

    return new Api\Model\StoreData($c->get('Api\Repository\StoreData'), $c->get('Api\Repository\Users'),
        $c->get('Api\Repository\OAuth'), $c->get('Api\Repository\Processes'), $c->get('Api\Model\BulkTransactions'),
        $c->get('Api\Model\General\FilesStorage'), $c->get('logger'), $c->get('Api\Model\Email\Email'),
        $c->get('Api\Model\Phone\Phone'), $c->get('view'), $c->get('settings')['front_urls']);
};

$container['Api\Model\Callback\CallbackCaller'] = function ($c) {
    return new Api\Model\Callback\CallbackCaller($c->get('Api\Repository\BulkTransactions'),
        $c->get('Api\Repository\StoreData'), $c->get('Api\Model\StoreData'), $c->get('Api\Model\Email\Email'),
        $c->get('Api\Model\General\FilesStorage'), $c->get('view'), $c->get('logger'),
        $c->get('settings')['front_urls']);
};

$container['Api\Model\System'] = function ($c) {
    $c->get('Api\Lib\BlockChain\BlockChain');

    return new Api\Model\System($c->get('Api\Repository\BulkTransactions'), $c->get('Api\Repository\StoreData'),
        $c->get('Api\Repository\Processes'), $c->get('Api\Model\Email\Email'), $c->get('view'), $c->get('logger'),
        $c->get('Api\Model\Callback\CallbackCaller'));
};

// Controllers
$container['Api\Controller\OAuth'] = function ($c) {
    return new Api\Controller\OAuth($c->get('Api\Repository\OAuth'));
};

$container['Api\Controller\General'] = function ($c) {
    return new Api\Controller\General($c->get('Api\Repository\General'), $c->get('MaxMind\Db\Reader'));
};

$container['Api\Controller\Processes'] = function ($c) {
    return new Api\Controller\Processes($c->get('Api\Repository\Processes'));
};

$container['Api\Controller\Accounts'] = function ($c) {
    return new Api\Controller\Accounts($c->get('Api\Model\Accounts'));
};

$container['Api\Controller\Users'] = function ($c) {
    return new Api\Controller\Users($c->get('Api\Repository\Users'));
};

$container['Api\Controller\StoreData'] = function ($c) {
    return new Api\Controller\StoreData($c->get('Api\Model\StoreData'));
};

$container['Api\Controller\BulkTransactions'] = function ($c) {
    return new Api\Controller\BulkTransactions($c->get('Api\Model\BulkTransactions'));
};

$container['Api\Controller\System'] = function ($c) {
    return new Api\Controller\System($c->get('Api\Model\System'));
};