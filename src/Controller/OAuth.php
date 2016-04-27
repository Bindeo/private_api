<?php

namespace Api\Controller;

use Api\Entity\OAuthClient;
use Api\Entity\OAuthToken;
use Api\Repository\RepositoryAbstract;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

class OAuth
{
    /**
     * @var \Api\Repository\OAuth
     */
    private $oauthRepo;

    public function __construct(RepositoryAbstract $oauthRepo)
    {
        $this->oauthRepo = $oauthRepo;
    }

    /**
     * Get the requested OAuth Client
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function oauthClient(Request $request, Response $response, $args)
    {
        $data = $this->oauthRepo->oauthClient(new OAuthClient($request->getParams()));

        $res = [
            'data' => [
                'type'       => 'oauth_clients',
                'attributes' => $data ? $data->toArray() : []
            ]
        ];

        return $response->withJson($res, 200);
    }

    /**
     * Save a token
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function saveToken(Request $request, Response $response, $args)
    {
        $this->oauthRepo->saveToken(new OAuthToken($request->getParams()));

        return $response->withJson([], 204);
    }

    /**
     * Expire a token
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function expireToken(Request $request, Response $response, $args)
    {
        $this->oauthRepo->expireToken(new OAuthToken($request->getParams()));

        return $response->withJson([], 204);
    }

    /**
     * Get an ative token
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function getToken(Request $request, Response $response, $args)
    {
        $data = $this->oauthRepo->getToken(new OAuthToken($request->getParams()));

        $res = [
            'data' => [
                'type'       => 'oauth_tokens',
                'attributes' => isset($data->getRows()[0]) ? $data->getRows()[0]->toArray() : []
            ]
        ];

        return $response->withJson($res, 200);
    }
}