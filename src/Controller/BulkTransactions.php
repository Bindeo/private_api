<?php

namespace Api\Controller;

use Api\Entity\BulkEvent;
use Api\Entity\BulkFile;
use Api\Entity\BulkTransaction;
use Api\Entity\BulkType;
use Bindeo\DataModel\Exceptions;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

class BulkTransactions
{
    /**
     * @var \Api\Model\BulkTransactions
     */
    private $model;

    public function __construct(\Api\Model\BulkTransactions $model)
    {
        $this->model = $model;
    }

    /**
     * Get the bulk type requested
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function bulkType(Request $request, Response $response, $args)
    {
        $data = $this->model->bulkType(new BulkType($request->getParams()));

        $res = ['data' => ['type' => 'bulk_types', 'attributes' => $data]];

        return $response->withJson($res, 200);
    }

    /**
     * Get the bulk types list of a client
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function bulkTypes(Request $request, Response $response, $args)
    {
        $data = $this->model->bulkTypes(new BulkType($request->getParams()));

        $res = ['data' => $data->toArray('bulk_types'), 'total_pages' => 1];

        return $response->withJson($res, 200);
    }

    /**
     * Create or open new bulk transaction
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function createBulk(Request $request, Response $response, $args)
    {
        $mode = $request->getParam('mode');
        if ($mode == 'open') {
            return $this->openBulk($request, $response, $args);
        } elseif ($mode == 'create') {
            return $this->oneStepBulk($request, $response, $args);
        } else {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }
    }

    /**
     * Open a new bulk transaction
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    private function openBulk(Request $request, Response $response, $args)
    {
        $res = $this->model->openBulk($request->getParams());
        $res = ['data' => ['type' => 'bulk_transactions', 'attributes' => $res]];

        return $response->withJson($res, 201);
    }

    /**
     * Create a new bulk transaction in one step
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    private function oneStepBulk(Request $request, Response $response, $args)
    {
        $res = $this->model->oneStepBulk($request->getParams());
        $res = ['data' => ['type' => 'bulk_transactions', 'attributes' => $res]];

        return $response->withJson($res, 201);
    }

    /**
     * Close an opened bulk transaction
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function closeBulk(Request $request, Response $response, $args)
    {
        $res = $this->model->closeBulk(new BulkTransaction($request->getParams()));
        $res = ['data' => ['type' => 'bulk_transactions', 'attributes' => $res]];

        return $response->withJson($res, 200);
    }

    /**
     * Delete an opened bulk transaction
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function deleteBulk(Request $request, Response $response, $args)
    {
        $this->model->deleteBulk(new BulkTransaction($request->getParams()));

        return $response->withJson('', 204);
    }

    /**
     * Get information about bulk transaction
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function getBulk(Request $request, Response $response, $args)
    {
        $res = $this->model->getBulk(new BulkTransaction($request->getParams()));
        $res = ['data' => ['type' => 'bulk_transactions', 'attributes' => $res ? $res->toArray() : []]];

        return $response->withJson($res, 200);
    }

    /**
     * Add an item to an opened bulk transaction
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function addItem(Request $request, Response $response, $args)
    {
        $mode = $request->getParam('type');
        if ($mode == 'event') {
            return $this->addEvent($request, $response);
        } elseif ($mode == 'file') {
            return $this->oneStepBulk($request, $response);
        } else {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }
    }

    /**
     * Add new item to an opened bulk transaction
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    private function addEvent(Request $request, Response $response)
    {
        $res = $this->model->addEvent(new BulkEvent($request->getParams()))->toArray();
        $res = ['data' => ['type' => 'bulk_transactions', 'attributes' => $res]];

        return $response->withJson($res, 201);
    }

    /**
     * Verify file integrity
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function verifyFile(Request $request, Response $response, $args)
    {
        $res = $this->model->verifyFile(new BulkFile($request->getParams()));

        $res = ['data' => ['type' => 'bulk_files', 'attributes' => $res]];

        return $response->withJson($res, 201);
    }
}