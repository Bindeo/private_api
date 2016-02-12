<?php

namespace Api\Entity;

use Bindeo\DataModel\ClientAbstract;

class Client extends ClientAbstract
{
    // Optionals and temporary attributes
    protected $_renew;

    /**
     * @return mixed
     */
    public function getRenew()
    {
        return $this->_renew;
    }

    /**
     * @param mixed $renew
     *
     * @return $this
     */
    public function setRenew($renew)
    {
        $this->_renew = $renew;

        return $this;
    }
}