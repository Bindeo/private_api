<?php

namespace Api\Controller;

use Api\Model\General\OAuthRegistry;
use Bindeo\DataModel\Exceptions;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

class System
{
    /**
     * @var \Api\Model\System
     */
    private $model;

    public function __construct(\Api\Model\System $model)
    {
        $this->model = $model;
    }

    /**
     * Check pending blockchain transaction looking for confirmations
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function blockchainConfirmations(Request $request, Response $response, $args)
    {
        // Check grants
        if (OAuthRegistry::getInstance()->getAppRole() != 'system') {
            throw new \Exception(Exceptions::UNAUTHORIZED, 401);
        }

        // Execute task
        $this->model->blockchainConfirmations($request->getParam('net', 'bitcoin'));

        return $response->withJson('', 204);
    }

    /**
     * Transfer coins between accounts
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @throws \Exception
     */
    public function transferCoins(Request $request, Response $response, $args)
    {
        // Check grants
        if (OAuthRegistry::getInstance()->getAppRole() != 'system') {
            throw new \Exception(Exceptions::UNAUTHORIZED, 401);
        }

        if (!$request->getParam('accountTo')) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Transfer coins
        $this->model->transferCoins($request->getParam('net', 'bitcoin'), $request->getParam('amount', 0),
            $request->getParam('accountTo'), $request->getParam('number', 1), $request->getParam('accountFrom', ''));
    }
}