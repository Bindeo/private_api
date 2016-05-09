<?php

namespace Api\Model\Phone;

/**
 * This is a phone client factory class
 * Class PhoneManager
 * @package Api\Model\Phone
 */
class PhoneManager
{
    /**
     * Factory method
     *
     * @param string $client Client to use
     * @param array  $conf
     *
     * @return PhoneInterface
     */
    static public function factory($client, $conf)
    {
        if ($client == 'messagebird') {
            return new MessageBirdClient($conf['key']);
        } else {
            return null;
        }
    }
}