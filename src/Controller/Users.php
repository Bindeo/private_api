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
    private $usersRepo;

    public function __construct(RepositoryAbstract $users)
    {
        $this->usersRepo = $users;
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

        if ($user->getIdUser()) {
            // Get the user by id
            $user = $this->usersRepo->find($user);
        } elseif ($user->getEmail()) {
            // Get the user by email
            $user = $this->usersRepo->findEmail($user);
        } else {
            $user = null;
        }

        $res = ['data' => ['type' => 'users', 'attributes' => $user ? $user->toArray() : []]];

        return $response->withJson($res, 200);
    }
}