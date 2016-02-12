<?php

namespace Api\Repository;

use Api\Model\General\DatabaseInterface;
use Api\Model\General\Exceptions;

abstract class RepositoryAbstract
{
    protected $_codes;
    protected $_db;

    public function __construct(DatabaseInterface $db)
    {
        $this->_db = $db;

        $this->_codes = [23000 => [409, Exceptions::DUPLICATED_KEY]];
    }

    /**
     * Return a filled Exception from db error
     *
     * @return \Exception
     */
    protected function _dbException()
    {
        if (isset($this->_codes[$this->_db->getError()[0]])) {
            return new \Exception($this->_codes[$this->_db->getError()[0]][1],
                $this->_codes[$this->_db->getError()[0]][0]);
        } else {
            return new \Exception(json_encode($this->_db->getError()), 400);
        }
    }
}