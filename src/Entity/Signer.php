<?php

namespace Api\Entity;

use Bindeo\DataModel\SignerAbstract;

class Signer extends SignerAbstract
{
    protected $lang;

    /**
     * @return mixed
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     * @param mixed $lang
     *
     * @return $this
     */
    public function setLang($lang)
    {
        $this->lang = $lang;

        return $this;
    }

    /**
     * Check if signer is a correct signer
     *
     * @return bool
     */
    public function isSigner()
    {
        return $this->email and $this->token;
    }
}