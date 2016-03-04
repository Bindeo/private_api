<?php

namespace Api\Entity;

class Signature
{
    private $assetType;
    private $assetHash;
    private $assetSize;
    private $assetName;
    private $ownerName;
    private $ownerId;

    // Aux
    private $auxIdentity;
    private $translate = ['F' => 'File', 'E' => 'Email', 'File' => 'F', 'Email' => 'E'];

    public function __construct(array $data = null)
    {
        if ($data) {
            if (isset($data['asset']['type']) and isset($this->translate[$data['asset']['type']])) {
                $this->assetType = $this->translate[$data['asset']['type']];
            }
            if (isset($data['asset']['hash'])) {
                $this->assetHash = $data['asset']['hash'];
            }
            if (isset($data['asset']['size'])) {
                $this->assetSize = $data['asset']['size'];
            }
            if (isset($data['asset']['name'])) {
                $this->assetName = $data['asset']['name'];
            }
            if (isset($data['owner']['name'])) {
                $this->ownerName = $data['owner']['name'];
            }
            if (isset($data['owner']['id'])) {
                $this->ownerId = $data['owner']['id'];
            }
        }
    }

    /**
     * @return mixed
     */
    public function getAssetType()
    {
        return $this->assetType;
    }

    /**
     * @param mixed $assetType
     *
     * @return Signature
     */
    public function setAssetType($assetType)
    {
        $this->assetType = $assetType;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAssetHash()
    {
        return $this->assetHash;
    }

    /**
     * @param mixed $assetHash
     *
     * @return Signature
     */
    public function setAssetHash($assetHash)
    {
        $this->assetHash = $assetHash;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAssetSize()
    {
        return $this->assetSize;
    }

    /**
     * @param mixed $assetSize
     *
     * @return Signature
     */
    public function setAssetSize($assetSize)
    {
        $this->assetSize = $assetSize;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAssetName()
    {
        return $this->assetName;
    }

    /**
     * @param mixed $assetName
     *
     * @return Signature
     */
    public function setAssetName($assetName)
    {
        $this->assetName = $assetName;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getOwnerName()
    {
        return $this->ownerName;
    }

    /**
     * @param mixed $ownerName
     *
     * @return Signature
     */
    public function setOwnerName($ownerName)
    {
        $this->ownerName = $ownerName;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getOwnerId()
    {
        return $this->ownerId;
    }

    /**
     * @param mixed $ownerId
     *
     * @return Signature
     */
    public function setOwnerId($ownerId)
    {
        $this->ownerId = $ownerId;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAuxIdentity()
    {
        return $this->auxIdentity;
    }

    /**
     * @param mixed $auxIdentity
     *
     * @return Signature
     */
    public function setAuxIdentity($auxIdentity)
    {
        $this->auxIdentity = $auxIdentity;

        return $this;
    }


    // ADITIONAL METHODS
    /**
     * Check if signature is complete
     * @return bool
     */
    public function isValid()
    {
        return $this->assetHash and $this->assetName and $this->assetSize and $this->assetType and $this->ownerName and $this->ownerId;
    }

    /**
     * Generate signature structure
     *
     * @param bool $json
     *
     * @return array|string
     */
    public function generate($json = false)
    {
        if ($this->isValid()) {
            $sign = [
                'asset' => [
                    'type' => $this->translate[$this->assetType],
                    'hash' => $this->assetHash,
                    'size' => $this->assetSize,
                    'name' => $this->assetName
                ],
                'owner' => [
                    'name' => $this->ownerName,
                    'id'   => $this->ownerId
                ]
            ];

            return $json ? json_encode($sign) : $sign;
        } else {
            return null;
        }
    }

    /**
     * Generate signature hash
     *
     * @param bool $raw [optional]
     *
     * @return string
     */
    public function generateHash($raw = false)
    {
        if ($json = $this->generate(true)) {
            return hash('sha256', $json, $raw);
        } else {
            return null;
        }
    }
}