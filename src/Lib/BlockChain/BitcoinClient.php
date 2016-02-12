<?php

namespace Api\Lib\BlockChain;

use Api\Lib\JsonRPC\jsonRPCClient;

class BitcoinClient implements BlockChainClientInterface
{
    const MAX_DATA_LENGTH = 40;
    const TRANSACTION_FEE = 0.000013; // 1300 satoshis
    private $_bitcoin;

    public function __construct($host, $port, $user)
    {
        $this->_bitcoin = new jsonRPCClient('http://bitcoinrpc:' . $user . '@' . $host . ':' . $port);
    }

    public function createRawTransaction($input, $output)
    {
        return $this->_bitcoin->createrawtransaction($input, $output);
    }

    public function decodeRawTransaction($raw)
    {
        return $this->_bitcoin->decoderawtransaction($raw);
    }

    public function getInfo()
    {
        return $this->_bitcoin->getinfo();
    }

    public function getNetworkInfo()
    {
        return $this->_bitcoin->getnetworkinfo();
    }

    public function getAccount($address)
    {
        return $this->_bitcoin->getaccount($address);
    }

    public function getAccountAddress($account)
    {
        return $this->_bitcoin->getaccountaddress($account);
    }

    public function getBalance($account = '*')
    {
        return $this->_bitcoin->getbalance($account);
    }

    public function getBlock($hash)
    {
        return $this->_bitcoin->getblock($hash);
    }

    public function getBlockHash($number)
    {
        return $this->_bitcoin->getblockhash($number);
    }

    public function getBlockCount()
    {
        return $this->_bitcoin->getblockcount();
    }

    public function getRawChangeAddress()
    {
        return $this->_bitcoin->getrawchangeaddress();
    }

    public function getRawTransaction($txid, $decoded = 0)
    {
        return $this->_bitcoin->getrawtransaction($txid, $decoded);
    }

    public function getTransaction($txid)
    {
        return $this->_bitcoin->gettransaction($txid);
    }

    public function getWalletInfo()
    {
        return $this->_bitcoin->getwalletinfo();
    }

    public function listAccounts()
    {
        return $this->_bitcoin->listaccounts();
    }

    public function listTransactions($account = '*')
    {
        return $this->_bitcoin->listtransactions($account);
    }

    public function listUnspent()
    {
        return $this->_bitcoin->listunspent(0);
    }

    public function sendRawTransaction($raw)
    {
        return $this->_bitcoin->sendrawtransaction($raw);
    }

    public function signMessage($address, $message)
    {
        return $this->_bitcoin->signmessage($address, $message);
    }

    public function signRawTransaction($tx, $output = null, $key = null, $sig_hash = null)
    {
        return $this->_bitcoin->signrawtransaction($tx, $output, $key, $sig_hash);
    }

    // Complex functionality

    // PRIVATE METHODS
    /**
     * Select necessary inputs for the given amount
     *
     * @param float $amount
     *
     * @return array
     */
    private function _selectInputs($amount)
    {
        // Select unspent transactions
        $unspentInputs = $this->listUnspent();

        if (!is_array($unspentInputs)) {
            return ['error' => 'Could not retrieve list of unspent inputs'];
        }

        // We choose in first place confirmed transactions and in the same level the one with less amount
        usort($unspentInputs, function ($a, $b) {
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

        // Spend inputs
        $inputAmount = 0;
        $selectedInputs = [];

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
     * Flip Byte Order
     * This function is used to swap the byte ordering from little to big
     * endian, and vice-versa. A byte string, not a reference, is supplied,
     * the byte order reversed, and the string returned.
     *
     * @param string $bytes
     *
     * @return string
     */
    private function _flipByteOrder($bytes)
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
    private function _decToBytes($decimal, $bytes, $reverse = false)
    {
        $hex = dechex($decimal);
        if (strlen($hex) % 2 != 0) {
            $hex = '0' . $hex;
        }

        $hex = str_pad($hex, $bytes * 2, '0', STR_PAD_LEFT);

        return ($reverse == true) ? $this->_flipByteOrder($hex) : $hex;
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
    private function _encodeVint($decimal)
    {
        $hex = dechex($decimal);
        if ($decimal < 253) {
            $hint = $this->_decToBytes($decimal, 1);
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
        return ($numBytes == 0) ? $hint : $hint . $this->_decToBytes($decimal, $numBytes, true);
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
    private function _encodeInputs($vin, $inputCount)
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
                $scriptVarint = $this->_encodeVint($scriptSize);
                $scriptSig = $scriptVarint . $vin[$i]['coinbase'];
            } else {
                // Regular transaction
                $txHash = $this->_flipByteOrder($vin[$i]['txid']);
                $vout = $this->_decToBytes($vin[$i]['vout'], 4, true);

                // Decimal number of bytes
                $scriptSize = strlen($vin[$i]['scriptSig']['hex']) / 2;
                // Create the varint encoding scripts length
                $scriptVarint = $this->_encodeVint($scriptSize);
                $scriptSig = $scriptVarint . $vin[$i]['scriptSig']['hex'];
            }
            // Add the sequence number.
            $sequence = $this->_decToBytes($vin[$i]['sequence'], 4, true);

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
    private function _encodeOutputs($voutArr, $outputCount)
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
            $amount = $this->_decToBytes($satoshis, 8);
            $amount = $this->_flipByteOrder($amount);

            // Number of bytes
            $scriptSize = strlen($voutArr[$i]['scriptPubKey']['hex']) / 2;
            $scriptVarint = $this->_encodeVint($scriptSize);
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
    private function _encodeTransaction($rawTransactionArray)
    {
        $encodedVersion = $this->_decToBytes($rawTransactionArray['version'], 4, true); // TRUE - get little endian

        // $encodedInputs - set the encoded varint, then work out if any input hex is to be displayed.
        $decimalInputsCount = count($rawTransactionArray['vin']);
        $encodedInputs = $this->_encodeVint($decimalInputsCount) . (($decimalInputsCount > 0)
                ? $this->_encodeInputs($rawTransactionArray['vin'], $decimalInputsCount) : '');

        // $encodedOutputs - set varint, then work out if output hex is required.
        $decimalOutputsCount = count($rawTransactionArray['vout']);
        $encodedOutputs = $this->_encodeVint($decimalOutputsCount) . (($decimalInputsCount > 0)
                ? $this->_encodeOutputs($rawTransactionArray['vout'], $decimalOutputsCount) : '');

        // Transaction locktime
        $encodedLocktime = $this->_decToBytes($rawTransactionArray['locktime'], 4, true);

        return $encodedVersion . $encodedInputs . $encodedOutputs . $encodedLocktime;
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
        // Check correct data length
        $dataLen = strlen($data);
        if ($dataLen == 0) {
            return ['error' => 'Some data is required to be stored'];
        } elseif ($dataLen > self::MAX_DATA_LENGTH) {
            return ['error' => 'Data is bigger than ' . self::MAX_DATA_LENGTH . ' bytes'];
        }

        // Get the change address to return coins
        $changeAddress = $this->getRawChangeAddress();

        // Select necessary inputs
        $inputs = $this->_selectInputs(self::TRANSACTION_FEE);
        if (isset($inputs['error'])) {
            return $inputs;
        }

        // Build the transaction
        $amount = $inputs['total'] - self::TRANSACTION_FEE;
        $outputs = $amount > 0 ? [$changeAddress => $amount] : [];
        $txn = $this->createRawTransaction($inputs['inputs'], $outputs);
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
        $txn = $this->_encodeTransaction($txn);

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
    public function getUncodedData($txid)
    {
        $tx = $this->getRawTransaction($txid, 1);
        if (!isset($tx['vout'][1]['scriptPubKey']['hex'])) {
            return ['error' => "Transaction doesn't exist"];
        } else {
            return ['data' => substr(hex2bin($tx['vout'][1]['scriptPubKey']['hex']), 1)];
        }
    }

    /**
     * Get coded stored data from a transaction id
     *
     * @param $txid
     *
     * @return array
     */
    public function getEncodedData($txid)
    {
        $tx = $this->getRawTransaction($txid, 1);
        if (!isset($tx['vout'][1]['scriptPubKey']['hex'])) {
            return ['error' => "Transaction doesn't exist"];
        } else {
            return ['data' => bin2hex(substr(hex2bin($tx['vout'][1]['scriptPubKey']['hex']), 2))];
        }
    }
}