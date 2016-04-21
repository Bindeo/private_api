<?php

namespace Api\Lib\BlockChain;

use Api\Lib\JsonRPC\jsonRPCClient;

class BitcoinClient implements BlockChainClientInterface
{
    const MAX_DATA_LENGTH = 128; // 80 bytes in hex characters
    const STAMP_FEE       = (ENV == 'development') ? 0.000013 * 10 : 0.000013; // 1300 satoshis
    const SIGNABLE_FEE    = (ENV == 'development') ? 0.000015 * 10 : 0.000015; // 1300 satoshis
    const TRANSACTION_FEE = (ENV == 'development') ? 0.000023 * 10 : 0.000023; // 2300 satoshis
    const BULK_STAMP_FEE  = self::TRANSACTION_FEE;
    const SATOSHI         = 0.00000001; // 1 satoshi

    private $bitcoin;

    public function __construct($host, $port, $user)
    {
        $this->bitcoin = new jsonRPCClient('http://bitcoinrpc:' . $user . '@' . $host . ':' . $port);
    }

    public function createRawTransaction($input, $output)
    {
        return $this->bitcoin->createrawtransaction($input, $output);
    }

    public function decodeRawTransaction($raw)
    {
        return $this->bitcoin->decoderawtransaction($raw);
    }

    public function getInfo()
    {
        return $this->bitcoin->getinfo();
    }

    public function getNetworkInfo()
    {
        return $this->bitcoin->getnetworkinfo();
    }

    public function getAccount($address)
    {
        return $this->bitcoin->getaccount($address);
    }

    public function getAccountAddress($account)
    {
        $address = $this->bitcoin->getaddressesbyaccount($account);

        if (!isset($address[0])) {
            $address = $this->bitcoin->getaccountaddress($account);
        } else {
            $address = $address[0];
        }

        return $address;
    }

    public function getBalance($account = '')
    {
        return $this->bitcoin->getbalance($account, 0);
    }

    public function getBlock($hash)
    {
        return $this->bitcoin->getblock($hash);
    }

    public function getBlockHash($number)
    {
        return $this->bitcoin->getblockhash($number);
    }

    public function getBlockCount()
    {
        return $this->bitcoin->getblockcount();
    }

    public function getRawChangeAddress()
    {
        return $this->bitcoin->getrawchangeaddress();
    }

    public function getRawTransaction($txid, $decoded = 0)
    {
        return $this->bitcoin->getrawtransaction($txid, $decoded);
    }

    public function getTransaction($txid)
    {
        return $this->bitcoin->gettransaction($txid);
    }

    public function getWalletInfo()
    {
        return $this->bitcoin->getwalletinfo();
    }

    public function listAccounts()
    {
        return $this->bitcoin->listaccounts(0);
    }

    public function listAddressGroupings()
    {
        return $this->bitcoin->listaddressgroupings();
    }

    public function listTransactions($account = '*')
    {
        return $this->bitcoin->listtransactions($account);
    }

    public function listUnspent($account = null)
    {
        if ($account !== null) {
            $accounts = is_array($account) ? $account : $this->bitcoin->getaddressesbyaccount($account);
        } else {
            $accounts = [];
        }

        return $this->bitcoin->listunspent(0, 9999999, $accounts);
    }

    public function move($from, $to, $amount)
    {
        return $this->bitcoin->move($from, $to, $amount);
    }

    public function sendRawTransaction($raw)
    {
        return $this->bitcoin->sendrawtransaction($raw);
    }

    public function signMessage($address, $message)
    {
        return $this->bitcoin->signmessage($address, $message);
    }

    public function signRawTransaction($tx, $output = null, $key = null, $sig_hash = null)
    {
        return $this->bitcoin->signrawtransaction($tx, $output, $key, $sig_hash);
    }

    public function dumpPrivKey($address)
    {
        return $this->bitcoin->dumpprivkey($address);
    }

    // Complex functionality

    // PRIVATE METHODS
    private function orderInputs($inputs)
    {
        // We choose in first place confirmed transactions and in the same level the one with less amount
        usort($inputs, function ($a, $b) {
            if ($a['confirmations'] > 0 and $b['confirmations'] == 0) {
                return 1;
            } elseif ($b['confirmations'] > 0 and $a['confirmations'] == 0) {
                return -1;
            } elseif ($a['amount'] > $b['amount']) {
                return 1;
            } elseif ($b['amount'] > $a['amount']) {
                return -1;
            } else {
                return 0;
            }
        });
    }

    /**
     * Select necessary inputs for the given amount, if an account and txid are given, we choose those transactions
     * first
     *
     * @param float  $amount  Minimum amount to spend
     * @param string $account [optional] Look for transaction id from this account as input
     * @param array  $txid    [optional] Look for this transaction id
     *
     * @return array
     */
    private function selectInputs($amount, $account = '', $txid = null)
    {
        // Select unspent transactions
        $unspentInputs = $this->listUnspent();

        if (!is_array($unspentInputs)) {
            return ['error' => 'Could not retrieve list of unspent inputs'];
        }

        $selectedInputs = [];

        // Look for the requested account and txid
        if ($account !== null) {
            // Select unspent inputs from requested account
            $inputs = $this->listUnspent($account);

            if ($txid) {
                // Choose the right transaction
                foreach ($inputs as $input) {
                    if ($input['account'] == $account and $input['txid'] == $txid) {
                        $selectedInputs[] = $input;
                    }
                }
                if (count($selectedInputs) == 0) {
                    return ['error' => 'Could not find requested account and txid'];
                }
            } else {
                $unspentInputs = $inputs;
            }
        }

        // Order inputs array
        $this->orderInputs($unspentInputs);

        // Spend inputs
        $inputAmount = 0;
        foreach ($unspentInputs as $unspentInput) {
            if ($unspentInput['amount'] > 0) {
                $selectedInputs[] = $unspentInput;
                $inputAmount += $unspentInput['amount'];

                // Stop when we have enough coins
                if ($inputAmount >= $amount) {
                    break;
                }
            }
        }

        if ($inputAmount < $amount) {
            return ['error' => 'Not enough funds are available to cover the amount and fee'];
        }

        // Return the inputs
        return ['inputs' => $selectedInputs, 'total' => $inputAmount];
    }

    /**
     * Select previously prepared inputs without take more money, if an account and txid are given, we choose that
     * transaction
     *
     * @param float  $amount
     * @param string $account
     * @param int    $addressesNumber
     * @param string $txid [optional] Transaction id
     *
     * @return array
     */
    private function selectPreparedInputs($amount, $account, $addressesNumber, $txid = null)
    {
        // Select account addresses
        $addresses = $this->bitcoin->getaddressesbyaccount($account);

        // Select first N addresses or generate them if the account doesn't have them
        $total = count($addresses);
        if ($total > $addressesNumber) {
            $addresses = array_slice($addresses, 0, $addressesNumber);
        } elseif ($total < $addressesNumber) {
            // Generate necessary addresses
            for ($i = $addressesNumber - $total; $i > 0; $i--) {
                $this->bitcoin->getnewaddress($account);
            }

            $addresses = $this->bitcoin->getaddressesbyaccount($account);
        }

        // Select addresses unspent inputs
        $baseUnspents = $this->listUnspent($addresses);

        if (!is_array($baseUnspents)) {
            return ['error' => 'Could not retrieve list of unspent inputs'];
        }

        // Select only unspent inputs with requested amount
        $unspentInputs = [];
        foreach ($baseUnspents as $unspent) {
            if (($txid == null or $txid == $unspent['txid']) and
                $unspent['amount'] <= $amount + self::SATOSHI and $unspent['amount'] >= $amount - self::SATOSHI
            ) {
                $unspentInputs[] = $unspent;
            }
        }

        // If we want any unspent input from given account, we transfer new coins
        if (count($unspentInputs) == 0) {
            if ($txid == null) {
                // We need to transfer coins
                $this->transferCoins($amount, $addresses, round(30 / $addressesNumber));
                $unspentInputs = $this->listUnspent($addresses);
            } else {
                // If we want a colored coin and we didn't find it is error
                return ['error' => 'Bad prepared inputs'];
            }
        }

        // Pickup a random entry
        $key = array_rand($unspentInputs);

        // Spend inputs
        $selectedInputs = [$unspentInputs[$key]];

        // Check if the selected input is ok
        if ($selectedInputs[0]['amount'] > $amount + self::SATOSHI or
            $selectedInputs[0]['amount'] < $amount - self::SATOSHI
        ) {
            return ['error' => 'Bad prepared inputs'];
        }

        // Return the inputs
        return ['inputs' => $selectedInputs, 'total' => $selectedInputs[0]['amount']];
    }

    /**
     * Flip Byte Order
     * This function is used to swap the byte ordering from little to big
     * endian, and vice-versa. A byte string, not a reference, is supplied,
     * the byte order reversed, and the string returned.
     *
     * @param string $bytes
     *
     * @return string
     */
    private function flipByteOrder($bytes)
    {
        return implode('', array_reverse(str_split($bytes, 2)));
    }

    /**
     * Decimal to Bytes
     * This function encodes a $decimal number as a $bytes byte long hex string.
     * Byte order can be flipped by setting $reverse to TRUE.
     *
     * @param int     $decimal
     * @param int     $bytes
     * @param boolean $reverse
     *
     * @return string
     */
    private function decToBytes($decimal, $bytes, $reverse = false)
    {
        $hex = dechex($decimal);
        if (strlen($hex) % 2 != 0) {
            $hex = '0' . $hex;
        }

        $hex = str_pad($hex, $bytes * 2, '0', STR_PAD_LEFT);

        return ($reverse == true) ? $this->flipByteOrder($hex) : $hex;
    }

    /**
     * Encode VarInt
     * Accepts a $decimal number and attempts to encode it to a VarInt.
     * https://en.bitcoin.it/wiki/Protocol_specification#Variable_length_integer
     * If the number is less than 0xFD/253, then the varint returned
     *  is the decimal number, encoded as one hex byte.
     * If larger than this number, then the numbers magnitude determines
     * a prefix, out of FD, FE, and FF, depending on the number size.
     * Returns FALSE if the number is bigger than 64bit.
     *
     * @param int $decimal
     *
     * @return string|FALSE
     */
    private function encodeVint($decimal)
    {
        $hex = dechex($decimal);
        if ($decimal < 253) {
            $hint = $this->decToBytes($decimal, 1);
            $numBytes = 0;
        } elseif ($decimal < 65535) {
            $hint = 'fd';
            $numBytes = 2;
        } elseif ($hex < 4294967295) {
            $hint = 'fe';
            $numBytes = 4;
        } elseif ($hex < 18446744073709551615) {
            $hint = 'ff';
            $numBytes = 8;
        } else {
            throw new \InvalidArgumentException('Invalid decimal');
        }

        // If the number needs no extra bytes, just return the 1-byte number.
        // If it needs to indicate a larger integer size (16bit, 32bit, 64bit)
        // then it returns the size hint and the 64bit number.
        return ($numBytes == 0) ? $hint : $hint . $this->decToBytes($decimal, $numBytes, true);
    }

    /**
     * Encode Inputs
     * Accepts a decoded $transaction['vin'] array as input: $vin. Also
     * requires $input count.
     * This function encodes the txid, vout, and script into hex format.
     *
     * @param array $vin
     * @param int   $inputCount
     *
     * @return string
     */
    private function encodeInputs($vin, $inputCount)
    {
        $inputs = '';
        for ($i = 0; $i < $inputCount; $i++) {
            if (isset($vin[$i]['coinbase'])) {
                // Coinbase
                $txHash = '0000000000000000000000000000000000000000000000000000000000000000';
                $vout = 'ffffffff';
                // Decimal number of bytes
                $scriptSize = strlen($vin[$i]['coinbase']) / 2;
                // Varint
                $scriptVarint = $this->encodeVint($scriptSize);
                $scriptSig = $scriptVarint . $vin[$i]['coinbase'];
            } else {
                // Regular transaction
                $txHash = $this->flipByteOrder($vin[$i]['txid']);
                $vout = $this->decToBytes($vin[$i]['vout'], 4, true);

                // Decimal number of bytes
                $scriptSize = strlen($vin[$i]['scriptSig']['hex']) / 2;
                // Create the varint encoding scripts length
                $scriptVarint = $this->encodeVint($scriptSize);
                $scriptSig = $scriptVarint . $vin[$i]['scriptSig']['hex'];
            }
            // Add the sequence number.
            $sequence = $this->decToBytes($vin[$i]['sequence'], 4, true);

            // Append this encoded input to the byte string.
            $inputs .= $txHash . $vout . $scriptSig . $sequence;
        }

        return $inputs;
    }

    /**
     * Encode Outputs
     * This function encodes $tx['vin'] array into hex format. Requires
     * the $voutArr, and also $outputCount - the number of outputs
     * this transaction has.
     *
     * @param array $voutArr
     * @param int   $outputCount
     *
     * @return string|FALSE
     */
    private function encodeOutputs($voutArr, $outputCount)
    {
        // If $voutArr is empty, check if it's MEANT to be before failing.
        if (count($voutArr) == 0) {
            return ($outputCount == 0) ? '' : false;
        }

        $outputs = '';
        for ($i = 0; $i < $outputCount; $i++) {
            $satoshis = $voutArr[$i]['value'];
            // Convert to satoshis
            if (!is_int($satoshis)) {
                $satoshis *= 100000000;
            }
            $amount = $this->decToBytes($satoshis, 8);
            $amount = $this->flipByteOrder($amount);

            // Number of bytes
            $scriptSize = strlen($voutArr[$i]['scriptPubKey']['hex']) / 2;
            $scriptVarint = $this->encodeVint($scriptSize);
            $scriptPubKey = $voutArr[$i]['scriptPubKey']['hex'];

            $outputs .= $amount . $scriptVarint . $scriptPubKey;
        }

        return $outputs;
    }

    /**
     * Encode
     * This function takes an array in a format similar to bitcoind's
     * (and compatible with the output of debug above) and re-encodes it
     * into a raw transaction hex string.
     *
     * @param array $rawTransactionArray
     *
     * @return string
     */
    private function encodeTransaction($rawTransactionArray)
    {
        $encodedVersion = $this->decToBytes($rawTransactionArray['version'], 4, true); // TRUE - get little endian

        // $encodedInputs - set the encoded varint, then work out if any input hex is to be displayed.
        $decimalInputsCount = count($rawTransactionArray['vin']);
        $encodedInputs = $this->encodeVint($decimalInputsCount) .
                         (($decimalInputsCount > 0) ? $this->encodeInputs($rawTransactionArray['vin'],
                             $decimalInputsCount) : '');

        // $encodedOutputs - set varint, then work out if output hex is required.
        $decimalOutputsCount = count($rawTransactionArray['vout']);
        $encodedOutputs = $this->encodeVint($decimalOutputsCount) .
                          (($decimalInputsCount > 0) ? $this->encodeOutputs($rawTransactionArray['vout'],
                              $decimalOutputsCount) : '');

        // Transaction locktime
        $encodedLocktime = $this->decToBytes($rawTransactionArray['locktime'], 4, true);

        return $encodedVersion . $encodedInputs . $encodedOutputs . $encodedLocktime;
    }

    /**
     * Check valid data to store into the blockchain
     *
     * @param $data
     *
     * @return array
     */
    private function checkData($data)
    {
        // Check data type
        if (!ctype_xdigit($data)) {
            return ['error' => 'Data must be hexadecimal'];
        }

        // Check correct data length
        $dataLen = strlen($data);
        if ($dataLen == 0) {
            return ['error' => 'Some data is required to be stored'];
        } elseif ($dataLen > self::MAX_DATA_LENGTH) {
            return ['error' => 'Data is bigger than ' . self::MAX_DATA_LENGTH . ' bytes'];
        } else {
            return ['ok'];
        }
    }

    /**
     * Create and send a blockchain transaction from given input and output arrays
     *
     * @param array $inputs
     * @param array $outputs
     *
     * @return array
     */
    private function createTransaction(array $inputs, array $outputs)
    {
        // Create a raw transaction with all the information
        try {
            $txn = $this->createRawTransaction($inputs, $outputs);
        } catch (\Exception $e) {
            return ['error' => 'Data is not valid'];
        }
        /*
                // Old method to include OP_RETURN data, it allows to include no hexadecimal data
                $txn = $this->decodeRawTransaction($txn);

                // Add OP_RETURN data
                if (isset($txn['vout'])) {
                    $key = unpack('H*', chr($dataLen) . $data);
                }
                $txn['vout'][] = [
                    'value'        => 0,
                    'n'            => count($txn['vout']),
                    'scriptPubKey' => ['hex' => '6a' . reset($key)]
                ];

                // Encode the transaction into raw format
                $txn = $this->encodeTransaction($txn);
        */

        // Sign the transaction
        $txn = $this->signRawTransaction($txn);
        if (!$txn['complete']) {
            return ['error' => 'Could not sign the transaction'];
        }

        // Send the transaction
        $result = $this->sendRawTransaction($txn['hex']);
        if (strlen($result) != 64) {
            return ['error' => 'Could not send the transaction'];
        }

        return ['txid' => $result];
    }


    // PUBLIC METHODS

    /**
     * Store data in the blockchain as OP_RETURN data (nulldata transaction)
     *
     * @param string $data
     *
     * @return array
     */
    public function storeData($data)
    {
        // Check correct types and set the minimum amount
        $amount = self::STAMP_FEE;

        // Check data is valid
        $result = $this->checkData($data);

        if (isset($result['error'])) {
            return $result;
        }

        // Get the change address to return coins
        $changeAddress = $this->getAccountAddress('');

        // Select inputs with enough coins
        $inputs = $this->selectInputs($amount);
        if (isset($inputs['error'])) {
            return $inputs;
        }

        // Build the transaction
        $amount = $inputs['total'] - $amount;

        $outputs = ['data' => $data];

        if ($amount > self::SATOSHI * 10) {
            $outputs[$changeAddress] = $amount;
        }

        // Create the new transaction
        $result = $this->createTransaction($inputs['input'], $outputs);

        return $result;
    }

    /**
     * Create a multisig account from array of accounts
     *
     * @param array  $accounts
     * @param string $multiAccount
     *
     * @return string
     */
    private function createMultiSigAccount(array $accounts, $multiAccount)
    {
        // Get addresses
        $addresses = [];
        foreach ($accounts as $from) {
            $addresses[] = $this->getAccountAddress($from);
        }

        // Create multisig account
        return count($addresses) > 1 ? $this->bitcoin->addmultisigaddress(count($addresses), $addresses, $multiAccount)
            : null;
    }

    /**
     * Store signable data creating a colored coin in sigAccount
     *
     * @param string $data
     * @param array  $signers
     * @param string $sigAccount
     *
     * @return array
     */
    public function storeSignableData($data, array $signers, $sigAccount)
    {
        // Check correct types and set the minimum amount
        $fee = self::SIGNABLE_FEE;
        $colored = self::STAMP_FEE;
        $amount = $fee + $colored;

        // Check data is valid
        $result = $this->checkData($data);

        if (isset($result['error'])) {
            return $result;
        }

        // Get the change address to return coins
        $changeAddress = $this->getAccountAddress('');

        // Select inputs with enough coins
        $inputs = $this->selectInputs($amount);
        if (isset($inputs['error'])) {
            return $inputs;
        }

        // If we have more than one signer we need to create a multisig account
        if (count($signers) > 1) {
            // Check if multisig account already exists
            $res = $this->bitcoin->getaddressesbyaccount($sigAccount);

            // Get existent account or generate a new one
            if (count($res) > 0) {
                $address = $res[0];
            } else {
                $address = $this->createMultiSigAccount($signers, $sigAccount);
            }
        } elseif ($sigAccount == $signers[0]) {
            // Address will be the signer address
            $address = $this->getAccountAddress($sigAccount);
        } else {
            return ['error' => 'Only one signer and wrong sigAccount'];
        }

        if ($changeAddress == $address) {
            return ['error' => 'Signing with default account'];
        }

        // Build the transaction
        $amount = $inputs['total'] - $amount;

        // Prepare outputs with notarized data and colored coin
        $outputs = ['data' => $data, $address => $colored];

        if ($amount > self::SATOSHI * 10) {
            $outputs[$changeAddress] = $amount;
        }

        // Create the new transaction
        $result = $this->createTransaction($inputs['inputs'], $outputs);

        return $result;
    }

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
    public function storeProperty($data, $type, $accountTo, $accountFrom = null, $txid = null)
    {
        // Check data is valid
        $result = $this->checkData($data);

        if (isset($result['error'])) {
            return $result;
        }

        // Check correct types and set the minimum amount
        if ($type == 'G' and $accountTo) {
            $accountFrom = null;
            $txid = null;
            $amount = self::STAMP_FEE + self::SATOSHI;
        } elseif ($type == 'T' and $accountTo and $accountFrom and $txid) {
            $amount = self::TRANSACTION_FEE + self::SATOSHI;
        } else {
            return ['error' => 'Incorrect type or account data'];
        }

        // Get the change address to return coins
        $changeAddress = $this->bitcoin->getaddressesbyaccount("")[0];

        $inputs = $this->selectInputs($amount, $accountFrom, $txid);
        if (isset($inputs['error'])) {
            return $inputs;
        }

        // Build the transaction
        $amount = $inputs['total'] - $amount;

        $outputs = ['data' => $data];
        //$outputs = [];
        if ($amount > 0) {
            $outputs[$changeAddress] = $amount;

            // Set the account target to send him the colored coin
            $userAccount = $this->getAccountAddress($accountTo);
            $outputs[$userAccount] = self::SATOSHI;
        }

        // Create the new transaction
        $result = $this->createTransaction($inputs['input'], $outputs);

        // If the transaction was ok and we did a transaction, we move the satoshi from accountFrom to default
        if (!isset($result['error']) and $accountFrom) {
            $this->move($accountFrom, '', self::SATOSHI);
        }

        return $result;
    }

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
    public function storeDataFromAccount($data, $account, $number = 1, $txid = null)
    {
        // Check data is valid
        $result = $this->checkData($data);

        if (isset($result['error'])) {
            return $result;
        }

        // Amount to transfer
        $amount = $txid ? self::STAMP_FEE : self::BULK_STAMP_FEE;

        // Select input to spend
        $inputs = $this->selectPreparedInputs($amount, $account, $number, $txid);
        if (isset($inputs['error'])) {
            return $inputs;
        }

        // Create the new transaction
        $result = $this->createTransaction($inputs['inputs'], ['data' => $data]);

        return $result;
    }

    /**
     * Auxiliary method to transfer all coins from one account to another
     *
     * @param $accountTo
     * @param $accountFrom
     *
     * @return array
     */
    private function transferAll($accountTo, $accountFrom)
    {
        // Select all the unspent outputs
        $unspents = $this->listUnspent($accountFrom);

        $inputs = ['total' => 0, 'inputs' => []];

        foreach ($unspents as $unspent) {
            $inputs['inputs'][] = $unspent;
            $inputs['total'] += $unspent['amount'];
        }

        // Build output if there are enough money
        $outputs = [];
        if (count($inputs['inputs']) > 0 and
            $inputs['total'] > self::TRANSACTION_FEE * ceil(count($inputs['inputs']) / 5)
        ) {
            $outputs[$this->getAccountAddress($accountTo)] = $inputs['total'] -
                                                             self::TRANSACTION_FEE * ceil(count($inputs['inputs']) / 5);
        }

        // Create raw transaction
        $txn = $this->createRawTransaction($inputs['inputs'], $outputs);

        return $txn;
    }

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
    public function transferCoins($amount, $accountTo, $numberOutputs = 1, $accountFrom = '')
    {
        // Amount 0 means that we need to transfer all coins from one account to another
        if ($amount == 0) {
            $txn = $this->transferAll($accountTo, $accountFrom);
        } else {
            // Get the change address to return coins
            $changeAddress = $this->bitcoin->getaddressesbyaccount($accountFrom)[0];

            // Build the transaction
            $outputs = [];

            $totalOutputs = $numberOutputs;

            // Set the account target to send him the coins
            if (is_array($accountTo)) {
                foreach ($accountTo as $account) {
                    $outputs[$account] = $amount;
                }
                $totalOutputs *= count($accountTo);
            } else {
                $addresses = $this->bitcoin->getaddressesbyaccount($accountTo);
                if (count($addresses) == 0) {
                    $outputs[$this->getAccountAddress($accountTo)] = $amount;
                } else {
                    $outputs[$addresses[0]] = $amount;
                }
            }

            // Calculate amount to spend and select necessary unspent inputs to do it
            $totalAmount = $amount * $totalOutputs + self::TRANSACTION_FEE * ceil($totalOutputs / 30);
            $inputs = $this->selectInputs($totalAmount, $accountFrom);
            if (isset($inputs['error'])) {
                return $inputs;
            }

            // Amount to transfer
            $totalAmount = $inputs['total'] - $totalAmount;

            // Change
            if ($totalAmount > self::SATOSHI) {
                $finalOutputs = array_merge([
                    $changeAddress => (isset($outputs[$changeAddress]) ? $outputs[$changeAddress] : 0) + $totalAmount
                ], $outputs);
            } else {
                $finalOutputs = $outputs;
            }

            // Create a raw transaction with all the information
            $txn = $this->createRawTransaction($inputs['inputs'], $finalOutputs);

            // We need to repeat the outputs
            if ($totalOutputs > 1) {
                // Decode transaction to manipulate its outputs directly
                $decoded = $this->decodeRawTransaction($txn);

                for ($initial = ($totalAmount > self::SATOSHI ? 1 : 0), $i = $initial, $n = count($decoded['vout']);
                     $i < count($accountTo) + $initial; $i++) {
                    $output = $decoded['vout'][$i];

                    for ($j = 1; $j < $numberOutputs; $j++, $n++) {
                        $output['n'] = $n;
                        $decoded['vout'][] = $output;
                    }
                }

                // Increase transaction size in 34 bytes per output
                $decoded['size'] += 34 * ($totalOutputs - 1);

                // Encode transaction
                $txn = $this->encodeTransaction($decoded);
            }
        }

        // Sign the transaction
        $txn = $this->signRawTransaction($txn);
        if (!$txn['complete']) {
            return ['error' => 'Could not sign the transaction'];
        }

        // Send the transaction
        $result = $this->sendRawTransaction($txn['hex']);
        if (strlen($result) != 64) {
            return ['error' => 'Could not send the transaction'];
        }

        return ['txid' => $result];
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
        $tx = $this->getRawTransaction($txid, 1);
        if (!isset($tx['vout'][0]['scriptPubKey']['hex'])) {
            return ['error' => "Transaction doesn't exist"];
        } else {
            return ['data' => substr($tx['vout'][0]['scriptPubKey']['hex'], 4)];
        }
    }

    /**
     * Get encoded stored data from a transaction id
     *
     * @param $txid
     *
     * @return array
     */
    public function getEncodedData($txid)
    {
        $tx = $this->getRawTransaction($txid, 1);
        if (!isset($tx['vout'][0]['scriptPubKey']['hex'])) {
            return ['error' => "Transaction doesn't exist"];
        } else {
            return ['data' => substr(hex2bin($tx['vout'][0]['scriptPubKey']['hex']), 1)];
        }
    }

    public function tests()
    {
        $res = 'in';
        //$res = $this->transferCoins(0, "", 1, 'd8cae0388b1f06d5e59630fdac4efb62165de822ffe44deda8b4a8dcc5b48328');

        /*
        $addresses = [
            $this->getAccountAddress('test'),
            //$this->getAccountAddress('test2'),
            $this->getAccountAddress('test3')
        ];

        $privKeys = [];
        foreach ($addresses as $address) {
            $privKeys[] = $this->dumpPrivKey($address);
        }*/

        //$res = $this->bitcoin->addmultisigaddress(2, $addresses, 'multi_test3');

        //$res = $this->transferCoins(0.001, 'multi_test3', 1, '');

        //$res = $this->transferCoins(0, 'test', 1, 'multi_test');

        /*
                $inputs = $this->listUnspent('multi_test3');
                $outputs = ['data' => 'f9e6f2feda1c4713a23baaed5836e7fae4247f7089bcd3b173255cc1f29fed48'];
                $txn = $this->createRawTransaction($inputs, $outputs);
                $txn = $this->signRawTransaction($txn, $inputs, $privKeys);
                //$txn = $this->signRawTransaction($txn);
                $res = $this->sendRawTransaction($txn['hex']);
        */

        // Obtain multisig description from the transaction
        /*
        $res = $this->getRawTransaction('28bff0c280063fd02efc90e7fdb289ec59b80aa12f4e5624a0c395739618091c', 1);
        $scripts = explode('[ALL]', $res['vin'][0]['scriptSig']['asm']);
        $res = $this->bitcoin->decodescript(trim(array_pop($scripts)));
*/
        print_r($res);

        exit;
    }
}