<?php

namespace Api\Middleware;

use Bindeo\OAuth2\OAuthProviderAbstract;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Api\Model\General\OAuthRegistry;

class OAuth
{
    private $oauth;

    public function __construct(OAuthProviderAbstract $oauth)
    {
        $this->oauth = $oauth;
    }

    /**
     * Check authorization against OAuth2 service
     *
     * @param Request|\Slim\Http\Request   $request
     * @param Response|\Slim\Http\Response $response
     * @param callable                     $next
     *
     * @return \Slim\Http\Response
     */
    public function __invoke(Request $request, Response $response, callable $next)
    {
        $data = $this->oauth->verify($request);

        OAuthRegistry::getInstance()
                     ->setGrantType($data['grantType'])
                     ->setAppId($data['appId'])
                     ->setAppName($data['appName'])
                     ->setAppRole($data['appRole']);

        return $next($request, $response);
    }
}