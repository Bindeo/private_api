<?php

namespace Api\Model\Phone;

interface PhoneInterface
{
    public function __construct($key);

    /**
     * Send an email
     *
     * @param string $to
     * @param string $content
     *
     * @return bool
     */
    public function sendMessage($to, $content);

    /**
     * Validate a mobile phone number
     *
     * @param string $number
     *
     * @return bool
     */
    public function validateNumber($number);
}