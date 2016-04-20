<?php

namespace Api\Model\Email;

interface EmailInterface
{
    public function __construct($domain, $baseFrom, $key);

    public function sendEmail($to, $subject, $content, $files = [], $from = null, $replyTo = null);
}