<?php

namespace Api\Languages;

class EsEs extends TranslateAbstract
{
    public function __construct()
    {
        $this->texts = [
            'general_bindeo_registered' => 'Bindeo es una compañía registrada',
            'general_thanks'            => 'Muchas gracias',
            'general_sendedto'          => 'Este mensaje ha sido enviado exclusivamente a %s',
            'general_notifyus'          => 'si no eres tu, por favor, %snotifícanoslo%s',
            'general_hi_user'           => 'Hola %s',
            'registry_subject'          => 'Bienvenido %s a Bindeo',
            'registry_explanation'      => 'Ayúdanos a asegurar tu cuenta Bindeo verificando tu dirección de e-mail',
            'registry_verify_button'    => 'Verifica la dirección de e-mail',
            'verification_subject'      => 'Verifica tu dirección de email %s',
            'verification_explanation'  => 'Confirma tu dirección de email %s pulsando en el botón siguiente'
        ];
    }
}