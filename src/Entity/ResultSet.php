<?php

namespace Api\Entity;

class ResultSet
{
    private $_numRows;
    private $_numPages;
    private $_rows;

    public function __construct($numRows, $numPages, array $rows)
    {
        $this->_numRows = (int)$numRows;
        $this->_numPages = (int)$numPages;
        $this->_rows = $rows;
    }

    /**
     * @return array
     */
    public function getRows()
    {
        return $this->_rows;
    }

    /**
     * @return int
     */
    public function getNumPages()
    {
        return $this->_numPages;
    }

    /**
     * @return int
     */
    public function getNumRows()
    {
        return $this->_numRows;
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
        if ($this->_numRows > 0) {
            foreach ($this->_rows as $row) {
                $array[] = ['type' => $type, 'attributes' => $row->toArray()];
            }
        }

        return $array;
    }
}