<?php

namespace Api\Entity;

use Bindeo\DataModel\BlockChainAbstract;
use Bindeo\DataModel\LocatableInterface;

class BlockChain extends BlockChainAbstract implements LocatableInterface
{
    // Optionals and temporary attributes
    protected $ip;
    protected $latitude;
    protected $longitude;
    protected $idGeonames;

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
     * @return BlockChain
     */
    public function setIp($ip)
    {
        $this->ip = $ip;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * @param mixed $latitude
     *
     * @return BlockChain
     */
    public function setLatitude($latitude)
    {
        $this->latitude = $latitude;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getLongitude()
    {
        return $this->longitude;
    }

    /**
     * @param mixed $longitude
     *
     * @return BlockChain
     */
    public function setLongitude($longitude)
    {
        $this->longitude = $longitude;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getIdGeonames()
    {
        return $this->idGeonames;
    }

    /**
     * @param mixed $idGeonames
     *
     * @return BlockChain
     */
    public function setIdGeonames($idGeonames)
    {
        $this->idGeonames = $idGeonames;

        return $this;
    }

    // ADITIONAL METHODS
    /**
     * @return SignatureGenerator
     */
    public function getSignature()
    {
        if ($this->jsonData) {
            return new SignatureGenerator(json_decode($this->jsonData, true));
        } else return null;
    }
}