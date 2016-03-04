<?php

namespace Api\Entity;

use Bindeo\DataModel\FileAbstract;
use Bindeo\DataModel\SignableInterface;

class File extends FileAbstract implements SignableInterface
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