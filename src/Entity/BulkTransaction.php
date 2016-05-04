<?php

namespace Api\Entity;

use Bindeo\DataModel\BulkTransactionAbstract;
use Bindeo\DataModel\Exceptions;

class BulkTransaction extends BulkTransactionAbstract
{
    protected $typeObject;

    // ADITIONAL METHODS
    /**
     * Transform original Json string of signers into an array of Signer objects
     * @return $this
     * @throws \Exception
     */
    public function transformSigners()
    {
        // Check signers
        if (!$this->signers) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Try to decode signers
        try {
            $signers = json_decode($this->signers, true);
        } catch (\Exception $e) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Populate objects
        $this->signers = [];
        foreach ($signers as $signer) {
            $this->signers[] = (new Signer($signer))->setIdBulk($this->idBulkTransaction);
        }

        return $this;
    }

    /**
     * Transform original Json string of files into an array of BulkFile objects
     * @return $this
     * @throws \Exception
     */
    public function transformFiles()
    {
        // Check files
        if (!$this->files) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Try to decode files
        try {
            $files = json_decode($this->files, true);
        } catch (\Exception $e) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Populate objects
        $this->files = [];
        foreach ($files as $file) {
            $this->files[] = (new BulkFile($file))->setClientType($this->clientType)
                                                  ->setIdClient($this->idClient)
                                                  ->setIp($this->ip);
        }
        $this->numItems = count($this->files);

        return $this;
    }

    /**
     * Generate bulk transaction hash
     *
     * @return $this
     */
    public function hash()
    {
        $this->hash = hash('sha256', $this->structure);

        return $this;
    }

    /**
     * @return BulkType
     */
    public function getTypeObject()
    {
        return $this->typeObject;
    }

    /**
     * @param BulkType $typeObject
     *
     * @return $this
     */
    public function setTypeObject($typeObject)
    {
        $this->typeObject = $typeObject;

        return $this;
    }
}