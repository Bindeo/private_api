<?php

namespace Api\Entity;

use Bindeo\DataModel\ResultSetAbstract;

class ResultSet extends ResultSetAbstract
{
    public function __construct($numRows, $numPages, array $rows)
    {
        $this->numRows = (int)$numRows;
        $this->numPages = (int)$numPages;
        $this->rows = $rows;
    }
}