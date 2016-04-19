<?php

namespace Api\Model;

use Api\Entity\BlockChain;
use Api\Entity\BulkTransaction;
use Api\Entity\OAuthClient;
use Api\Entity\SignatureGenerator;
use Api\Entity\User;
use Api\Entity\File;
use Api\Model\Email\EmailInterface;
use Bindeo\DataModel\Exceptions;
use Api\Model\General\FilesInterface;
use Api\Repository\RepositoryAbstract;
use Bindeo\DataModel\NotarizableInterface;
use Bindeo\DataModel\SpendingStorageInterface;
use Bindeo\Filter\FilesFilter;
use \Psr\Log\LoggerInterface;
use Slim\Views\Twig;

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
     * @var \Api\Repository\OAuth
     */
    private $oauthRepo;

    /**
     * @var BulkTransactions
     */
    private $bulkModel;

    /**
     * @var \Api\Model\General\FilesStorage
     */
    private $storage;

    /**
     * @var \Monolog\Logger
     */
    private $logger;

    /**
     * @var EmailInterface
     */
    private $email;

    /**
     * @var Twig
     */
    private $view;

    private $frontUrls;

    public function __construct(
        RepositoryAbstract $dataRepo,
        RepositoryAbstract $usersRepo,
        RepositoryAbstract $oauthRepo,
        BulkTransactions $bulkModel,
        FilesInterface $storage,
        LoggerInterface $logger,
        EmailInterface $email,
        Twig $view,
        array $frontUrls
    ) {
        $this->dataRepo = $dataRepo;
        $this->usersRepo = $usersRepo;
        $this->oauthRepo = $oauthRepo;
        $this->bulkModel = $bulkModel;
        $this->storage = $storage;
        $this->logger = $logger;
        $this->email = $email;
        $this->view = $view;
        $this->frontUrls = $frontUrls;
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
        if (!in_array($file->getClientType(), ['U', 'C']) or !$file->getIdClient() or !$file->getPath() or
            !file_exists($file->getPath()) or !$file->getFileOrigName() or
            ($file->getMode() == 'S' and !$file->getSigners())
        ) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Convert signers into array if it needed
        if (!is_array($file->getSigners()) and
            !$file->setSigners(json_decode($file->getSigners(), true))->getSigners()
        ) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // We need to check if the client exists and has enough free space
        if ($file->getClientType() == 'U') {
            $user = $this->usersRepo->find(new User(['idUser' => $file->getIdClient()]));
        } else {
            $user = $this->oauthRepo->oauthClient(new OAuthClient(['idClient' => $file->getIdClient()]));
        }

        /** @var SpendingStorageInterface $user */
        if (!$user) {
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

        // File has been created, if it has signers, associate them
        if ($file->getMode() == 'S') {
            $signers = $this->dataRepo->associateSigners($file);

            // Generate params array
            $params = (new BulkTransaction())->setType('Sign Document')
                                             ->setClientType($file->getClientType())
                                             ->setIdClient($file->getIdClient())
                                             ->setExternalId('Sign_Document' . '_' . $file->getIdFile())
                                             ->setIp($file->getIp())
                                             ->toArray();

            // Generate signature
            $signature = $this->signatureFactory($file);

            // Signature hash
            $params['hash'] = $signature->generateHash();
            // Size in bytes
            $params['size'] = $file->getSize() * 1024;

            // Calculate account name depending on signers
            if (count($signers) == 1) {
                $params['account'] = $signers[0]->getAccount();
            } else {
                // Concatenate signers accounts to generate multisig account name
                $account = '';
                foreach ($signers as $signer) {
                    $account .= $signer->getAccount();
                }
                $params['account'] = hash('sha256', $account);
            }

            // Open bulk transaction
            $bulk = $this->bulkModel->openBulk($params);

            // Update file registry with bulk transaction
            $this->dataRepo->updateFile($file->setIdBulk($bulk['idBulkTransaction']));

            // TODO Send email to signers

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
            // We need to store the new hash after signing the file
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
        $blockchainObj = (new BlockChain())->setIp($file->getIp())
                                           ->setNet($blockchain->getNet())
                                           ->setClientType($file->getClientType())
                                           ->setIdClient($file->getIdClient())
                                           ->setIdIdentity($signature->getAuxIdentity())
                                           ->setHash($signature->generateHash())
                                           ->setJsonData($signature->generate(true))
                                           ->setType($signature->getAssetType())
                                           ->setIdElement($file->getIdFile());

        // Sign
        if ($file->getMode() == 'S') {
            // If file needs to be signed, we need the list of signers
            $signers = $this->dataRepo->signersList($file);
            if ($signers->getNumRows() == 0) {
                throw new \Exception(Exceptions::NON_EXISTENT, 409);
            }

            // Get accounts list
            $accounts = [];
            foreach ($signers->getRows() as $signer) {
                $accounts[] = $signer->getAccount();
            }

            // Get bulk transaction associated
            $bulk = $this->bulkModel->getBulk((new BulkTransaction())->setIdBulkTransaction($file->getIdBulk()));

            // Notarize and create signable content in blockchain
            $res = $blockchain->storeSignableData($blockchainObj->getHash(), $accounts, $bulk['account']);
        } else {
            // Notarize data
            $res = $blockchain->storeData($blockchainObj->getHash());
        }

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
     * @param NotarizableInterface $assert
     *
     * @return SignatureGenerator
     * @throws \Exception
     */
    private function signatureFactory(NotarizableInterface $assert)
    {
        // Create signature
        $signature = new SignatureGenerator();
        $signature->setAssetHash($assert->getHash())
                  ->setAssetSize($assert->getSize())
                  ->setAssetName($assert->getFileOrigName())
                  ->setAssetType($assert->getType());

        // Get owner data
        if ($assert->getClientType() == 'U') {
            // Logged user type
            $identity = $this->usersRepo->getIdentities(new User(['idUser' => $assert->getIdClient()]), true);
            if ($identity->getNumRows() == 0) {
                throw new \Exception(Exceptions::NON_EXISTENT, 409);
            }

            $signature->setOwnerId($identity->getRows()[0]->getValue())
                      ->setOwnerName($identity->getRows()[0]->getName())
                      ->setAuxIdentity($identity->getRows()[0]->getIdIdentity());
        } else {
            // OAuth client type
            $client = $this->oauthRepo->oauthClient(new OAuthClient(['idClient' => $assert->getIdClient()]));

            if ($client->getNumRows() == 0) {
                throw new \Exception(Exceptions::NON_EXISTENT, 409);
            }

            $signature->setOwnerId($client->getRows()[0]->getName())->setOwnerName($client->getRows()[0]->getName());
        }

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
        }
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
     * @param NotarizableInterface $asset
     *
     * @return array
     * @throws \Exception
     */
    public function testAsset(NotarizableInterface $asset)
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
        $signature = new SignatureGenerator(json_decode($blockchain->getJsonData(), true));

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

    /**
     * Save direct data string into blockchain
     *
     * @param $data
     *
     * @return array
     * @throws \Exception
     */
    public function postBlockchainData($data)
    {
        if (!$data) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        if (!($blockchain = \Api\Lib\BlockChain\BlockChain::getInstance('bitcoin'))) {
            $this->logger->addError(Exceptions::UNRECHEABLE_BLOCKCHAIN);
            throw new \Exception(Exceptions::UNRECHEABLE_BLOCKCHAIN, 503);
        }

        // Store string in blockchain
        return $blockchain->storeData($data, 'S');
    }

    /**
     * Get data from blockchain
     *
     * @param $mode
     * @param $txid
     *
     * @return array
     * @throws \Exception
     */
    public function getBlockchainData($mode, $txid)
    {
        if (!in_array($mode, ['basic_info', 'advanced_info', 'data']) or !$txid or !ctype_xdigit($txid)) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        if (!($blockchain = \Api\Lib\BlockChain\BlockChain::getInstance('bitcoin'))) {
            $this->logger->addError(Exceptions::UNRECHEABLE_BLOCKCHAIN);
            throw new \Exception(Exceptions::UNRECHEABLE_BLOCKCHAIN, 503);
        }

        if ($mode == 'data') {
            return $blockchain->getDecodedData($txid);
        } elseif ($mode == 'basic_info') {
            return $blockchain->getTransaction($txid);
        } else {
            return $blockchain->getRawTransaction($txid, 1);
        }
    }
}