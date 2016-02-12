<?php

namespace Api\Controller;

use Api\Entity\Client;
use Api\Repository\RepositoryAbstract;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

class Clients
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
        // Populate de client object
        $client = new Client($request->getParams());

        // Get the user
        $user = $this->_clientsRepo->find($client);
        $res = ['data' => ['type' => 'clients', 'attributes' => $user ? $user->toArray() : []]];

        return $response->withJson($res, 200);
    }
}