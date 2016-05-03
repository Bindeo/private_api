<?php

namespace Api\Entity;

use Bindeo\DataModel\UserAbstract;
use Bindeo\DataModel\UserInterface;

class User extends UserAbstract implements UserInterface
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