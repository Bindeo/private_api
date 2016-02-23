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
     * @param array  $files [optional]
     * @param string $from  [optional]
     *
     * @return \stdClass
     * @throws \Mailgun\Messages\Exceptions\MissingRequiredMIMEParameters
     */
    public function sendEmail($to, $subject, $content, $files = [], $from = null)
    {
        // Build the message
        $message = new MessageBuilder();
        $message->setFromAddress($from ? $from : $this->baseFrom);
        $message->addToRecipient($to);
        $message->setSubject($subject);
        $message->setHtmlBody($content);

        // Send the message
        return $this->client->sendMessage($this->domain, $message->getMessage(), $files);
    }
}