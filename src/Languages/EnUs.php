<?php

namespace Api\Languages;

class EnUs extends TranslateAbstract
{
    public function __construct()
    {
        $this->texts = [
            'general_bindeo_registered'   => 'Bindeo is a registered company',
            'general_thanks'              => 'Many thanks',
            'general_sendedto'            => 'This message is exclusively addressed to %s',
            'general_notifyus'            => 'if it\'s not you, please, %snotify us%s',
            'general_hi_user'             => 'Hi %s',
            'registry_subject'            => 'Welcome %s to Bindeo',
            'registry_explanation'        => 'Help us secure your Bindeo\'s account by verifiying your email address',
            'registry_verify_button'      => 'Verify email address',
            'verification_subject'        => 'Verify your email address %s',
            'verification_explanation'    => 'Confirm your email address %s by clicking on the following button',
            'password_subject'            => 'Reset your password %s',
            'password_explanation'        => 'You have requested reset your Bindeo\'s account password, click the following button for choose a new one, remember that this link expires in 24 hours',
            'password_button'             => 'Choose new password',
            'password_notifyus'           => 'If you haven\'t requested to reset your password, please, %snotify us%s',
            'sign_request_subject'        => '%s sent you a document to sign',
            'sign_request_explanation'    => '%s sent you the following document to review and sign',
            'sign_review_button'          => 'Review document',
            'sign_request_replyto'        => 'Please, do not share this email, if you need to modify the document or have questions, email directly to %s by replying this email',
            'sign_viewed_subject'         => '%s viewed your document',
            'sign_viewed_explanation'     => 'At %s, %s opened and viewed you document',
            'sign_code_subject'           => 'Your verification code',
            'sign_code_title'             => 'Your verification code to sign document',
            'sign_code_explanation'       => 'This code is valid during following 10 minutes',
            'sign_signed_subject'         => '%s signed your document',
            'sign_signed_explanation'     => 'At %s, %s signed you document',
            'sign_signed_pending_sing'    => '%s recipient is pending',
            'sign_signed_pending_plur'    => '%s recipients are pending',
            'sign_signed_notarizing'      => 'Your signed document is being notarized, you will receive an email when it finish',
            'sign_completed_subject'      => 'Your document has been completed',
            'sign_completed_explain'      => 'All signers completed your document',
            'sign_completed_button'       => 'Download proof',
            'sign_completed_subject_copy' => 'All signers completed %s document, keep your signed copy',
            'sign_completed_button_copy'  => 'Download signed document',
            'sign_completed_explain_copy' => 'All your signed documents are available by %ssigning up on Bindeo%s'
        ];
    }
}