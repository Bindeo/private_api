<?php

namespace Api\Model\General;

class OAuth extends \Bindeo\OAuth2\OAuthProviderAbstract
{
    /**
     * Verify an OAuth2 request
     *
     * @param \Psr\Http\Message\ServerRequestInterface|\Slim\Http\Request $request
     *
     * @return array
     * @throws \Exception
     */
    public function verify(\Psr\Http\Message\ServerRequestInterface $request)
    {
        // Get authorization header
        $authorization = $request->getHeader('Authorization');
        if (!$authorization[0]) {
            throw new \Exception(self::NO_AUTH_HEADER, 400);
        }

        // Check de authorization code
        $authorization = explode(' ', $authorization[0]);
        $data = $this->oauthStorage->getOAuth($authorization[0], $authorization[1]);

        return $data;
    }
}