<?php

namespace Api\Entity;

use Bindeo\DataModel\BlockChainAbstract;
use Bindeo\DataModel\LocatableInterface;

class BlockChain extends BlockChainAbstract implements LocatableInterface
{
    // Optionals and temporary attributes
    protected $_ip;
    protected $_latitude;
    protected $_longitude;
    protected $_idGeonames;

    /**
     * @return mixed
     */
    public function getIp()
    {
        return $this->_ip;
    }

    /**
     * @param mixed $ip
     *
     * @return BlockChain
     */
    public function setIp($ip)
    {
        $this->_ip = $ip;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getLatitude()
    {
        return $this->_latitude;
    }

    /**
     * @param mixed $latitude
     *
     * @return BlockChain
     */
    public function setLatitude($latitude)
    {
        $this->_latitude = $latitude;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getLongitude()
    {
        return $this->_longitude;
    }

    /**
     * @param mixed $longitude
     *
     * @return BlockChain
     */
    public function setLongitude($longitude)
    {
        $this->_longitude = $longitude;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getIdGeonames()
    {
        return $this->_idGeonames;
    }

    /**
     * @param mixed $idGeonames
     *
     * @return BlockChain
     */
    public function setIdGeonames($idGeonames)
    {
        $this->_idGeonames = $idGeonames;

        return $this;
    }
}