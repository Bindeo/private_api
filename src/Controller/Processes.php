<?php

namespace Api\Controller;

use Api\Repository\RepositoryAbstract;
use Bindeo\Filter\ProcessesFilter;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

class Processes
{
    /**
     * @var \Api\Repository\Processes
     */
    private $procRepo;

    public function __construct(RepositoryAbstract $procRepo)
    {
        $this->procRepo = $procRepo;
    }

    /**
     * Get the processes status listed by language
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function processesStatus(Request $request, Response $response, $args)
    {
        $data = $this->procRepo->getStatusList($request->getParam('locale'));

        $res = ['data' => $data->toArray('processes_status'), 'total_pages' => 1];

        return $response->withJson($res, 200);
    }

    /**
     * Get a processes list filter by some params
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param array                        $args [optional]
     *
     * @return \Slim\Http\Response
     * @throws \Exception
     */
    public function processesList(Request $request, Response $response, $args)
    {
        // Get the list
        $res = $this->procRepo->processesList(new ProcessesFilter($request->getParams()));

        $res = [
            'data'         => $res->toArray('processes'),
            'total_pages'  => $res->getNumPages(),
            'current_page' => $request->getParam('page')
        ];

        return $response->withJson($res, 200);
    }
}