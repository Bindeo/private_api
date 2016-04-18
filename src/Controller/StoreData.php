<?php

namespace Api\Controller;

use Api\Entity\BlockChain;
use Api\Entity\Email;
use Api\Entity\File;
use Bindeo\DataModel\Exceptions;
use Bindeo\Filter\FilesFilter;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

class StoreData
{
    /**
     * @var \Api\Model\StoreData
     */
    private $modelData;

    public function __construct(\Api\Model\StoreData $model)
    {
        $this->modelData = $model;
    }

    /**
     * Get a file by id
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function getFile(Request $request, Response $response, $args)
    {
        // Get the file
        $res = $this->modelData->getFile(new File($request->getParams()));
        $res = ['data' => ['type' => 'files', 'attributes' => $res]];

        return $response->withJson($res, 200);
    }

    /**
     * Save a new file
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     */
    public function createFile(Request $request, Response $response, $args)
    {
        // Save file
        $res = $this->modelData->saveFile(new File($request->getParams()));
        $res = ['data' => ['type' => 'files', 'attributes' => $res]];

        return $response->withJson($res, 201);
    }

    /**
     * Delete a file if it hasn't been signed or mark it as deleted if it has
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     */
    public function deleteFile(Request $request, Response $response, $args)
    {
        // Delete an account
        $this->modelData->deleteFile(new File($request->getParams()));

        return $response->withJson('', 204);
    }

    /**
     * Get a paginated list of files from one client
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function fileList(Request $request, Response $response, $args)
    {
        // Get the list
        $res = $this->modelData->fileList(new FilesFilter($request->getParams()));
        $res = [
            'data'         => $res->toArray('files'),
            'total_pages'  => $res->getNumPages(),
            'current_page' => $request->getParam('page')
        ];

        return $response->withJson($res, 200);
    }

    /**
     * Sign a file into the blockchain
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function signFile(Request $request, Response $response, $args)
    {
        // Sign the file
        $res = $this->modelData->signFile(new File($request->getParams()));

        $res = ['data' => ['type' => 'blockchain', 'attributes' => $res]];

        return $response->withJson($res, 202);
    }

    /**
     * Return the current bitcoins balance
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function getBalance(Request $request, Response $response, $args)
    {
        if ($request->getParam('net') == 'bitcoin') {
            $res = $this->modelData->getBCBalance();
        } else {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        return $response->withJson(['data' => ['type' => 'float', 'attributes' => $res]], 200);
    }

    /**
     * Get a blockchain transaction by id
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function getTransaction(Request $request, Response $response, $args)
    {
        // Get the transaction
        $res = $this->modelData->getTransaction(new BlockChain($request->getParams()), $request->getParam('mode', 'light'));
        $res = ['data' => ['type' => 'blockchain', 'attributes' => $res]];

        return $response->withJson($res, 200);
    }

    /**
     * Get a blockchain transaction by id from blockchain
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function getOnlineTransaction(Request $request, Response $response, $args)
    {
        if ($request->getParam('mode') == 'hash') {
            $type = 'hash';
            $res = $this->modelData->getTransactionHash(new BlockChain($request->getParams()));
        } elseif ($request->getParam('mode') == 'info') {
            $type = 'transaction';
            $res = $this->modelData->getTransactionInfo(new BlockChain($request->getParams()));
        } elseif ($request->getParam('mode') == 'extended') {
            $type = 'transaction';
            $res = $this->modelData->getTransactionExtended(new BlockChain($request->getParams()));
        } else {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        return $response->withJson(['data' => ['type' => $type, 'attributes' => $res]], 200);
    }

    /**
     * Test a file against a recorded blockchain transaction
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function testFile(Request $request, Response $response, $args)
    {
        // Get the uploaded file
        $files = $request->getUploadedFiles();
        $file = reset($files);

        if (!$file or !$request->getParam('transaction') or !$request->getParam('net')) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Get the result
        $res = $this->modelData->testFile($file->file, $request->getParam('net'), $request->getParam('transaction'));
        $res = ['data' => ['type' => 'hash_test', 'attributes' => $res]];

        return $response->withJson($res, 200);
    }

    /**
     * Test an asset against the blockchain
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function testAsset(Request $request, Response $response, $args)
    {
        // Get the result
        if ($request->getParam('type') == 'F') {
            $asset = new File($request->getParams());
        } else {
            $asset = new Email($request->getParams());
        }
        $res = $this->modelData->testAsset($asset);
        $res = ['data' => ['type' => 'hash_test', 'attributes' => $res]];

        return $response->withJson($res, 200);
    }

    /**
     * Post data into blockchain
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     */
    public function postBlockchainData(Request $request, Response $response, $args)
    {
        $res = $this->modelData->postBlockchainData($request->getParam('data'));

        return $response->withJson(['array' => $res], 201);
    }

    /**
     * Get data from blockchain
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     */
    public function getBlockchainData(Request $request, Response $response, $args)
    {
        $res = $this->modelData->getBlockchainData($request->getParam('mode'), $request->getParam('txid'));

        return $response->withJson(['array' => $res], 200);
    }

    /**
     * Development blockchain tests
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     */
    public function tests(Request $request, Response $response, $args)
    {
        $blockchain = \Api\Lib\BlockChain\BlockChain::getInstance();

        $res = $blockchain->tests();
        print_r($res);

        exit;
    }
}