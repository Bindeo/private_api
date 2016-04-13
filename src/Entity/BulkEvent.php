<?php

namespace Api\Entity;

use Bindeo\DataModel\BulkEventAbstract;

class BulkEvent extends BulkEventAbstract
{
    /**
     * @return array
     */
    public function getStructure()
    {
        return ['name' => $this->name, 'timestamp' => $this->getFormattedTimestamp(), 'data' => $this->data];
    }
}