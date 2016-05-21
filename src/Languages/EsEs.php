<?php

namespace Api\Languages;

class EsEs extends TranslateAbstract
{
    public function __construct()
    {
        $this->texts = [
            'general_bindeo_registered'    => 'Bindeo es una compañía registrada',
            'general_thanks'               => 'Muchas gracias',
            'general_sendedto'             => 'Este mensaje ha sido enviado exclusivamente a %s',
            'general_notifyus'             => 'si no eres tu, por favor, %snotifícanoslo%s',
            'general_hi_user'              => 'Hola %s',
            'registry_subject'             => 'Bienvenido %s a Bindeo',
            'registry_explanation'         => 'Ayúdanos a asegurar tu cuenta Bindeo verificando tu dirección de e-mail',
            'registry_verify_button'       => 'Verifica la dirección de e-mail',
            'verification_subject'         => 'Verifica tu dirección de email %s',
            'verification_explanation'     => 'Confirma tu dirección de email %s pulsando en el botón siguiente',
            'password_subject'             => 'Restablece tu contraseña %s',
            'password_explanation'         => 'Has solicitado restablecer la contraseña de tu cuenta de Bindeo, pulsa en el botón siguiente para elegir una nueva, recuerda que este enlace expira en 24 horas',
            'password_button'              => 'Elegir nueva contraseña',
            'password_notifyus'            => 'Si no has solicitado restablecer tu contraseña, por favor, %snotifícanoslo%s',
            'sign_request_subject'         => '%s te envió para firmar "%s"',
            'sign_request_explanation'     => '%s te envió el siguiente documento para revisar y firmar',
            'sign_review_button'           => 'Revisa el documento',
            'sign_request_replyto'         => 'Por favor, no compartas este e-mail, si necesitas modificar el documento o tienes preguntas, escribe directamente a %s respondiendo este e-mail',
            'sign_viewed_subject'          => '%s revisó "%s"',
            'sign_viewed_subtitle'         => '%s revisó tu documento',
            'sign_viewed_explanation'      => 'El %s, %s abrió y revisó tu documento',
            'sign_code_subject'            => 'Tu código de verificación para firmar "%s"',
            'sign_code_title'              => 'Tu código de verificación para firmar el documento',
            'sign_code_explanation'        => 'Este código es válido durante los próximos 10 minutos',
            'sign_signed_subject'          => '%s firmó "%s"',
            'sign_signed_subtitle'         => '%s firmó tu documento',
            'sign_signed_explanation'      => 'El %s, %s firmó tu documento',
            'sign_signed_pending_sing'     => 'Está pendiente %s receptor',
            'sign_signed_pending_plur'     => 'Están pendientes %s receptores',
            'sign_signed_notarizing'       => 'Tu documento firmado está siendo notarizado, recibirás un email cuando termine',
            'sign_completed_subject'       => '"%s" ha sido firmado',
            'sign_completed_subtitle'      => 'Tu documento ha sido firmado',
            'sign_completed_explain'       => 'Todos los involucrados firmaron tu documento',
            'sign_completed_button'        => 'Descarga prueba',
            'sign_completed_subject_copy'  => 'Todos los involucrados firmaron "%s", mantén tu copia firmada',
            'sign_completed_subtitle_copy' => 'Todos los involucrados firmaron el documento de %s, mantén tu copia firmada',
            'sign_completed_button_copy'   => 'Descarga el documento firmado',
            'sign_completed_explain_copy'  => 'Todos tus documentos firmados están disponibles %siniciando sesión en Bindeo%s'
        ];
    }
}