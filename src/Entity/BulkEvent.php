<?php

namespace Api\Entity;

use Bindeo\DataModel\BulkEventAbstract;

class BulkEvent extends BulkEventAbstract
{
    protected $bulkExternalId;

    /**
     * @return mixed
     */
    public function getBulkExternalId()
    {
        return $this->bulkExternalId;
    }

    /**
     * @param mixed $bulkExternalId
     *
     * @return BulkEvent
     */
    public function setBulkExternalId($bulkExternalId)
    {
        $this->bulkExternalId = $bulkExternalId;

        return $this;
    }

    /**
     * @return array
     */
    public function getStructure()
    {
        return ['name' => $this->name, 'timestamp' => $this->getFormattedTimestamp(), 'data' => $this->data];
    }
}