<?php

namespace Api\Languages;

class EnUs extends TranslateAbstract
{
    public function __construct()
    {
        $this->texts = [
            'registry_subject' => 'Welcome %s to Bindeo',
            'registry_followlink' => 'Folow this link to validate your account'
        ];
    }
}