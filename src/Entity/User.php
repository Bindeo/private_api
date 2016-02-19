<?php

namespace Api\Entity;

use Bindeo\DataModel\UserAbstract;

class User extends UserAbstract
{
    // Optionals and temporary attributes
    protected $renew;

    /**
     * @return mixed
     */
    public function getRenew()
    {
        return $this->renew;
    }

    /**
     * @param mixed $renew
     *
     * @return $this
     */
    public function setRenew($renew)
    {
        $this->renew = $renew;

        return $this;
    }
}