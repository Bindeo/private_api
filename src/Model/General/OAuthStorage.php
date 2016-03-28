<?php

namespace Api\Model\General;

use Bindeo\OAuth2\OAuthStorageInterface;
use Bindeo\OAuth2\OAuthProviderAbstract;

class OAuthStorage implements OAuthStorageInterface
{
    private $conf;

    public function __construct($settings)
    {
        $this->conf = $settings;
    }

    /**
     * @param string $clientId
     * @param string $clientSecret
     * @param string $authCode
     *
     * @return mixed
     */
    public function authorize($clientId = null, $clientSecret = null, $authCode = null)
    {
        // TODO: Implement authorize() method.
    }

    /**
     * @param string $type
     * @param string $oauthToken
     *
     * @return array
     * @throws \Exception
     */
    public function getOAuth($type, $oauthToken = null)
    {
        // Validate the OAuth token
        if ($type != "Bearer" or !$oauthToken or !isset($this->conf[$oauthToken])) {
            throw new \Exception(OAuthProviderAbstract::INVALID_AUTH_DATA, 401);
        }

        // Take the app role and initialize the system
        return $this->conf[$oauthToken];
    }

    /**
     * @param $nonce
     * @param $timestamp
     *
     * @return boolean
     */
    public function checkNonce($nonce, $timestamp)
    {
        return null;
    }
}