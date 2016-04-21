<?php

namespace Api\Entity;

use Bindeo\DataModel\BulkEventAbstract;

class BulkEvent extends BulkEventAbstract
{
    /**
     * @var BulkTransaction
     */
    protected $bulkObj;

    /**
     * @return array
     */
    public function getStructure()
    {
        return ['name' => $this->name, 'timestamp' => $this->getFormattedTimestamp(), 'data' => $this->data];
    }

    /**
     * @return BulkTransaction
     */
    public function getBulkObj()
    {
        return $this->bulkObj;
    }

    /**
     * @param BulkTransaction $bulkObj
     *
     * @return $this
     */
    public function setBulkObj(BulkTransaction $bulkObj)
    {
        $this->bulkObj = $bulkObj;

        return $this;
    }

}