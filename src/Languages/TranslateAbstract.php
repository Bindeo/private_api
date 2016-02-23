<?php

namespace Api\Languages;

/**
 * Base class to translate texts inside Slim
 * @package Api\Languages
 */
abstract class TranslateAbstract
{
    protected $texts = [];

    /**
     * Find the translation of the given key
     *
     * @param string $key
     * @param array  $args [optional]
     *
     * @return string
     */
    public function translate($key, ...$args)
    {
        if (isset($this->texts[$key])) {
            return vsprintf($this->texts[$key], $args);
        } else {
            return "";
        }
    }
}