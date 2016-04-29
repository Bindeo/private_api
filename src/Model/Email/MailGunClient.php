<?php

namespace Api\Model\Email;

use Mailgun\Mailgun;
use Mailgun\Messages\MessageBuilder;

class MailGunClient implements EmailInterface
{
    private $domain;
    private $baseFrom;
    private $client;

    public function __construct($domain, $baseFrom, $key)
    {
        $this->domain = $domain;
        $this->baseFrom = $baseFrom;
        $this->client = new Mailgun($key);
    }

    /**
     * Send an email
     *
     * @param string $to
     * @param string $subject
     * @param string $content
     * @param array  $files   [optional]
     * @param string $from    [optional]
     * @param string $replyTo [optional]
     *
     * @return \stdClass
     * @throws \Mailgun\Messages\Exceptions\MissingRequiredMIMEParameters
     * @throws \Mailgun\Messages\Exceptions\TooManyParameters
     */
    public function sendEmail($to, $subject, $content, $files = [], $from = null, $replyTo = null)
    {
        // Build the message
        $message = new MessageBuilder();
        $message->setFromAddress($from ? $from : $this->baseFrom, ['first' => 'Bindeo']);

        // If we are in development, mails are only sent to @bindeo.com mails
        if (ENV == 'development') {
            if (!preg_match('/.*@bindeo.com$/i', $to)) {
                $to = DEVELOPER . '@bindeo.com';
            }
        }

        $message->addToRecipient($to);
        $message->setSubject($subject);
        $message->setHtmlBody($content);
        if ($replyTo) {
            $message->setReplyToAddress($replyTo);
        }

        // Send the message
        return $this->client->sendMessage($this->domain, $message->getMessage(), $files);
    }
}