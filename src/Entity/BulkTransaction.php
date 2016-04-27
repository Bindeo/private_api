<?php

namespace Api\Entity;

use Bindeo\DataModel\BulkTransactionAbstract;
use Bindeo\DataModel\Exceptions;

class BulkTransaction extends BulkTransactionAbstract
{
    protected $typeObject;

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