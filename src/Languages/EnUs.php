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
            'registry_verify_button'    => 'Verify email address',
            'verification_subject'      => 'Verify your email address %s',
            'verification_explanation'  => 'Confirm your email address %s by clicking on the following button',
            'password_subject'          => 'Reset your password %s',
            'password_explanation'      => 'You have requested reset your Bindeo\'s account password, click the following button for choose a new one, remember that this link expires in 24 hours',
            'password_button'           => 'Choose new password',
            'password_notifyus'         => 'If you haven\'t requested to reset your password, please, %snotify us%s',
            'sign_request_subject'      => '%s sent you a document to sign',
            'sign_request_explanation'  => '%s sent you the following document to review and sign',
            'sign_request_button'       => 'Review document',
            'sign_request_replyto'      => 'Please, do not share this email, if you need to modify the document or have questions, email directly to %s by replying this email'
        ];
    }
}