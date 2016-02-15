<?php

namespace Api\Controller;

use Api\Entity\User;
use Api\Repository\RepositoryAbstract;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

class Users
{
    /**
     * @var \Api\Repository\Users
     */
    private $_usersRepo;

    public function __construct(RepositoryAbstract $users)
    {
        $this->_usersRepo = $users;
    }

    /**
     * Get a client account by id
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function get(Request $request, Response $response, $args)
    {
        // Populate de user object
        $user = new User($request->getParams());

        // Get the user
        $user = $this->_usersRepo->find($user);
        $res = ['data' => ['type' => 'users', 'attributes' => $user ? $user->toArray() : []]];

        return $response->withJson($res, 200);
    }
}