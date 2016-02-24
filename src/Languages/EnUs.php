<?php

namespace Api\Languages;

class EnUs extends TranslateAbstract
{
    public function __construct()
    {
        $this->texts = [
            'general_bindeo_registered' => 'Bindeo is a registered company',
            'general_thanks'            => 'Many thanks',
            'general_sendedto'          => 'This message is exclusively addressed to %s',
            'general_notifyus'          => 'if it\'s not you, please, %snotify us%s',
            'general_hi_user'           => 'Hi %s',
            'registry_subject'          => 'Welcome %s to Bindeo',
            'registry_explanation'      => 'Help us secure your Bindeo\'s account by verifiying your email address',
            'registry_verify_button'    => 'Verify email address'
        ];
    }
}