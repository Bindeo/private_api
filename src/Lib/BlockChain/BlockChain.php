<?php

namespace Api\Lib\BlockChain;

defined('ENV') || define('ENV', 'development');

/**
 * Class BlockChain
 */
class BlockChain
{
    static private $_conf;
    static private $_instance;
    private        $_net;
    /**
     * @var BlockChainClientInterface
     */
    private $_client;

    /**
     * Private singleton BlockChain constructor.
     *
     * @param $client
     *
     * @throws \Exception
     */
    private function __construct(BlockChainClientInterface $client, $type)
    {
        $this->_client = $client;
        $this->_net = $type;
    }

    /**
     * Set the initial conf
     *
     * @param array $conf
     */
    public static function setConf(array $conf)
    {
        self::$_conf = $conf;
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
        if (!isset(self::$_instance[$client])) {
            if ($client == 'bitcoin' and isset(self::$_conf[$client])) {
                self::$_instance[$client] = new BlockChain(new BitcoinClient(self::$_conf[$client]['host'],
                    self::$_conf[$client]['port'], self::$_conf[$client]['user']), $client);
            } else {
                return null;
            }
        }

        return self::$_instance[$client];
    }

    /**
     * Get the current blockchain net
     * @return string
     */
    public function getNet()
    {
        return $this->_net;
    }

    // METHODS
    public function decodeRawTransaction($raw)
    {
        return $this->_client->decodeRawTransaction($raw);
    }

    public function getInfo()
    {
        return $this->_client->getInfo();
    }

    public function getNetworkInfo()
    {
        return $this->_client->getNetworkInfo();
    }

    public function getAccount($address)
    {
        return $this->_client->getAccount($address);
    }

    public function getAccountAddress($account)
    {
        return $this->_client->getAccountAddress($account);
    }

    public function getBalance($account = null)
    {
        return $account ? $this->_client->getBalance($account) : $this->_client->getBalance();
    }

    public function getBlock($hash)
    {
        return $this->_client->getBlock($hash);
    }

    public function getBlockHash($number)
    {
        return $this->_client->getBlockHash($number);
    }

    public function getBlockCount()
    {
        return $this->_client->getBlockCount();
    }

    public function getRawChangeAddress()
    {
        return $this->_client->getRawChangeAddress();
    }

    public function getRawTransaction($txid, $decoded = 0)
    {
        return $this->_client->getRawTransaction($txid, $decoded);
    }

    public function getTransaction($txid)
    {
        return $this->_client->getTransaction($txid);
    }

    public function getWalletInfo()
    {
        return $this->_client->getWalletInfo();
    }

    public function listAccounts()
    {
        return $this->_client->listAccounts();
    }

    public function listAddressGroupings()
    {
        return $this->_client->listAddressGroupings();
    }

    public function listTransactions($account = null)
    {
        return $account ? $this->_client->listTransactions($account) : $this->_client->listTransactions();
    }

    public function listUnspent($account = null)
    {
        return ($account !== null) ? $this->_client->listUnspent($account) : $this->_client->listUnspent();
    }

    public function move($from, $to, $amount)
    {
        return $this->_client->move($from, $to, $amount);
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
        return $this->_client->storeData($data, $type, $accountTo, $accountFrom, $txid);
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
        return $this->_client->getDecodedData($txid);
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
        return $this->_client->getEncodedData($txid);
    }
}