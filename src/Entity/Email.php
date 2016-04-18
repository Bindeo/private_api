<?php

namespace Api\Entity;

use Bindeo\DataModel\EmailAbstract;
use Bindeo\DataModel\NotarizableInterface;

class Email extends EmailAbstract implements NotarizableInterface
{
    // ADITIONAL METHODS

    /**
     * @return string
     */
    public function getType()
    {
        return 'E';
    }

    /**
     * @return int
     */
    public function getIdElement()
    {
        return $this->idEmail;
    }

    /**
     * @return string
     */
    public function getFileOrigName()
    {
        return $this->subject;
    }
}