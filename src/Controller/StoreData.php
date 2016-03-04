<?php

namespace Api\Controller;

use Api\Entity\BlockChain;
use Api\Entity\Email;
use Api\Entity\File;
use Bindeo\DataModel\Exceptions;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

class StoreData
{
    /**
     * @var \Api\Model\StoreData
     */
    private $model;

    public function __construct(\Api\Model\StoreData $model)
    {
        $this->model = $model;
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
        $res = $this->model->getFile(new File($request->getParams()));
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
        $res = $this->model->saveFile(new File($request->getParams()));
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
        $this->model->deleteFile(new File($request->getParams()));

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
        $res = $this->model->fileList($request->getParam('id_client'), $request->getParam('page'));
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
        $res = $this->model->signFile(new File($request->getParams()));

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
            $res = $this->model->getBCBalance();
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
        $res = $this->model->getTransaction(new BlockChain($request->getParams()));
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
    public function getTransactionInfo(Request $request, Response $response, $args)
    {
        if ($request->getParam('mode') == 'hash') {
            $type = 'hash';
            $res = $this->model->getTransactionHash(new BlockChain($request->getParams()));
        } elseif ($request->getParam('mode') == 'extended') {
            $type = 'transaction';
            $res = $this->model->getTransactionExtended(new BlockChain($request->getParams()));
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
        $res = $this->model->testFile($file->file, $request->getParam('net'), $request->getParam('transaction'));
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
        $res = $this->model->testAsset($asset);
        $res = ['data' => ['type' => 'hash_test', 'attributes' => $res]];

        return $response->withJson($res, 200);
    }

    /**
     * Development blockchain tests
     *
     * @param Request  $request
     * @param Response $response
     * @param          $args
     */
    public function tests(Request $request, Response $response, $args)
    {
        $blockchain = \Api\Lib\BlockChain\BlockChain::getInstance();

        $res = $blockchain->getRawTransaction('59f91fd845430e8b8b13e7325e23f8c3b866cae1db78b3a427f94a1c1a0fed0b', 1);
        $res1 = $blockchain->getRawTransaction('7c2cb65d25e67a1b2e9221db756c01434476ebf8e5d9d0b63140a77ea38abbc5', 1);
        echo '<pre>';
        print_r($res);
        print_r($res1);
        exit;
    }
}