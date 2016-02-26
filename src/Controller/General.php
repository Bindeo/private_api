<?php

namespace Api\Controller;

use Api\Entity\User;
use Api\Repository\RepositoryAbstract;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

class General
{
    /**
     * @var \Api\Repository\General
     */
    private $generalRepo;

    public function __construct(RepositoryAbstract $generalRepo)
    {
        $this->generalRepo = $generalRepo;
    }

    /**
     * Get the account types list by language
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function accountTypes(Request $request, Response $response, $args)
    {
        $data = $this->generalRepo->accountTypes($request->getParam('locale'));

        $res = ['data' => $data->toArray('account_type')];

        return $response->withJson($res, 200);
    }

    /**
     * Get the file types list by language
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function fileTypes(Request $request, Response $response, $args)
    {
        $data = $this->generalRepo->fileTypes($request->getParam('locale'));

        $res = ['data' => $data->toArray('file_type')];

        return $response->withJson($res, 200);
    }

    /**
     * Get the media types list by language
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function mediaTypes(Request $request, Response $response, $args)
    {
        $data = $this->generalRepo->mediaTypes($request->getParam('locale'));

        $res = ['data' => $data->toArray('media_type')];

        return $response->withJson($res, 200);
    }
}