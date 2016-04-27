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

    // ADITIONAL METHODS
    /**
     * Transform original Json string of signers into an array of Signer objects
     * @return $this
     * @throws \Exception
     */
    public function transformSigners()
    {
        // Check signers
        if (!$this->signers) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Try to decode signers
        try {
            $signers = json_decode($this->signers, true);
        } catch (\Exception $e) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Populate objects
        $this->signers = [];
        foreach ($signers as $signer) {
            $this->signers[] = (new Signer($signer))->setElementType('F')->setElementId($this->idFile);
        }

        return $this;
    }

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
}