<?php

namespace Api\Controller;

use Api\Model\General\Exceptions;
use Api\Model\General\OAuthRegistry;
use Api\Repository\RepositoryAbstract;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Api\Entity\User;

class Accounts
{
    /**
     * @var \Api\Repository\Users
     */
    private $usersRepo;

    public function __construct(RepositoryAbstract $users)
    {
        $this->usersRepo = $users;
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
        // Populate the user object and login the user
        $data = $this->usersRepo->login(new User($request->getParams()), OAuthRegistry::getInstance()->getAppName());
        $res = ['data' => ['type' => 'users', 'attributes' => $data->toArray()]];

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
        // Populate the user object and create a new account
        $data = $this->usersRepo->create(new User($request->getParams()));

        $res = ['data' => ['type' => 'users', 'attributes' => $data]];

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
        // Populate the user object and modify the account
        $user = $this->usersRepo->modify(new User($request->getParams()));

        $res = ['data' => ['type' => 'users', 'attributes' => $user ? $user->toArray() : null]];

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
        // Populate the user object and modify the password
        $this->usersRepo->modifyPassword(new User($request->getParams()));

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
        // Populate the user object and change his account type
        $user = $this->usersRepo->modifyType(new User($request->getParams()));

        $res = ['data' => ['type' => 'users', 'attributes' => $user ? $user->toArray() : null]];

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
        // Populate the user object and modify the email
        $this->usersRepo->modifyEmail(new User($request->getParams()));

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
        $this->usersRepo->validateToken($request->getParam('token'), $request->getParam('ip'),
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
        // Populate the user object and delete an account
        $this->usersRepo->delete(new User($request->getParams()));

        return $response->withJson('', 204);
    }
}