<?php

namespace Api\Model\Phone;

use MessageBird\Client;
use MessageBird\Exceptions\AuthenticateException;
use MessageBird\Exceptions\BalanceException;
use MessageBird\Objects\Message;

class MessageBirdClient implements PhoneInterface
{
    private $client;

    public function __construct($key)
    {
        $this->client = new Client($key);
    }

    /**
     * Send an email
     *
     * @param string $to
     * @param string $content
     *
     * @return bool
     */
    public function sendMessage($to, $content)
    {
        // Send the message
        $message = new Message();
        $message->originator = 'Bindeo';
        $message->recipients = [$to];
        $message->body = $content;

        try {
            $MessageResult = $this->client->messages->create($message);
        } catch (AuthenticateException $e) {
            // That means that your accessKey is unknown
            return false;
        } catch (BalanceException $e) {
            // That means that you are out of credits, so do something about it.
            return false;
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Validate a mobile phone number
     *
     * @param string $number
     *
     * @return bool
     */
    public function validateNumber($number)
    {
        try {
            $this->client->lookup->read($number);
            $res = true;
        } catch (\Exception $e) {
            $res = false;
        }

        return $res;
    }
}