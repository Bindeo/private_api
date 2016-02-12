<?php

namespace Api\Model\General;

use Bindeo\OAuth2\OAuthStorageInterface;
use Bindeo\OAuth2\OAuthProviderAbstract;

class OAuthStorage implements OAuthStorageInterface
{
    private $_conf;

    public function __construct($settings)
    {
        $this->_conf = $settings;
    }

    /**
     * @param string $type
     * @param string $oauthToken
     * @param int    $clientId
     * @param string $clientSecret
     *
     * @return array
     * @throws \Exception
     */
    public function getOAuth($type, $oauthToken = null, $clientId = null, $clientSecret = null)
    {
        // Validate the OAuth token
        if ($type != "Bearer" or !$oauthToken or !isset($this->_conf[$oauthToken])) {
            throw new \Exception(OAuthProviderAbstract::INVALID_AUTH_DATA, 401);
        }

        // Take the app role and initialize the system
        return $this->_conf[$oauthToken];
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