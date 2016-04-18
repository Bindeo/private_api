<?php

namespace Api\Entity;

use Bindeo\DataModel\FileAbstract;
use Bindeo\DataModel\NotarizableInterface;

class File extends FileAbstract implements NotarizableInterface
{
    // ADITIONAL METHODS

    /**
     * @return string
     */
    public function getType()
    {
        return 'F';
    }

    public function getIdElement()
    {
        return $this->idFile;
    }
}