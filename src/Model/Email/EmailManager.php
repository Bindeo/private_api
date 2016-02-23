<?php

namespace Api\Model\Email;

/**
 * This is an email client factory class
 * Class EmailManager
 * @package Api\Model\Email
 */
class EmailManager
{
    /**
     * Factory method
     *
     * @param string $client Client to use
     * @param array  $conf
     *
     * @return EmailInterface
     */
    static public function factory($client, $conf)
    {
        if ($client == 'mailgun') {
            return new MailGunClient($conf['domain'], $conf['from'], $conf['key']);
        } else {
            return null;
        }
    }
}