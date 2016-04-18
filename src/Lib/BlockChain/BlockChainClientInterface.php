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

    public function createMultiSigAccount(array $accounts, $account);

    // Complex functionality
    public function storeData($data);

    public function storeProperty($data, $type, $accountTo, $accountFrom = null, $txid = null);

    public function storeDataFromAccount($data, $account, $number = 1);

    public function transferCoins($amount, $accountTo, $numberOutputs = 1, $accountFrom = null);

    public function getDecodedData($txid);

    public function getEncodedData($txid);
}