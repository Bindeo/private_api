<?php

namespace Api\Repository;

use Api\Entity\OAuthClient;
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
}