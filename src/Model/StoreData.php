<?php

namespace Api\Model;

use Api\Entity\BlockChain;
use Api\Entity\User;
use Api\Entity\File;
use Api\Model\General\Exceptions;
use Api\Model\General\FilesInterface;
use Api\Repository\RepositoryAbstract;
use \Psr\Http\Message\UploadedFileInterface;
use \Psr\Log\LoggerInterface;

/**
 * Class StoreData to manage StoreData controller functionality
 * @package Api\Model
 */
class StoreData
{
    /**
     * @var \Api\Repository\StoreData
     */
    private $_dataRepo;

    /**
     * @var \Api\Repository\Users
     */
    private $_clientsRepo;

    /**
     * @var \Api\Model\General\FilesStorage
     */
    private $_storage;

    /**
     * @var \Monolog\Logger
     */
    private $_logger;

    public function __construct(
        RepositoryAbstract $dataRepo,
        RepositoryAbstract $clientsRepo,
        FilesInterface $storage,
        LoggerInterface $logger
    ) {
        $this->_dataRepo = $dataRepo;
        $this->_clientsRepo = $clientsRepo;
        $this->_storage = $storage;
        $this->_logger = $logger;
    }

    /**
     * Get a file by id
     *
     * @param File $file
     *
     * @return array
     */
    public function getFile(File $file)
    {
        // Get the file
        $file = $this->_dataRepo->findFile($file);

        if ($file) {
            // Get the public path
            $file->setPath($this->_storage->get($file->getIdClient(), $file->getName()));

            // Convert the object into an array
            $file = $file->toArray();
        } else {
            $file = [];
        }

        return $file;
    }

    /**
     * Save the file in storage and database

*
*@param \Api\Entity\File              $file
     * @param \Slim\Http\UploadedFile $origFile Original uploaded file

*
*@return array
     * @throws \Exception
     */
    public function saveFile(File $file, UploadedFileInterface $origFile)
    {
        // We try to create file first
        if (!$file->getIdClient() or !$origFile or $origFile->getError()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // We need to check if the user has enough free space
        if (!($client = $this->_clientsRepo->find(new User(['id_client' => $file->getIdClient()])))) {
            throw new \Exception(Exceptions::NON_EXISTENT, 409);
        }

        // Transform bytes to kilobytes
        $file->setSize(ceil($origFile->getSize() / 1024));
        if ($client->getType() > 1 and $file->getSize() > $client->getStorageLeft()) {
            throw new \Exception(Exceptions::FULL_SPACE, 403);
        }

        // Storage the file in our file system
        try {
            $name = $this->_storage->save($file->getIdClient(), $origFile);
        } catch (\Exception $e) {
            throw new \Exception('', 500);
        }

        // Hash the file
        $file->setType('F')->setHash($this->_storage->getHash($file->getIdClient(), $name))->setName($name);

        // Save the file registry
        try {
            $id = $this->_dataRepo->createFile($file);
        } catch (\Exception $e) {
            // Remove the uploaded file
            $this->_storage->delete($file->getIdClient(), $file->getName());
            throw $e;
        }

        return $this->getFile($file->setIdFile($id));
    }

    /**
     * Delete a file if it is not signed yet

*
*@param \Api\Entity\File $file
     *
     *@throws \Exception
     */
    public function deleteFile(File $file)
    {
        // Delete the registry
        if ($file = $this->_dataRepo->deleteFile($file)) {
            // Delete the file if the registry has been completely deleted
            $this->_storage->delete($file->getIdClient(), $file->getName());
        }
    }

    /**
     * Get a paginated list of files from one client
     *
     * @param int $idClient
     * @param int $page
     *
     * @return \Api\Entity\ResultSet
     * @throws \Exception
     */
    public function fileList($idClient, $page)
    {
        if (!is_numeric($idClient) or !is_numeric($page)) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Get the list
        return $this->_dataRepo->fileList($idClient, $page);
    }

    /**
     * Sign a file into the blockchain

*
*@param \Api\Entity\File $file
     *
     * @return array
     * @throws \Exception
     */
    public function signFile(File $file)
    {
        if (!$file->getIdFile() or !$file->getIdClient() or !$file->getIp()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Get the file
        $ip = $file->getIp();
        $file = $this->_dataRepo->findFile($file);
        if (!$file) {
            throw new \Exception('', 500);
        }
        $file->setIp($ip);

        // We only sign a file once
        if ($file->getTransaction()) {
            throw new \Exception(Exceptions::ALREADY_SIGNED, 409);
        }

        // Check if the stored hash is correct
        $hash = $this->_storage->getHash($file->getIdClient(), $file->getName());
        if ($hash != $file->getHash()) {
            // We need to store the new hash after sign the file
            $this->_logger->addNotice('Hash Incongruence', $file->toArray());
            $file->setHash($hash);
        }

        // Create the transaction
        $blockchain = \Api\Lib\BlockChain\BlockChain::getInstance();
        if ($blockchain->getBalance() <= 0) {
            $this->_logger->addError(Exceptions::NO_COINS);
            throw new \Exception(Exceptions::NO_COINS, 503);
        }

        $res = $blockchain->storeData($file->getHash(true));

        // Check if the transaction was ok
        if (!$res['txid']) {
            $this->_logger->addError('Error signing a file', $file->toArray());
            throw new \Exception('', 500);
        }

        // Save the transaction information
        $file->setTransaction($res['txid']);

        return $this->_dataRepo->signFile($file, $blockchain->getNet())->toArray();
    }

    /**
     * Get a blockchain transaction by id from our db

*
*@param \Api\Entity\BlockChain $blockchain


*
*@return array
     */
    public function getTransaction(BlockChain $blockchain)
    {
        // Get the blockchain transaction
        $blockchain = $this->_dataRepo->findTransaction($blockchain);

        if ($blockchain) {
            $blockchain = $blockchain->toArray();
        }

        return $blockchain;
    }

    /**
     * Get a blockchain transaction hash by id from blockchain

*
*@param \Api\Entity\BlockChain $blockchain


*
*@return string
     * @throws \Exception
     */
    public function getTransactionHash(BlockChain $blockchain)
    {
        if (!$blockchain->getTransaction()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // If net has not been provided, we assume is a transaction from our system, we take it from db
        if (!$blockchain->getNet()) {
            $blockchain = $this->_dataRepo->findTransaction($blockchain);
            if (!$blockchain) {
                throw new \Exception(Exceptions::MISSING_FIELDS, 400);
            }
        }

        // Get the blockchain transaction
        $net = \Api\Lib\BlockChain\BlockChain::getInstance($blockchain->getNet());
        if (!$net) {
            $this->_logger->addError(Exceptions::UNRECHEABLE_BLOCKCHAIN);
            throw new \Exception(Exceptions::UNRECHEABLE_BLOCKCHAIN, 503);
        };
        // Obtain encoded data from blockchain
        $res = $net->getEncodedData($blockchain->getTransaction());
        if (isset($res['data'])) {
            $res = $res['data'];
        } else {
            $res = '';
        }

        return $res;
    }

    /**
     * Get an extended blockchain transaction by id from blockchain
     *
     * @param BlockChain $blockchain
     *
     * @return array
     * @throws \Exception
     */
    public function getTransactionExtended(BlockChain $blockchain)
    {
        if (!$blockchain->getTransaction()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // If net has not been provided, we assume is a transaction from our system, we take it from db
        if (!$blockchain->getNet()) {
            $blockchain = $this->_dataRepo->findTransaction($blockchain);
            if (!$blockchain) {
                throw new \Exception(Exceptions::MISSING_FIELDS, 400);
            }
        }

        // Get the blockchain transaction
        $net = \Api\Lib\BlockChain\BlockChain::getInstance($blockchain->getNet());
        if (!$net) {
            $this->_logger->addError(Exceptions::UNRECHEABLE_BLOCKCHAIN);
            throw new \Exception(Exceptions::UNRECHEABLE_BLOCKCHAIN, 503);
        };
        // Obtain encoded data from blockchain
        $res = $net->getRawTransaction($blockchain->getTransaction(), 1);

        return $res;
    }

    /**
     * Return the current bitcoins balance
     * @return int
     * @throws \Exception
     */
    public function getBCBalance()
    {
        if (!($blockchain = \Api\Lib\BlockChain\BlockChain::getInstance('bitcoin'))) {
            $this->_logger->addError(Exceptions::UNRECHEABLE_BLOCKCHAIN);
            throw new \Exception(Exceptions::UNRECHEABLE_BLOCKCHAIN, 503);
        }

        return $blockchain->getBalance();
    }

    /**
     * Test a file against a recorded blockchain transaction
     *
     * @param string $file Path to an uploaded file
     * @param string $net  Blockchain network to get the transaction
     * @param string $txid Transaction id
     *
     * @return array
     * @throws \Exception
     */
    public function testFile($file, $net, $txid)
    {
        if (!in_array($net, ['bitcoin']) or !is_file($file)) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Obtain both hashes
        $hashFile = hash_file('sha256', $file);
        $hashBC = $this->getTransactionHash(new BlockChain(['transaction' => $txid, 'net' => $net]));

        return ['match' => $hashFile == $hashBC, 'hash_file' => $hashFile, 'hash_blockchain' => $hashBC];
    }
}