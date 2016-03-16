<?php

namespace Api\Controller;

use Api\Entity\BulkTransaction;
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
     * Create a new bulk transaction
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
        // New bulk transaction
        $bulk = new BulkTransaction($request->getParams());
        $bulk->transformFiles();

        $res = $this->model->createBulk($bulk);
        $res = ['data' => ['type' => 'blockchain', 'attributes' => $res]];

        return $response->withJson($res, 201);
    }
}