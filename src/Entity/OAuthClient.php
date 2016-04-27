<?php

namespace Api\Entity;

use Bindeo\DataModel\OAuthClientAbstract;
use Bindeo\DataModel\UserInterface;

class OAuthClient extends OAuthClientAbstract implements UserInterface
{
    /**
     * @return string
     */
    public function getUserType()
    {
        return 'C';
    }

    /**
     * @return int
     */
    public function getIdUser()
    {
        return $this->idClient;
    }

    /**
     * @return string
     */
    public function getLang()
    {
        return 'en_US';
    }
}