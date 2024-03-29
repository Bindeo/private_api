<?php
// Routes

// General data routes
$app->group('/general', function () {
    $this->get('/account-types', 'Api\Controller\General:accountTypes');
    $this->get('/media-types', 'Api\Controller\General:mediaTypes');
    $this->get('/geolocalize', 'Api\Controller\General:geolocalize');
    $this->get('/validate/phone', 'Api\Controller\StoreData:validateMobilePhone');
});

// OAuth routes
$app->group('/oauth', function () {
    $this->get('/clients', 'Api\Controller\OAuth:oauthClient');
    $this->post('/token', 'Api\Controller\OAuth:saveToken');
    $this->delete('/token', 'Api\Controller\OAuth:expireToken');
    $this->get('/token', 'Api\Controller\OAuth:getToken');
});

// Accounts routes
$app->group('/account', function () {
    $this->get('', 'Api\Controller\Accounts:login');
    $this->post('', 'Api\Controller\Accounts:create');
    $this->put('', 'Api\Controller\Accounts:modify');
    $this->delete('', 'Api\Controller\Accounts:delete');
    $this->get('/password', 'Api\Controller\Accounts:resetPassword');
    $this->put('/password', 'Api\Controller\Accounts:modifyPassword');
    $this->put('/type', 'Api\Controller\Accounts:changeType');
    $this->get('/token', 'Api\Controller\Accounts:resendToken');
    $this->put('/token', 'Api\Controller\Accounts:validateToken');
    $this->get('/identities', 'Api\Controller\Accounts:getIdentities');
    $this->put('/identities', 'Api\Controller\Accounts:saveIdentity');
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
    $this->get('/blockchain/info', 'Api\Controller\StoreData:getOnlineTransaction');
    $this->get('/blockchain/test', 'Api\Controller\StoreData:testAsset');
    $this->get('/processes-status', 'Api\Controller\Processes:processesStatus');
    $this->get('/processes', 'Api\Controller\Processes:processesList');
    $this->get('/certificate', 'Api\Controller\StoreData:notarizationCertificate');
});

// Bulk transaction routes
$app->group('/bulk', function () {
    $this->post('', 'Api\Controller\BulkTransactions:createBulk');
    $this->get('/verify', 'Api\Controller\BulkTransactions:verifyFile');
    $this->get('/type', 'Api\Controller\BulkTransactions:bulkType');
    $this->get('/types', 'Api\Controller\BulkTransactions:bulkTypes');
    $this->put('', 'Api\Controller\BulkTransactions:closeBulk');
    $this->delete('', 'Api\Controller\BulkTransactions:deleteBulk');
    $this->post('/item', 'Api\Controller\BulkTransactions:addItem');
    $this->get('', 'Api\Controller\BulkTransactions:getBulk');
});

// Signature routes
$app->group('/signature', function () {
    $this->get('/certificate', 'Api\Controller\StoreData:signatureCertificate');
    $this->get('', 'Api\Controller\StoreData:getDocSignable');
    $this->get('/signer', 'Api\Controller\StoreData:getSigner');
    $this->put('', 'Api\Controller\StoreData:signDocument');
    $this->get('/code', 'Api\Controller\StoreData:getSignCode');
});

// System tasks
$app->group('/system', function () {
    $this->get('/blockchain/confirmations', 'Api\Controller\System:blockchainConfirmations');
    $this->post('/blockchain/transfer', 'Api\Controller\System:transferCoins');
});

// Direct access to blockchain
$app->group('/advanced/blockchain', function () {
    $this->post('', 'Api\Controller\StoreData:postBlockchainData');
    $this->get('', 'Api\Controller\StoreData:getBlockchainData');
});

//$app->get('/tests', 'Api\Controller\StoreData:tests');