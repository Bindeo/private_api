<?php

namespace Api\Controller;

use Api\Model\General\Exceptions;
use Api\Repository\RepositoryAbstract;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Api\Entity\Client;

class Accounts
{
    /**
     * @var \Api\Repository\Clients
     */
    private $_clientsRepo;

    public function __construct(RepositoryAbstract $clients)
    {
        $this->_clientsRepo = $clients;
    }

    /**
     * Login the user
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function login(Request $request, Response $response, $args)
    {
        // Populate the client object and login the client
        $data = $this->_clientsRepo->login(new Client($request->getParams()));
        $res = ['data' => ['type' => 'clients', 'attributes' => $data->toArray()]];

        return $response->withJson($res, 200);
    }

    /**
     * Create a new account
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     */
    public function create(Request $request, Response $response, $args)
    {
        // Populate the client object and create a new account
        $data = $this->_clientsRepo->create(new Client($request->getParams()));

        $res = ['data' => ['type' => 'clients', 'attributes' => $data]];

        return $response->withJson($res, 201);
    }

    /**
     * Modify an account
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     */
    public function modify(Request $request, Response $response, $args)
    {
        // Populate the client object and modify the account
        $user = $this->_clientsRepo->modify(new Client($request->getParams()));

        $res = ['data' => ['type' => 'clients', 'attributes' => $user ? $user->toArray() : null]];

        return $response->withJson($res, 200);
    }

    /**
     * Modify an account password
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     */
    public function modifyPassword(Request $request, Response $response, $args)
    {
        // Populate the client object and modify the password
        $this->_clientsRepo->modifyPassword(new Client($request->getParams()));

        return $response->withJson('', 204);
    }

    /**
     * Modify an account type
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     */
    public function changeType(Request $request, Response $response, $args)
    {
        // Populate the client object and change his account type
        $user = $this->_clientsRepo->changeType(new Client($request->getParams()));

        $res = ['data' => ['type' => 'clients', 'attributes' => $user ? $user->toArray() : null]];

        return $response->withJson($res, 200);
    }

    /**
     * Modify an account email
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     */
    public function modifyEmail(Request $request, Response $response, $args)
    {
        // Populate the client object and modify the email
        $this->_clientsRepo->modifyEmail(new Client($request->getParams()));

        return $response->withJson('', 204);
    }

    /**
     * Validate a token
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function validateToken(Request $request, Response $response, $args)
    {
        if (!$request->getParam('token') or !$request->getParam('ip')) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Create a new account
        $this->_clientsRepo->validateToken($request->getParam('token'), $request->getParam('ip'),
            $request->getParam('password'));

        return $response->withJson('', 204);
    }

    /**
     * Delete an account
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function delete(Request $request, Response $response, $args)
    {
        // Populate the client object and delete an account
        $this->_clientsRepo->delete(new Client($request->getParams()));

        return $response->withJson('', 204);
    }
}