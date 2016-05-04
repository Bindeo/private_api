<?php

namespace Api\Entity;

use Bindeo\DataModel\Exceptions;
use Bindeo\DataModel\FileAbstract;
use Bindeo\DataModel\NotarizableInterface;
use Bindeo\DataModel\UserInterface;

class File extends FileAbstract implements NotarizableInterface
{
    /**
     * @var UserInterface
     */
    protected $user;

    protected $existent;

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

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param UserInterface $user
     *
     * @return File
     */
    public function setUser(UserInterface $user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getExistent()
    {
        return $this->existent;
    }

    /**
     * @param mixed $existent
     *
     * @return $this
     */
    public function setExistent($existent)
    {
        $this->existent = $existent;

        return $this;
    }
}