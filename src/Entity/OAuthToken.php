<?php

namespace Api\Entity;

use Bindeo\DataModel\OAuthTokenAbstract;

class OAuthToken extends OAuthTokenAbstract
{
    protected $ip;

    /**
     * @return mixed
     */
    public function getIp()
    {
        return $this->ip;
    }

    /**
     * @param mixed $ip
     *
     * @return $this
     */
    public function setIp($ip)
    {
        $this->ip = $ip;

        return $this;
    }
}