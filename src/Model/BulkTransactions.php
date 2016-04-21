<?php

namespace Api\Model;

use Api\Entity\BlockChain;
use Api\Entity\BlockchainInfo;
use Api\Entity\BulkEvent;
use Api\Entity\BulkFile;
use Api\Entity\BulkTransaction;
use Api\Entity\BulkType;
use Bindeo\DataModel\Exceptions;
use Api\Model\General\FilesInterface;
use Api\Repository\RepositoryAbstract;
use \Psr\Log\LoggerInterface;

/**
 * Class BulkTransactions to manage BulkTransactions controller functionality
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
     * Factory method to generate a valid BulkTransaction object depending on the bulk type structure and client
     * permissions
     *
     * @param array $params
     *
     * @return BulkTransaction
     * @throws \Exception
     */
    private function bulkFactory(array $params)
    {
        // Instantiate bulk transaction
        $bulk = new BulkTransaction($params);

        // Check data
        if (!$bulk->getType() or !$bulk->getClientType() or !$bulk->getIdClient()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Check if client is able to use this type of bulk transaction
        $bulkType = $this->bulkRepo->getType((new BulkType())->setClientType($bulk->getClientType())
                                                             ->setIdClient($bulk->getIdClient())
                                                             ->setType($bulk->getType()));
        if (!$bulkType) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Set type object
        $bulk->setTypeObject($bulkType)->setElementsType($bulkType->getElementsType());

        // Build extra info attribute with Bulk Type structure
        $bulkInfo = $bulkType->getBulkInfo(true);
        $defaultInfo = $bulkType->getDefaultInfo(true);
        $extraInfo = [];
        // Fill extra info with given params or default info
        foreach ($bulkInfo['fields'] as $field) {
            if (isset($params[$field])) {
                $extraInfo[$field] = $params[$field];
            } elseif ($defaultInfo and isset($defaultInfo[$field])) {
                $extraInfo[$field] = $defaultInfo[$field];
            } else {
                throw new \Exception(Exceptions::MISSING_FIELDS, 400);
            }
        }
        // Initialize structure
        $structure = [$bulkInfo['title'] => $extraInfo];
        if ($bulk->getElementsType() == 'E') {
            $structure['events'] = [];
        } elseif ($bulk->getElementsType() == 'F') {
            $structure['docs'] = [];
            $bulk->transformFiles();
        }
        $bulk->setStructure(json_encode($structure))->setHash('PENDING');

        return $bulk;
    }

    /**
     * Get the bulk type requested
     *
     * @param BulkType $bulk
     *
     * @return array
     * @throws \Exception
     */
    public function bulkType(BulkType $bulk)
    {
        $bulk = $this->bulkRepo->getType($bulk);

        return $bulk ? $bulk->toArray() : [];
    }

    /**
     * Get the bulk types list of a client
     *
     * @param BulkType $bulk
     *
     * @return \Api\Entity\ResultSet
     * @throws \Exception
     */
    public function bulkTypes(BulkType $bulk)
    {
        return $this->bulkRepo->bulkTypes($bulk);
    }

    /**
     * Open a new bulk transaction
     *
     * @param array $params
     *
     * @return array
     * @throws \Exception
     */
    public function openBulk(array $params)
    {
        // Factory creation
        $bulk = $this->bulkFactory($params);

        // Open the bulk transaction
        $this->bulkRepo->openBulk($bulk);

        return $bulk->toArray();
    }

    /**
     * Close an opened Bulk Transaction
     *
     * @param BulkTransaction $bulk
     *
     * @return array
     * @throws \Exception
     */
    public function closeBulk(BulkTransaction $bulk)
    {
        // Open the bulk transaction
        $bulk = $this->bulkRepo->closeBulk($bulk)->hash();

        // Sign transaction in blockchain
        $this->signBulkTransaction($bulk);

        return $bulk->toArray();
    }

    /**
     * Delete an opened bulk transaction
     *
     * @param BulkTransaction $bulk
     *
     * @throws \Exception
     */
    public function deleteBulk(BulkTransaction $bulk)
    {
        // Open the bulk transaction
        $this->bulkRepo->deleteBulk($bulk);
    }

    /**
     * Get a bulk transaction
     *
     * @param BulkTransaction $bulk
     *
     * @return BulkTransaction
     * @throws \Exception
     */
    public function getBulk(BulkTransaction $bulk)
    {
        // Open the bulk transaction
        return $this->bulkRepo->getBulk($bulk);
    }

    /**
     * Open a new bulk transaction
     *
     * @param BulkEvent $event
     *
     * @return BulkTransaction
     * @throws \Exception
     */
    public function addEvent(BulkEvent $event)
    {
        // Get opened bulk transaction
        $bulk = $event->getBulkObj()
            ? $event->getBulkObj()
            : $this->bulkRepo->getBulk((new BulkTransaction())->setExternalId($event->getBulkExternalId())
                                                              ->setClientType($event->getClientType())
                                                              ->setIdClient($event->getIdClient())
                                                              ->setIdBulkTransaction($event->getIdBulk()));
        if (!$bulk) {
            throw new \Exception(Exceptions::NON_EXISTENT, 409);
        }

        if ($bulk->getClosed() != 0) {
            throw new \Exception(Exceptions::ALREADY_CLOSED, 409);
        }

        // Create bulk event
        $this->bulkRepo->createEvent($event->setIdBulk($bulk->getIdBulkTransaction()));

        // Add event to bulk transaction structure
        $bulk->incNumItems();
        $structure = $bulk->getStructure(true);
        $structure['events'][] = $event->getStructure();
        $bulk->setStructure(json_encode($structure));

        // Update bulk transaction with new elements
        $this->bulkRepo->updateBulk($bulk);

        return $bulk;
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
            if ($e->getCode() == 503) {
                throw $e;
            } else {
                throw new \Exception('Cannot store file', 500);
            }
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
     * Sign a bulk transaction into the blockchain
     *
     * @param BulkTransaction $bulk
     *
     * @return array
     * @throws \Exception
     */
    private function signBulkTransaction(BulkTransaction $bulk)
    {
        if (!$bulk->getIdBulkTransaction() or !$bulk->getIp() or !$bulk->getHash() or !$bulk->getStructure() or
            !$bulk->getClientType() or !$bulk->getIdClient()
        ) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // We only sign a bulk transaction once
        if ($bulk->getTransaction()) {
            throw new \Exception(Exceptions::ALREADY_SIGNED, 409);
        }

        // Check if the stored hash is correct
        $hash = hash('sha256', $bulk->getStructure());
        if ($hash != $bulk->getHash()) {
            // We need to store the new hash after sign the bulk
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
            'clientType' => $bulk->getClientType(),
            'idClient'   => $bulk->getIdClient(),
            'idIdentity' => 0,
            'hash'       => $bulk->getHash(),
            'jsonData'   => $bulk->getStructure(),
            'type'       => 'B',
            'idElement'  => $bulk->getIdBulkTransaction()
        ]);

        // Obtain bulk type
        if (!$bulk->getTypeObject()) {
            $bulk->setTypeObject($this->bulkRepo->getType((new BulkType())->setClientType($bulk->getClientType())
                                                                          ->setIdClient($bulk->getIdClient())
                                                                          ->setType($bulk->getType())));
        }
        if (!$bulk->getTypeObject()) {
            throw new \Exception(Exceptions::NON_EXISTENT, 409);
        }

        // Get account name and info
        $num = 1;
        $accountName = $bulk->getAccount();
        $linkedTransaction = $bulk->getLinkedTransaction() ? $bulk->getLinkedTransaction() : null;

        // For transaction type of Notarization, it's possible to mask company address using several addresses, we look for them
        if ($bulk->getTypeObject()->getAsset() == 'N') {
            if ($bulk->getClientType() == 'C') {
                // Look for client defined info
                $info = $this->bulkRepo->getBCInfo((new BlockchainInfo())->setIdClient($bulk->getIdClient()));

                if (!$info) {
                    throw new \Exception(Exceptions::NON_EXISTENT, 409);
                }

                // Get client number of addresses
                $num = $info->getNumberAddresses();
            } else {
                throw new \Exception(Exceptions::NON_EXISTENT, 409);
            }
        }

        // Sign
        $res = $blockchain->storeDataFromAccount($blockchainObj->getHash(), $accountName, $num, $linkedTransaction);

        // Check if the transaction was ok
        if (!$res['txid']) {
            $this->logger->addError('Error signing a bulk', $bulk->toArray());
            throw new \Exception($res['error'], 500);
        }

        if ($bulk->getClientType() == 'C') {
            // Spend the prepared transaction
            $this->bulkRepo->spendTransaction((new BlockchainInfo())->setIdClient($bulk->getIdClient()));
        }

        // Save the transaction information
        $blockchainObj->setTransaction($res['txid']);
        $bulk->setTransaction($res['txid']);

        return $this->dataRepo->signAsset($blockchainObj);
    }

    /**
     * Create a new bulk transaction in one step
     *
     * @param array $params
     *
     * @return string
     * @throws \Exception
     */
    public function oneStepBulk(array $params)
    {
        // New bulk transaction with factory method
        $bulk = $this->bulkFactory($params);

        // First we will verify the whole transaction before move files or do anything
        $this->bulkRepo->verifyBulkTransaction($bulk);
        if ($bulk->getElementsType() == 'F') {
            /** @var BulkFile $file */
            foreach ($bulk->getFiles() as $file) {
                $this->bulkRepo->verifyBulkFile($file);
                if (!$file->getPath() or !file_exists($file->getPath())) {
                    throw new \Exception(Exceptions::MISSING_FIELDS, 400);
                }
            }
        }

        // We know at this moment data is correct, we can move on with the process

        // Open bulk transaction
        $this->bulkRepo->openBulk($bulk);

        if ($bulk->getElementsType() == 'F') {
            // Create files associated to the bulk and add them to the structure
            $structure = $bulk->getStructure(true);

            /** @var BulkFile $file */
            foreach ($bulk->getFiles() as $file) {
                // Set bulk transaction data
                $this->saveBulkFile($file->setIdBulk($bulk->getIdBulkTransaction())
                                         ->setIdClient($bulk->getIdClient())
                                         ->setIp($bulk->getIp()));

                // Add data to structure
                $structure['docs'][$file->getUniqueId()] = $file->getStructure();
            }
            // Set structure and hash
            $bulk->setStructure(json_encode($structure))->hash();
        }

        // Update bulk transaction with added elements
        $this->bulkRepo->updateBulk($bulk);
        $this->bulkRepo->closeBulk($bulk);

        // Sign the bulk transaction in blockchain
        $this->signBulkTransaction($bulk);

        return $bulk->toArray();
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
        $bulk = $this->bulkRepo->getBulk(new BulkTransaction(['idBulkTransaction' => $file->getIdBulk()]));
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