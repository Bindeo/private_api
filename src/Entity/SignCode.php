<?php

namespace Api\Entity;

use Bindeo\DataModel\SignCodeAbstract;

class SignCode extends SignCodeAbstract
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
}