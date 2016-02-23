<?php

namespace Api\Languages;

class EsEs extends TranslateAbstract
{
    public function __construct()
    {
        $this->texts = [
            'registry_subject' => 'Bienvenido %s a Bindeo',
            'registry_followlink' => 'Pulsa este enlace para validar tu cuenta'
        ];
    }
}