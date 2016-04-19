<?php

namespace Api\Lib\BlockChain;

interface BlockChainClientInterface
{
    public function createRawTransaction($input, $output);

    public function decodeRawTransaction($raw);

    public function getInfo();

    public function getNetworkInfo();

    public function getAccount($address);

    public function getAccountAddress($account);

    public function getBalance($account = null);

    public function getBlock($hash);

    public function getBlockHash($number);

    public function getBlockCount();

    public function getRawChangeAddress();

    public function getRawTransaction($txid);

    public function getTransaction($txid);

    public function getWalletInfo();

    public function listAccounts();

    public function listAddressGroupings();

    public function listTransactions($account = null);

    public function listUnspent($account = null);

    public function move($from, $to, $amount);

    public function sendRawTransaction($raw);

    public function signMessage($address, $message);

    public function signRawTransaction($tx, $output, $key, $sig_hash = null);

    // Complex functionality
    /**
     * Store data in the blockchain
     *
     * @param string $data
     *
     * @return array
     */
    public function storeData($data);

    /**
     * Store signable data creating a colored coin in sigAccount
     *
     * @param string $data
     * @param array  $signers
     * @param string $sigAccount
     *
     * @return array
     */
    public function storeSignableData($data, array $signers, $sigAccount);

    /**
     * Store and transfer property using colored coins
     *
     * @param string $data
     * @param string $type        Type of transfer - G: Genesis, T: Transfer
     * @param string $accountTo   [optional]
     * @param string $accountFrom [optional]
     * @param string $txid        [optional]
     *
     * @return array
     */
    public function storeProperty($data, $type, $accountTo, $accountFrom = null, $txid = null);

    /**
     * Store data in blockchain signed by the account. Final address is selected from a group composed by first n
     * accounts defined by $number
     *
     * @param string $data
     * @param string $account
     * @param int    $number [optional] Number of account addresses used to select origin input
     * @param string $txid   [optional]
     *
     * @return array
     */
    public function storeDataFromAccount($data, $account, $number = 1, $txid = null);

    /**
     * Transfer some coins from one account to another
     *
     * @param float        $amount
     * @param string|array $accountTo     Account name or array of addresses to transfer coins
     * @param int          $numberOutputs [optional] Number of outputs per address
     * @param string       $accountFrom   [optional]
     *
     * @return array
     */
    public function transferCoins($amount, $accountTo, $numberOutputs = 1, $accountFrom = null);

    /**
     * Get stored data from a transaction id
     *
     * @param $txid
     *
     * @return array
     */
    public function getDecodedData($txid);

    /**
     * Get encoded stored data from a transaction id
     *
     * @param $txid
     *
     * @return array
     */
    public function getEncodedData($txid);
}