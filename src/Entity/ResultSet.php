<?php

namespace Api\Entity;

class ResultSet
{
    private $numRows;
    private $numPages;
    private $rows;

    public function __construct($numRows, $numPages, array $rows)
    {
        $this->numRows = (int)$numRows;
        $this->numPages = (int)$numPages;
        $this->rows = $rows;
    }

    /**
     * @return array
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * @return int
     */
    public function getNumPages()
    {
        return $this->numPages;
    }

    /**
     * @return int
     */
    public function getNumRows()
    {
        return $this->numRows;
    }

    /**
     * Convert the whole ResultSet into a well formed api answer
     *
     * @param string $type
     *
     * @return array
     */
    public function toArray($type)
    {
        $array = [];
        if ($this->numRows > 0) {
            foreach ($this->rows as $row) {
                $array[] = ['type' => $type, 'attributes' => $row->toArray()];
            }
        }

        return $array;
    }
}