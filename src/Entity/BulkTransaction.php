<?php

namespace Api\Entity;

use Bindeo\DataModel\BulkTransactionAbstract;
use Bindeo\DataModel\Exceptions;

class BulkTransaction extends BulkTransactionAbstract
{
    /**
     * Transform original Json string of files into an array of BulkFile objects
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
        } catch(\Exception $e) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Populate objects
        $this->files = [];
        foreach ($files as $file) {
            $this->files[] = (new BulkFile($file))->setIdUser($this->idUser)->setIp($this->ip);
        }
        $this->numFiles = count($this->files);
    }
}