<?php

namespace Api\Controller;

use Api\Entity\UserIdentity;
use Bindeo\DataModel\Exceptions;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Api\Entity\User;

class Accounts
{
    /**
     * @var \Api\Model\Accounts
     */
    private $model;

    public function __construct(\Api\Model\Accounts $model)
    {
        $this->model = $model;
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
        $data = $this->model->login(new User($request->getParams()));
        $res = ['data' => ['type' => 'users', 'attributes' => $data]];

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
        $data = $this->model->create(new User($request->getParams()));

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
        $user = $this->model->modify(new User($request->getParams()));

        $res = ['data' => ['type' => 'users', 'attributes' => $user]];

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
        $user = $this->model->modifyPassword(new User($request->getParams()));

        $res = ['data' => ['type' => 'users', 'attributes' => $user]];

        return $response->withJson($res, 200);
    }

    /**
     * Reset an account password
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     */
    public function resetPassword(Request $request, Response $response, $args)
    {
        // Populate the user object and modify the password
        $this->model->resetPassword(new User($request->getParams()));

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
        $user = $this->model->modifyType(new User($request->getParams()));

        $res = ['data' => ['type' => 'users', 'attributes' => $user]];

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
        $this->model->modifyEmail(new User($request->getParams()));

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
        $user = $this->model->validateToken($request->getParam('token'), $request->getParam('ip'),
            $request->getParam('password'));

        $res = ['data' => ['type' => 'users', 'attributes' => $user]];

        return $response->withJson($res, 200);
    }

    /**
     * Resend the initial validation token
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function resendToken(Request $request, Response $response, $args)
    {
        // Resend the token
        $this->model->resendToken(new User($request->getParams()));

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
        $this->model->delete(new User($request->getParams()));

        return $response->withJson('', 204);
    }

    /**
     * Get active identities of the user
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function getIdentities(Request $request, Response $response, $args)
    {
        $data = $this->model->getIdentities(new User($request->getParams()));

        $res = ['data' => $data->toArray('user_identity'), 'total_pages' => 1];

        return $response->withJson($res, 200);
    }

    /**
     * Modify or create an identity
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function saveIdentity(Request $request, Response $response, $args)
    {
        // Populate the user identity object and save it
        $userIdentity = $this->model->saveIdentity(new UserIdentity($request->getParams()));

        $res = ['data' => ['type' => 'UserIdentity', 'attributes' => $userIdentity]];

        return $response->withJson($res, 200);
    }
}