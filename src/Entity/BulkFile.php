<?php

namespace Api\Entity;

use Bindeo\DataModel\BulkFileAbstract;

class BulkFile extends BulkFileAbstract
{
    /**
     * @return array
     */
    public function getStructure()
    {
        return ['hash' => $this->hash, 'to' => hash('sha256', $this->fullName)];
    }
}