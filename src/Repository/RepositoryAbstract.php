<?php

namespace Api\Repository;

use Api\Model\General\DatabaseInterface;
use Api\Model\General\Exceptions;

abstract class RepositoryAbstract
{
    protected $codes;
    protected $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;

        $this->codes = [23000 => [409, Exceptions::DUPLICATED_KEY]];
    }

    /**
     * Return a filled Exception from db error
     *
     * @return \Exception
     */
    protected function dbException()
    {
        if (isset($this->codes[$this->db->getError()[0]])) {
            return new \Exception($this->codes[$this->db->getError()[0]][1],
                $this->codes[$this->db->getError()[0]][0]);
        } else {
            return new \Exception(json_encode($this->db->getError()), 400);
        }
    }
}