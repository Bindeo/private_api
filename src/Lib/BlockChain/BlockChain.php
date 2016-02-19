<?php

namespace Api\Lib\BlockChain;

defined('ENV') || define('ENV', 'development');

/**
 * Class BlockChain
 */
class BlockChain
{
    static private $conf;
    static private $instance;
    private        $net;
    /**
     * @var BlockChainClientInterface
     */
    private $client;

    /**
     * Private singleton BlockChain constructor.
     *
     * @param $client
     *
     * @throws \Exception
     */
    private function __construct(BlockChainClientInterface $client, $type)
    {
        $this->client = $client;
        $this->net = $type;
    }

    /**
     * Set the initial conf
     *
     * @param array $conf
     */
    public static function setConf(array $conf)
    {
        self::$conf = $conf;
    }

    /**
     * getInstance singleton and factory method
     *
     * @param string $client
     *
     * @return BlockChain
     */
    public static function getInstance($client = 'bitcoin')
    {
        if (!isset(self::$instance[$client])) {
            if ($client == 'bitcoin' and isset(self::$conf[$client])) {
                self::$instance[$client] = new BlockChain(new BitcoinClient(self::$conf[$client]['host'],
                    self::$conf[$client]['port'], self::$conf[$client]['user']), $client);
            } else {
                return null;
            }
        }

        return self::$instance[$client];
    }

    /**
     * Get the current blockchain net
     * @return string
     */
    public function getNet()
    {
        return $this->net;
    }

    // METHODS
    public function decodeRawTransaction($raw)
    {
        return $this->client->decodeRawTransaction($raw);
    }

    public function getInfo()
    {
        return $this->client->getInfo();
    }

    public function getNetworkInfo()
    {
        return $this->client->getNetworkInfo();
    }

    public function getAccount($address)
    {
        return $this->client->getAccount($address);
    }

    public function getAccountAddress($account)
    {
        return $this->client->getAccountAddress($account);
    }

    public function getBalance($account = null)
    {
        return $account ? $this->client->getBalance($account) : $this->client->getBalance();
    }

    public function getBlock($hash)
    {
        return $this->client->getBlock($hash);
    }

    public function getBlockHash($number)
    {
        return $this->client->getBlockHash($number);
    }

    public function getBlockCount()
    {
        return $this->client->getBlockCount();
    }

    public function getRawChangeAddress()
    {
        return $this->client->getRawChangeAddress();
    }

    public function getRawTransaction($txid, $decoded = 0)
    {
        return $this->client->getRawTransaction($txid, $decoded);
    }

    public function getTransaction($txid)
    {
        return $this->client->getTransaction($txid);
    }

    public function getWalletInfo()
    {
        return $this->client->getWalletInfo();
    }

    public function listAccounts()
    {
        return $this->client->listAccounts();
    }

    public function listAddressGroupings()
    {
        return $this->client->listAddressGroupings();
    }

    public function listTransactions($account = null)
    {
        return $account ? $this->client->listTransactions($account) : $this->client->listTransactions();
    }

    public function listUnspent($account = null)
    {
        return ($account !== null) ? $this->client->listUnspent($account) : $this->client->listUnspent();
    }

    public function move($from, $to, $amount)
    {
        return $this->client->move($from, $to, $amount);
    }

    // Complex functionality
    /**
     * Store data in the blockchain, we can store a Stamp, a Genesis operation or a Transfer operation
     *
     * @param string $data
     * @param string $type        Type of seal - S: Stamp, G: Genesis, T: Transfer
     * @param string $accountTo   [optional]
     * @param string $accountFrom [optional]
     * @param string $txid        [optional]
     *
     * @return array
     */
    public function storeData($data, $type, $accountTo = null, $accountFrom = null, $txid = null)
    {
        return $this->client->storeData($data, $type, $accountTo, $accountFrom, $txid);
    }

    /**
     * Get stored data from a transaction id
     *
     * @param $txid
     *
     * @return array
     */
    public function getDecodedData($txid)
    {
        return $this->client->getDecodedData($txid);
    }

    /**
     * Get stored data from a transaction id
     *
     * @param $txid
     *
     * @return array
     */
    public function getEncodedData($txid)
    {
        return $this->client->getEncodedData($txid);
    }
}