<?php

namespace Api\Model\Phone;

interface PhoneInterface
{
    public function __construct($key);

    public function sendMessage($to, $content);
}