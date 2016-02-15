<?php
// Routes

// Accounts routes
$app->group('/account', function () {
    $this->get('', 'Api\Controller\Accounts:login');
    $this->post('', 'Api\Controller\Accounts:create');
    $this->patch('', 'Api\Controller\Accounts:modify');
    $this->delete('', 'Api\Controller\Accounts:delete');
    $this->patch('/password', 'Api\Controller\Accounts:modifyPassword');
    $this->patch('/email', 'Api\Controller\Accounts:modifyEmail');
    $this->patch('/type', 'Api\Controller\Accounts:changeType');
    $this->put('/token', 'Api\Controller\Accounts:validateToken');
});

// Users routes
$app->group('/users', function () {
    $this->get('', 'Api\Controller\Users:get');
});

// Data storage routes
$app->group('/data', function () {
    $this->get('/file', 'Api\Controller\StoreData:getFile');
    $this->post('/file', 'Api\Controller\StoreData:createFile');
    $this->delete('/file', 'Api\Controller\StoreData:deleteFile');
    $this->get('/files', 'Api\Controller\StoreData:fileList');
    $this->put('/blockchain', 'Api\Controller\StoreData:signFile');
    $this->get('/blockchain', 'Api\Controller\StoreData:getTransaction');
    $this->get('/blockchain/coins', 'Api\Controller\StoreData:getBalance');
    $this->get('/blockchain/info', 'Api\Controller\StoreData:getTransactionInfo');
    $this->post('/blockchain/test', 'Api\Controller\StoreData:testFile');
});