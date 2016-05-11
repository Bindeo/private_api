<?php

namespace Api\Controller;

use Api\Entity\User;
use Api\Repository\RepositoryAbstract;
use Bindeo\DataModel\Exceptions;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \MaxMind\Db\Reader;

class General
{
    /**
     * @var \Api\Repository\General
     */
    private $generalRepo;

    /**
     * @var Reader
     */
    protected $maxmind;

    public function __construct(RepositoryAbstract $generalRepo, Reader $maxmind)
    {
        $this->generalRepo = $generalRepo;
        $this->maxmind = $maxmind;
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

        $res = ['data' => $data->toArray('account_type'), 'total_pages' => 1];

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

        $res = ['data' => $data->toArray('media_type'), 'total_pages' => 1];

        return $response->withJson($res, 200);
    }

    /**
     * Get country iso code from ip
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function geolocalize(Request $request, Response $response, $args)
    {
        if (!$request->getParam('ip')) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Geolocalize the ip
        $geoip = $this->maxmind->get($request->getParam('ip'));

        $res = [
            'data' => [
                'type'       => 'users',
                'attributes' => (new User())->setIp($geoip['country']['iso_code'] ? $geoip['country']['iso_code']
                    : null)->toArray()
            ]
        ];

        return $response->withJson($res, 200);
    }
}