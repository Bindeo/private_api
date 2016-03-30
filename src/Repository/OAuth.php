<?php

namespace Api\Repository;

use Api\Entity\OAuthClient;
use Api\Entity\OAuthToken;
use Api\Entity\ResultSet;
use Bindeo\DataModel\Exceptions;

class OAuth extends RepositoryAbstract
{
    /**
     * Get an OAuth Client given by its name and secret
     *
     * @param OAuthClient $client
     *
     * @return ResultSet
     * @throws \Exception
     */
    public function oauthClient(OAuthClient $client)
    {
        $client->clean();
        if (!$client->getName() or !$client->getSecret()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $sql = "SELECT ID_CLIENT, NAME, SECRET, ROLE FROM OAUTH_CLIENTS WHERE NAME = :name AND SECRET = :secret AND STATUS = 'A'";
        $params = [':name' => mb_strtoupper($client->getName()), ':secret' => $client->getSecret()];

        $data = $this->db->query($sql, $params, 'Api\Entity\OAuthClient');

        if (!$data or $this->db->getError()) {
            throw new \Exception($this->db->getError(), 400);
        }

        return $data;
    }

    /**
     * Save a token
     *
     * @param OAuthToken $token
     *
     * @return bool
     * @throws \Exception
     */
    public function saveToken(OAuthToken $token)
    {
        $token->clean();
        if (!$token->getToken() or !in_array($token->getType(), ['A', 'R']) or !$token->getExpiration() or
            !$token->getIp()
        ) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Insert new token
        $sql = 'INSERT INTO OAUTH_TOKENS(TOKEN, TYPE, EXPIRATION, CTRL_DATE, CTRL_IP, FK_ID_CLIENT, FK_ID_USER, FK_ACCESS_TOKEN)
                VALUES (:token, :type, :expiration, SYSDATE(), :ip, :id_client, :id_user, :access_token)';
        $params = [
            ':token'        => $token->getToken(),
            ':type'         => $token->getType(),
            ':expiration'   => $token->getFormattedExpiration(),
            ':ip'           => $token->getIp(),
            ':id_client'    => $token->getIdClient() ? $token->getIdClient() : null,
            ':id_user'      => $token->getIdUser() ? $token->getIdUser() : null,
            ':access_token' => $token->getAccessToken() ? $token->getAccessToken() : null
        ];

        // Execute query
        if ($this->db->action($sql, $params)) {
            return true;
        } else {
            throw $this->dbException();
        }
    }

    /**
     * Expire given token
     *
     * @param OAuthToken $token
     *
     * @return bool
     * @throws \Exception
     */
    public function expireToken(OAuthToken $token)
    {
        $token->clean();
        if (!$token->getToken()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Expire the token and refresh token
        $sql = 'UPDATE OAUTH_TOKENS SET EXPIRATION = SYSDATE() WHERE TOKEN = :token OR FK_ACCESS_TOKEN = :token';
        // Execute query
        if ($this->db->action($sql, [':token' => $token->getToken()])) {
            return true;
        } else {
            throw $this->dbException();
        }
    }

    /**
     * Get an active token
     *
     * @param OAuthToken $token
     *
     * @return ResultSet
     * @throws \Exception
     */
    public function getToken(OAuthToken $token)
    {
        $token->clean();
        if (!$token->getToken()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Get the token if it is active
        $sql = 'SELECT TOKEN, EXPIRATION FROM OAUTH_TOKENS WHERE TOKEN = :token AND EXPIRATION > SYSDATE()';
        $data = $this->db->query($sql, [':token' => $token->getToken()], 'Api\Entity\OAuthToken');

        if (!$data or $this->db->getError()) {
            throw new \Exception($this->db->getError(), 400);
        }

        return $data;
    }
}