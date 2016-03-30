<?php

namespace Api\Model;

use Api\Entity\BlockChain;
use Api\Entity\BulkFile;
use Api\Entity\BulkTransaction;
use Bindeo\DataModel\Exceptions;
use Api\Model\General\FilesInterface;
use Api\Repository\RepositoryAbstract;
use \Psr\Log\LoggerInterface;

/**
 * Class StoreData to manage StoreData controller functionality
 * @package Api\Model
 */
class BulkTransactions
{
    /**
     * @var \Api\Repository\BulkTransactions
     */
    private $bulkRepo;

    /**
     * @var \Api\Repository\StoreData
     */
    private $dataRepo;

    /**
     * @var \Api\Model\General\FilesStorage
     */
    private $storage;

    /**
     * @var \Monolog\Logger
     */
    private $logger;

    public function __construct(
        RepositoryAbstract $bulkRepo,
        RepositoryAbstract $dataRepo,
        FilesInterface $storage,
        LoggerInterface $logger
    ) {
        $this->bulkRepo = $bulkRepo;
        $this->dataRepo = $dataRepo;
        $this->storage = $storage;
        $this->logger = $logger;
    }

    /**
     * Save the bulk file in storage and database
     *
     * @param BulkFile $file
     *
     * @return array
     * @throws \Exception
     */
    private function saveBulkFile(BulkFile $file)
    {
        // Transform bytes to kilobytes
        $file->setSize(ceil(filesize($file->getPath()) / 1024));

        // Storage the file in our file system
        try {
            $this->storage->save($file);
        } catch (\Exception $e) {
            throw new \Exception('', 500);
        }

        // Hash the file
        $file->setHash($this->storage->getHash($file));

        // Save the file registry
        try {
            $this->bulkRepo->createFile($file);
        } catch (\Exception $e) {
            // Remove the uploaded file
            $this->storage->delete($file);
            throw $e;
        }
    }

    /**
     * Sign a file into the blockchain
     *
     * @param BulkTransaction $bulk
     *
     * @return array
     * @throws \Exception
     */
    private function signBulkTransaction(BulkTransaction $bulk)
    {
        if (!$bulk->getIdBulkTransaction() or !$bulk->getIp() or !$bulk->getHash() or !$bulk->getStructure()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // We only sign a bulk transaction once
        if ($bulk->getTransaction()) {
            throw new \Exception(Exceptions::ALREADY_SIGNED, 409);
        }

        // Check if the stored hash is correct
        $hash = hash('sha256', $bulk->getStructure());
        if ($hash != $bulk->getHash()) {
            // We need to store the new hash after sign the file
            $this->logger->addNotice('Hash Incongruence', $bulk->toArray());
            $bulk->setHash($hash);
        }

        // Create the transaction
        $blockchain = \Api\Lib\BlockChain\BlockChain::getInstance();
        if ($blockchain->getBalance() <= 0) {
            $this->logger->addError(Exceptions::NO_COINS);
            throw new \Exception(Exceptions::NO_COINS, 503);
        }

        // Setup blockchain object
        $blockchainObj = new BlockChain([
            'ip'         => $bulk->getIp(),
            'net'        => $blockchain->getNet(),
            'idUser'     => $bulk->getIdUser(),
            'idIdentity' => 0,
            'hash'       => $bulk->getHash(),
            'jsonData'   => $bulk->getStructure(),
            'type'       => 'B',
            'idElement'  => $bulk->getIdBulkTransaction()
        ]);

        // Sign
        $res = $blockchain->storeData($blockchainObj->getHash(), 'S');

        // Check if the transaction was ok
        if (!$res['txid']) {
            $this->logger->addError('Error signing a file', $bulk->toArray());
            throw new \Exception('', 500);
        }

        // Save the transaction information
        $blockchainObj->setTransaction($res['txid']);
        $bulk->setTransaction($res['txid']);

        return $this->dataRepo->signAsset($blockchainObj);
    }

    /**
     * Create a new bulk transaction
     *
     * @param BulkTransaction $bulk
     *
     * @return string
     * @throws \Exception
     */
    public function createBulk(BulkTransaction $bulk)
    {
        // First we will verify the whole transaction before move files or do anything
        $this->bulkRepo->verifyBulkTransaction($bulk);
        /** @var BulkFile $file */
        foreach ($bulk->getFiles() as $file) {
            $this->bulkRepo->verifyBulkFile($file);
            if (!$file->getPath() or !file_exists($file->getPath())) {
                throw new \Exception(Exceptions::MISSING_FIELDS, 400);
            }
        }

        // We know at this moment that the data is correct, we can continue the process

        // Create bulk transaction registry
        $this->bulkRepo->createBulk($bulk->setHash('PENDING')->setStructure('PENDING'));

        // Create files associated to the bulk and generate the structure
        // Emitter data, only for the example
        $emitter = ['name' => 'ISDI', 'full_name' => 'Instituto Superior para el Desarrollo de Internet'];

        $structure = ['owner' => $emitter];
        /** @var BulkFile $file */
        foreach ($bulk->getFiles() as $file) {
            // Set bulk transaction data
            $this->saveBulkFile($file->setIdBulk($bulk->getIdBulkTransaction())
                                     ->setIdUser($bulk->getIdUser())
                                     ->setIp($bulk->getIp()));

            // Add data to structure
            $structure['docs'][$file->getUniqueId()] = [
                'hash' => $file->getHash(),
                'to'   => hash('sha256', $file->getFullName())
            ];
        }
        // Set structure and hash
        $bulk->setStructure(json_encode($structure))->setHash(hash('sha256', $bulk->getStructure()));

        // Update bulk transaction
        $this->bulkRepo->updateBulk($bulk);

        // Sign the bulk transaction in blockchain
        $blockchain = $this->signBulkTransaction($bulk);

        return $blockchain->toArray();
    }

    /**
     * Verify file integrity with exhaustive verifications
     *
     * @param BulkFile $file
     *
     * @return array
     * @throws \Exception
     */
    public function verifyFile(BulkFile $file)
    {
        // Check necessary fields
        if (!$file->getUniqueId() and (!$file->getPath() or !is_file($file->getPath()))) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // If we have the uploaded file path, we need to get its hash
        if ($file->getPath()) {
            $file->setHash(hash_file('sha256', $file->getPath()));
        }

        // Get file from database
        $file = $this->bulkRepo->findFile($file);
        if (!$file) {
            return [];
        }

        // Check stored file hash is not corrupted
        if ($file->getHash() != $this->storage->getHash($file)) {
            return [];
        }

        // Get associated bulk transaction
        $bulk = $this->bulkRepo->findBulk(new BulkTransaction(['idBulkTransaction' => $file->getIdBulk()]));
        if (!$bulk or !$bulk->getTransaction()) {
            return [];
        }

        // Check if bulk transaction is not corrupted
        if (hash('sha256', $bulk->getStructure()) != $bulk->getHash()) {
            return [];
        }

        // Check if file actually exists inside bulk structure
        try {
            $structure = json_decode($bulk->getStructure(), true);

            if (!isset($structure['docs'][$file->getUniqueId()]) or
                $structure['docs'][$file->getUniqueId()]['hash'] != $file->getHash()
            ) {
                return [];
            }
        } catch (\Exception $e) {
            return [];
        }

        // Finally check hash against the blockchain
        $blockchain = \Api\Lib\BlockChain\BlockChain::getInstance();
        if (!$blockchain) {
            $this->logger->addError(Exceptions::UNRECHEABLE_BLOCKCHAIN);
            throw new \Exception(Exceptions::UNRECHEABLE_BLOCKCHAIN, 503);
        };
        $res = $blockchain->getDecodedData($bulk->getTransaction());
        if (!isset($res['data']) or $bulk->getHash() != $res['data']) {
            return [];
        }

        return $file->setTransaction($bulk->getTransaction())->toArray();
    }
}