<?php

namespace Api\Model;

use Api\Entity\BlockChain;
use Api\Entity\Signature;
use Api\Entity\User;
use Api\Entity\File;
use Bindeo\DataModel\Exceptions;
use Api\Model\General\FilesInterface;
use Api\Repository\RepositoryAbstract;
use Bindeo\DataModel\SignableInterface;
use Bindeo\Filter\FilesFilter;
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
    private $dataRepo;

    /**
     * @var \Api\Repository\Users
     */
    private $usersRepo;

    /**
     * @var \Api\Model\General\FilesStorage
     */
    private $storage;

    /**
     * @var \Monolog\Logger
     */
    private $logger;

    public function __construct(
        RepositoryAbstract $dataRepo,
        RepositoryAbstract $usersRepo,
        FilesInterface $storage,
        LoggerInterface $logger
    ) {
        $this->dataRepo = $dataRepo;
        $this->usersRepo = $usersRepo;
        $this->storage = $storage;
        $this->logger = $logger;
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
        $file = $this->dataRepo->findFile($file);

        if ($file) {
            // Get the public path
            $file->setPath($this->storage->get($file));

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
     * @param File $file
     *
     * @return array
     * @throws \Exception
     */
    public function saveFile(File $file)
    {
        // We try to create file first
        if (!$file->getIdClient() or !$file->getPath() or !file_exists($file->getPath()) or !$file->getFileOrigName()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // We need to check if the user has enough free space
        if (!($user = $this->usersRepo->find(new User(['idUser' => $file->getIdClient()])))) {
            throw new \Exception(Exceptions::NON_EXISTENT, 409);
        }

        // Transform bytes to kilobytes
        $file->setSize(ceil(filesize($file->getPath()) / 1024));
        if ($user->getType() > 1 and $file->getSize() > $user->getStorageLeft()) {
            throw new \Exception(Exceptions::FULL_SPACE, 403);
        }

        // Storage the file in our file system
        try {
            $this->storage->save($file);
        } catch (\Exception $e) {
            throw new \Exception('', 500);
        }

        // Hash the file
        $file->setHash($this->storage->getHash($file));

        // Calculate the media type
        $file->setIdMedia($this->dataRepo->calculateMediaType($file));

        // Save the file registry
        try {
            $this->dataRepo->createFile($file);
        } catch (\Exception $e) {
            // Remove the uploaded file
            $this->storage->delete($file);
            throw $e;
        }

        return $this->getFile($file);
    }

    /**
     * Delete a file o send it to trash
     *
     * @param File $file
     *
     * @throws \Exception
     */
    public function deleteFile(File $file)
    {
        // Delete the registry
        if ($oldFile = $this->dataRepo->deleteFile($file)) {
            // Delete the file if it has been completely deleted
            if ($file->getStatus() == 'D') {
                $this->storage->delete($oldFile);
            }
        }
    }

    /**
     * Get a paginated list of files from one client
     *
     * @param FilesFilter $filter
     *
     * @return \Api\Entity\ResultSet
     * @throws \Exception
     */
    public function fileList(FilesFilter $filter)
    {
        // Get the list
        return $this->dataRepo->fileList($filter);
    }

    /**
     * Sign a file into the blockchain
     *
     * @param File $file
     *
     * @return array
     * @throws \Exception
     */
    public function signFile(File $file)
    {
        if (!$file->getIdFile() or !$file->getIp()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Get the file
        $ip = $file->getIp();
        $file = $this->dataRepo->findFile($file);
        if (!$file) {
            throw new \Exception('', 500);
        }
        $file->setIp($ip);

        // We only sign a file once
        if ($file->getTransaction()) {
            throw new \Exception(Exceptions::ALREADY_SIGNED, 409);
        }

        // Check if the stored hash is correct
        $hash = $this->storage->getHash($file);
        if ($hash != $file->getHash()) {
            // We need to store the new hash after sign the file
            $this->logger->addNotice('Hash Incongruence', $file->toArray());
            $file->setHash($hash);
        }

        // Create the transaction
        $blockchain = \Api\Lib\BlockChain\BlockChain::getInstance();
        if ($blockchain->getBalance() <= 0) {
            $this->logger->addError(Exceptions::NO_COINS);
            throw new \Exception(Exceptions::NO_COINS, 503);
        }

        // Generate signature
        $signature = $this->signatureFactory($file);

        if (!$signature->isValid()) {
            throw new \Exception(Exceptions::NON_EXISTENT, 409);
        }

        // Setup blockchain object
        $blockchainObj = new BlockChain([
            'ip'         => $file->getIp(),
            'net'        => $blockchain->getNet(),
            'idUser'     => $file->getIdClient(),
            'idIdentity' => $signature->getAuxIdentity(),
            'hash'       => $signature->getAssetHash(),
            'jsonData'   => $signature->generate(true),
            'type'       => $signature->getAssetType(),
            'idElement'  => $file->getIdFile()
        ]);

        // Sign
        $res = $blockchain->storeData($signature->generateHash(), 'S');

        // Check if the transaction was ok
        if (!$res['txid']) {
            $this->logger->addError('Error signing a file', $file->toArray());
            throw new \Exception('', 500);
        }

        // Save the transaction information
        $blockchainObj->setTransaction($res['txid']);
        $file->setTransaction($res['txid']);

        return $this->dataRepo->signAsset($blockchainObj)->toArray();
    }

    /**
     * Generate the assert signature
     *
     * @param SignableInterface $assert
     *
     * @return Signature
     * @throws \Exception
     */
    private function signatureFactory(SignableInterface $assert)
    {
        // Get owner data
        $identity = $this->usersRepo->getIdentities(new User(['idUser' => $assert->getIdUser()]), true);
        if ($identity->getNumRows() == 0) {
            throw new \Exception(Exceptions::NON_EXISTENT, 409);
        }

        $signature = new Signature();
        $signature->setAssetHash($assert->getHash())
                  ->setAssetSize($assert->getSize())
                  ->setAssetName($assert->getFileOrigName())
                  ->setAssetType($assert->getType())
                  ->setOwnerId($identity->getRows()[0]->getValue())
                  ->setOwnerName($identity->getRows()[0]->getName())
                  ->setAuxIdentity($identity->getRows()[0]->getIdIdentity());

        return $signature;
    }

    /**
     * Get a blockchain transaction by id from our db
     *
     * @param BlockChain $blockchain
     * @param string     $mode [optional] 'light' mode for only db data, 'full' mode for db data enriched with online
     *                         blockchain info
     *
     * @return array
     * @throws \Exception
     */
    public function getTransaction(BlockChain $blockchain, $mode = 'light')
    {
        // Get the blockchain transaction
        $blockchain = $this->dataRepo->findTransaction($blockchain);

        if ($blockchain) {
            // If we are in full mode we need to recover some extra information from blockchain
            if ($mode == 'full') {
                $basicInfo = $this->getTransactionInfo($blockchain);
                if ($basicInfo) {
                    // If we check transactions too quickly it is possible that it doesn't exist in blockchain yet
                    if (isset($basicInfo['time'])) {
                        $blockchain->setBcDate(\DateTime::createFromFormat('U', $basicInfo['time']));
                    }
                    if (isset($basicInfo['blockhash'])) {
                        $blockchain->setBcBlock($basicInfo['blockhash']);
                    }
                }
                //TODO We need to fill original signer, maybe in information from getrawtransaction -> scriptPubKey and look for the hex with decodescript
            }

            $blockchain = $blockchain->toArray();
        }

        return $blockchain;
    }

    /**
     * Get a blockchain transaction hash by id from blockchain
     *
     * @param BlockChain $blockchain
     *
     * @return string
     * @throws \Exception
     */
    public function getTransactionHash(BlockChain $blockchain)
    {
        if (!$blockchain->getTransaction()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // If net has not been provided, we assume is a transaction from our system, we take it from db
        if (!$blockchain->getNet()) {
            $blockchain = $this->dataRepo->findTransaction($blockchain);
            if (!$blockchain) {
                throw new \Exception(Exceptions::MISSING_FIELDS, 400);
            }
        }

        // Get the blockchain transaction
        $net = \Api\Lib\BlockChain\BlockChain::getInstance($blockchain->getNet());
        if (!$net) {
            $this->logger->addError(Exceptions::UNRECHEABLE_BLOCKCHAIN);
            throw new \Exception(Exceptions::UNRECHEABLE_BLOCKCHAIN, 503);
        };
        // Obtain decoded data from blockchain
        $res = $net->getDecodedData($blockchain->getTransaction());
        if (isset($res['data'])) {
            $res = $res['data'];
        } else {
            $res = '';
        }

        return $res;
    }

    /**
     * Get an extended blockchain transaction by id from blockchain raw transaction
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
            $blockchain = $this->dataRepo->findTransaction($blockchain);
            if (!$blockchain) {
                throw new \Exception(Exceptions::MISSING_FIELDS, 400);
            }
        }

        // Get the blockchain transaction
        $net = \Api\Lib\BlockChain\BlockChain::getInstance($blockchain->getNet());
        if (!$net) {
            $this->logger->addError(Exceptions::UNRECHEABLE_BLOCKCHAIN);
            throw new \Exception(Exceptions::UNRECHEABLE_BLOCKCHAIN, 503);
        };
        // Obtain encoded data from blockchain
        $res = $net->getRawTransaction($blockchain->getTransaction(), 1);

        return $res;
    }

    /**
     * Get an extended blockchain transaction by id from blockchain transaction
     *
     * @param BlockChain $blockchain
     *
     * @return array
     * @throws \Exception
     */
    public function getTransactionInfo(BlockChain $blockchain)
    {
        if (!$blockchain->getTransaction()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // If net has not been provided, we assume is a transaction from our system, we take it from db
        if (!$blockchain->getNet()) {
            $blockchain = $this->dataRepo->findTransaction($blockchain);
            if (!$blockchain) {
                throw new \Exception(Exceptions::MISSING_FIELDS, 400);
            }
        }

        // Get the blockchain transaction
        $net = \Api\Lib\BlockChain\BlockChain::getInstance($blockchain->getNet());
        if (!$net) {
            $this->logger->addError(Exceptions::UNRECHEABLE_BLOCKCHAIN);
            throw new \Exception(Exceptions::UNRECHEABLE_BLOCKCHAIN, 503);
        };
        // Obtain encoded data from blockchain
        $res = $net->getTransaction($blockchain->getTransaction());

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
            $this->logger->addError(Exceptions::UNRECHEABLE_BLOCKCHAIN);
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

    /**
     * Test an asset against the blockchain
     *
     * @param SignableInterface $asset
     *
     * @return array
     * @throws \Exception
     */
    public function testAsset(SignableInterface $asset)
    {
        if ($asset->getType() == 'F') {
            $file = $this->dataRepo->findFile($asset);
        } else {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        if (!$file or !$file->getTransaction()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        if (!($blockchain = $this->dataRepo->findTransaction(new BlockChain(['transaction' => $file->getTransaction()])))) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Generate fresh signature
        $signature = new Signature(json_decode($blockchain->getJsonData(), true));

        if (!$signature->isValid()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $hashBC = $this->getTransactionHash($blockchain);
        $hashSign = $signature->generateHash();

        return [
            'match'           => $hashSign == $hashBC,
            'hash_signature'  => $hashSign,
            'hash_blockchain' => $hashBC,
            'sign_info'       => $signature->generate()
        ];
    }
}