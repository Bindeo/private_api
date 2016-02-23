<?php

namespace Api\Languages;

/**
 * Factory class to generate correct translation class
 * @package Api\Languages
 */
class TranslateFactory
{
    /**
     * Factory method
     *
     * @param $lang
     *
     * @return TranslateAbstract
     */
    static public function factory($lang)
    {
        if ($lang == 'es_ES') {
            return new EsEs();
        } elseif ($lang == 'en_US') {
            return new EnUs();
        } else {
            return null;
        }
    }
}