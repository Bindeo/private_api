<?php

namespace Api\Model;

use Api\Entity\BlockChain;
use Api\Entity\BulkEvent;
use Api\Entity\BulkTransaction;
use Api\Entity\DocsSignature;
use Api\Entity\OAuthClient;
use Api\Entity\Process;
use Api\Entity\ResultSet;
use Api\Entity\SignatureGenerator;
use Api\Entity\SignCode;
use Api\Entity\Signer;
use Api\Entity\User;
use Api\Entity\File;
use Api\Entity\UserIdentity;
use Api\Languages\TranslateFactory;
use Api\Model\Email\EmailInterface;
use Api\Model\General\ScriptsLauncher;
use Api\Model\Phone\PhoneInterface;
use Bindeo\DataModel\Exceptions;
use Api\Model\General\FilesInterface;
use Api\Repository\RepositoryAbstract;
use Bindeo\DataModel\NotarizableInterface;
use Bindeo\DataModel\SignableInterface;
use Bindeo\DataModel\UserInterface;
use Bindeo\Filter\FilesFilter;
use Bindeo\Util\Tools;
use \Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use Slim\Http\Response;

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
     * @var \Api\Repository\Processes
     */
    private $procRepo;

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
     * @var PhoneInterface
     */
    private $phone;

    /**
     * @var Twig
     */
    private $view;

    private $frontUrls;

    public function __construct(
        RepositoryAbstract $dataRepo,
        RepositoryAbstract $usersRepo,
        RepositoryAbstract $oauthRepo,
        RepositoryAbstract $procRepo,
        BulkTransactions $bulkModel,
        FilesInterface $storage,
        LoggerInterface $logger,
        EmailInterface $email,
        PhoneInterface $phone,
        Twig $view,
        array $frontUrls
    ) {
        $this->dataRepo = $dataRepo;
        $this->usersRepo = $usersRepo;
        $this->oauthRepo = $oauthRepo;
        $this->procRepo = $procRepo;
        $this->bulkModel = $bulkModel;
        $this->storage = $storage;
        $this->logger = $logger;
        $this->email = $email;
        $this->phone = $phone;
        $this->view = $view;
        $this->frontUrls = $frontUrls;
    }

    /**
     * Get a file by id
     *
     * @param File $file
     *
     * @return File
     */
    public function getFile(File $file)
    {
        // Get the file
        $file = $this->dataRepo->findFile($file);

        if ($file) {
            // Get the public path
            $file->setPath($this->storage->get($file));
        } else {
            $file = null;
        }

        return $file;
    }

    /**
     * Auxiliary method to implement additional functionality in file creation for files that are going to be signed
     *
     * @param File   $file
     * @param string $lang
     *
     * @return File
     * @throws \Exception
     */
    private function fileToSign(File $file, $lang)
    {
        $signers = $file->getSigners();

        // File is prepared to be signed
        if ($file->getMode() == 'S') {
            // Launch doc conversion script if it did not exist
            if ($file->getExistent()) {
                $file = $this->dataRepo->findFile($file)->setUser($file->getUser())->setIp($file->getIp());
            } else {
                ScriptsLauncher::getInstance()->execBackground('convert-documents.sh ' . $this->storage->get($file));
            }
            /** @var File $file */
            // Generate params array
            $params = (new BulkTransaction())->setType('Sign Document')
                                             ->setClientType($file->getClientType())
                                             ->setIdClient($file->getIdClient())
                                             ->setExternalId(hash('sha256', 'Sign Document ' . time()))
                                             ->setIp($file->getIp())
                                             ->toArray();

            // Generate signature
            $signature = $this->signatureFactory($file);

            // Signature hash
            $params['hash'] = $signature->generateHash();
            // Size in bytes
            $params['size'] = $file->getSize();
            // Number of pages
            $params['pages'] = $file->getPages() ? $file->getPages() : 0;

            // Open bulk transaction
            $bulk = $this->bulkModel->openBulk($params);

            // Associate signers
            $signers = $this->dataRepo->associateSigners($bulk->setSigners($signers)->transformSigners());

            // Calculate account name depending on signers
            if (count($signers) == 1) {
                $bulk->setAccount($signers[0]->getAccount());
            } else {
                // Concatenate signers accounts to generate multisig account name
                $account = '';
                foreach ($signers as $signer) {
                    $account .= $signer->getAccount();
                }
                $bulk->setAccount(hash('sha256', $account));
            }

            // Update bulk transaction with blockchain account and associate files
            $this->bulkModel->associateSignableElements($bulk->setFiles([$file]));

            // Create process representing bulk transaction to sign and add signers as clients
            $process = $this->procRepo->createProcess($bulk);
            $this->procRepo->addProcessClients($process, $signers);
            $this->procRepo->updateProcess($process->generateAdditionalData($signers));

            // Sign file against blockchain
            $blockchain = \Api\Lib\BlockChain\BlockChain::getInstance();
            if ($blockchain->getBalance() <= 0) {
                $this->logger->addError(Exceptions::NO_COINS);
                throw new \Exception(Exceptions::NO_COINS, 503);
            }

            // Get accounts list
            $accounts = [];
            foreach ($signers as $signer) {
                $accounts[] = $signer->getAccount();
            }

            // Generate blockchainObj
            $blockchainObj = $this->blockchainObjFactory($signature, $file, $blockchain->getNet());

            // Notarize and create signable content in blockchain
            $res = $blockchain->storeSignableData($blockchainObj->getHash(), $accounts, $bulk->getAccount());

            // Check if the transaction was ok
            if (!$res['txid']) {
                $this->logger->addError('Error signing a file', $file->toArray());
                throw new \Exception('', 500);
            }

            // Save the transaction information
            $blockchainObj->setTransaction($res['txid']);
            $file->setTransaction($res['txid']);

            // Save blockchain obj
            $this->dataRepo->signAsset($blockchainObj, $bulk->getIdBulkTransaction());

            // Send emails to signers
            $translate = TranslateFactory::factory($lang);

            $url = $this->frontUrls['host'] . (ENV == 'development' ? DEVELOPER . '.' : '') .
                   $this->frontUrls['review_contract'];

            // Send an email by each signer distinct from creator
            foreach ($signers as $signer) {
                if (!$signer->getCreator()) {
                    $response = $this->view->render(new Response(), 'email/sign_request.html.twig', [
                        'translate' => $translate,
                        'filename'  => $file->getFileOrigName(),
                        'creator'   => $file->getUser(),
                        'user'      => $signer,
                        'url'       => $url . '/' . $signer->getToken()
                    ]);

                    // Send and email
                    try {
                        $res = $this->email->sendEmail($signer->getEmail(),
                            $translate->translate('sign_request_subject', $file->getUser()->getName(),
                                $file->getFileOrigName(32)), $response->getBody()->__toString(), [], null,
                            $file->getUser()->getEmail() ? $file->getUser()->getEmail() : null);

                        if (!$res or $res->http_response_code != 200) {
                            $this->logger->addError('Error sending an email',
                                ['signer' => $signer->toArray(), 'response' => $res ? $res->http_response_code : null]);
                        }
                    } catch (\Exception $e) {
                        $this->logger->addError('Error sending an email',
                            ['signer' => $signer->toArray(), 'exception' => $e->getMessage()]);
                    }
                }
            }

            // Return id bulk transaction
            $file->setIdBulk($bulk->getExternalId());
        }

        return $file;
    }

    /**
     * Save the file in storage and database
     *
     * @param File   $file
     * @param string $lang [optional]
     *
     * @return File
     * @throws \Exception
     */
    public function saveFile(File $file, $lang = null)
    {
        // We try to create file first
        if (!in_array($file->getClientType(), ['U', 'C']) or !$file->getIdClient() or !$file->getPath() or
            !file_exists($file->getPath()) or !$file->getFileOrigName() or
            ($file->getMode() == 'S' and !$file->getSigners())
        ) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // We need to check if the client exists and has enough free space
        if ($file->getClientType() == 'U') {
            $user = $this->usersRepo->find((new User())->setIdUser($file->getIdClient()));
            $lang = $user->getLang();
        } else {
            $user = $this->oauthRepo->oauthClient((new OAuthClient())->setIdClient($file->getIdClient()));

            if (!$lang) {
                $lang = 'en_US';
            }
        }
        if (!$user) {
            throw new \Exception(Exceptions::NON_EXISTENT, 409);
        }

        // Set user in file
        $file->setUser($user);

        // Get filesize
        $file->setSize(filesize($file->getPath()));
        if ($user->getType() > 1 and $file->getSize() > ($user->getStorageLeft() * 1024)) {
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

        // If we did not create the file because it already existed, we delete the new file
        if ($file->getExistent()) {
            $this->storage->delete($file);
        }

        // File has been created in mode to be signed
        if ($file->getMode() == 'S') {
            $file = $this->fileToSign($file, $lang);
        } else {
            // Create process representing file to notarize
            $this->procRepo->createProcess($file);
            $file = $this->getFile($file);
        }

        return $file;
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
     * @return ResultSet
     * @throws \Exception
     */
    public function fileList(FilesFilter $filter)
    {
        // Get the list
        $resultset = $this->dataRepo->fileList($filter);

        // Clean no files
        if ($resultset->getNumRows() > 0 and !$resultset->getRows()[0]->getIdFile()) {
            $resultset = new ResultSet(0, 0, []);
        }

        return $resultset;
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
            throw new \Exception(Exceptions::NON_EXISTENT, 409);
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
        $blockchainObj = $this->blockchainObjFactory($signature, $file, $blockchain->getNet());

        // Notarize data
        $res = $blockchain->storeData($blockchainObj->getHash());

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

            if (!$client) {
                throw new \Exception(Exceptions::NON_EXISTENT, 409);
            }

            $signature->setOwnerId($client->getName())->setOwnerName($client->getName());
        }

        return $signature;
    }

    /**
     * Factory method to generate a BlockChain object full of information
     *
     * @param SignatureGenerator $signature
     * @param SignableInterface  $element
     * @param string             $net [optional]
     *
     * @return BlockChain
     */
    private function blockchainObjFactory(SignatureGenerator $signature, SignableInterface $element, $net = 'bitcoin')
    {
        // Generate blockchain object
        return (new BlockChain())->setNet($net)
                                 ->setIp($element->getIp())
                                 ->setIdElement($element->getElementId())
                                 ->setClientType($element->getClientType())
                                 ->setIdClient($element->getIdClient())
                                 ->setIdIdentity($signature->getAuxIdentity())
                                 ->setHash($signature->generateHash())
                                 ->setJsonData($signature->generate(true))
                                 ->setType($signature->getAssetType());
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
        return $blockchain->storeData($data);
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

    /**
     * Get the signature creator
     *
     * @param BulkTransaction $bulk
     *
     * @return UserInterface
     * @throws \Exception
     */
    public function getSignatureCreator(BulkTransaction $bulk)
    {
        if ($bulk->getClientType() == 'U') {
            $user = $this->usersRepo->find((new User())->setIdUser($bulk->getIdClient()));
        } else {
            $user = $this->oauthRepo->oauthClient((new OAuthClient())->setIdClient($bulk->getIdClient()));
        }

        return $user;
    }

    /**
     * Use an existent and valid token to get a signable element
     *
     * @param string $token
     * @param int    $idUser
     *
     * @return array
     * @throws \Exception
     */
    public function getSignableElement($token, $idUser = null)
    {
        if (!$token and !$idUser) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Get the bulk transaction
        $bulk = $this->dataRepo->getSignature($token, $idUser);

        if (!$bulk) {
            throw new \Exception(Exceptions::NON_EXISTENT, 409);
        }

        // Get signable elements
        $elements = $this->dataRepo->getSignedElements($bulk);

        // Get first element of the list
        if (!$elements) {
            throw new \Exception(Exceptions::NON_EXISTENT, 409);
        }

        /** @var SignableInterface $element */
        $element = $elements[0];

        // Save current signer in JSON field
        $element->setSignerJson($bulk->getSigners()[0]->toArray());

        // If signer is the first time that he viewed document, we need to send a email to the creator
        if ($bulk->getSigners()[0]->isSigner() and
            $bulk->getSigners()[0]->getViewed() == 0 and !$bulk->getSigners()[0]->getCreator()
        ) {
            // Get the signature creator
            $creator = $this->getSignatureCreator($bulk);

            if ($creator and $creator->getEmail()) {
                // Send email to the creator
                $translate = TranslateFactory::factory($creator->getLang());

                $url = $this->frontUrls['host'] . (ENV == 'development' ? DEVELOPER . '.' : '') .
                       $this->frontUrls['review_contract'] . '/s' . $bulk->getExternalId();

                // Send an email to the creator
                $response = $this->view->render(new Response(), 'email/sign_viewed.html.twig', [
                    'translate'    => $translate,
                    'element_name' => $element->getElementName(),
                    'datetime'     => (new \DateTime())->format('Y-m-d H:i:s T'),
                    'user'         => $creator,
                    'viewer'       => $bulk->getSigners()[0],
                    'url'          => $url
                ]);

                // Send and email
                try {
                    $res = $this->email->sendEmail($creator->getEmail(),
                        $translate->translate('sign_viewed_subject', $bulk->getSigners()[0]->getName(),
                            $element->getElementName(32)), $response->getBody()->__toString());

                    if (!$res or $res->http_response_code != 200) {
                        $this->logger->addError('Error sending an email',
                            ['creator' => $creator->toArray(), 'response' => $res ? $res->http_response_code : null]);
                    }
                } catch (\Exception $e) {
                    $this->logger->addError('Error sending an email',
                        ['creator' => $creator->toArray(), 'exception' => $e->getMessage()]);
                }
            }
        }

        // Set element path
        if ($element->getElementType() == 'F') {
            /** @var File $element */
            $element->setPagesPreviews($this->storage->pagesPreview($element))
                    ->setPages(count($element->getPagesPreviews()))
                    ->encodePages()
                    ->setPath($this->storage->get($element));
        }

        return $element->toArray();
    }

    /**
     * Get a pin code to sign a document by sending a signer token
     *
     * @param SignCode $code
     *
     * @throws \Exception
     */
    public function getSignCode(SignCode $code)
    {
        // Check data
        if (!$code->getToken() or !in_array($code->getLang(), ['es_ES', 'en_US'])) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Get the bulk transaction
        $bulk = $this->dataRepo->getSignature($code->getToken(), $code->getIdUser());

        if (!$bulk) {
            throw new \Exception(Exceptions::NON_EXISTENT, 409);
        }

        // Get the signable element
        $elements = $this->dataRepo->getSignedElements($bulk);

        if (!$elements) {
            throw new \Exception(Exceptions::NON_EXISTENT, 409);
        }

        // Current signer
        $signer = $bulk->getSigners()[0];

        // Get first element
        /** @var SignableInterface $element */
        $element = $elements[0];

        // Get the sign code
        $code->setMethod(($signer->getPhone() and ENV != 'development') ? 'P' : 'E');
        //$code->setMethod($signer->getPhone() ? 'P' : 'E');
        $code = $this->dataRepo->getFreshSignCode($code);

        // If method is mobile message, we will try to send it first
        if ($code->getMethod() == 'P') {
            // Send a text message
            $res = $this->phone->sendMessage($signer->getPhone(), 'Bindeo PIN code: ' . $code->getCode());

            if (!$res) {
                // Generate a log
                $this->logger->addError('Error sending text message', ['number' => $signer->getPhone()]);

                // Failed in sending phone code so generate an email code
                $code = $this->dataRepo->getFreshSignCode($code->setMethod('E'));
            }
        }

        if ($code->getMethod() == 'E') {
            // Send an email with the code
            $translate = TranslateFactory::factory($code->getLang());

            $response = $this->view->render(new Response(), 'email/sign_code.html.twig', [
                'translate'    => $translate,
                'element_name' => $element->getElementName(),
                'user'         => $signer,
                'code'         => $code->getCode()
            ]);

            // Send an email
            try {
                $res = $this->email->sendEmail($signer->getEmail(),
                    $translate->translate('sign_code_subject', $element->getElementName(32)),
                    $response->getBody()->__toString());

                if (!$res or $res->http_response_code != 200) {
                    $this->logger->addError('Error sending an email',
                        ['signer' => $signer->toArray(), 'response' => $res ? $res->http_response_code : null]);
                }
            } catch (\Exception $e) {
                $this->logger->addError('Error sending an email',
                    ['signer' => $signer->toArray(), 'exception' => $e->getMessage()]);
            }
        }
    }

    /**
     * Sign a document with valid token and pin code
     *
     * @param SignCode $code
     *
     * @return BulkTransaction
     * @throws \Exception
     */
    public function signDocument(SignCode $code)
    {
        // Check data
        if (!$code->getToken() or !$code->getCode() or !$code->getIp() or !$code->getName() or !$code->getDocument()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        $code->clean();

        // Validate received code
        $signer = $this->dataRepo->validateSignCode($code);

        // Get the bulk transaction
        $bulk = $this->dataRepo->getSignature($code->getToken(), $code->getIdUser());

        // Get the signable element
        $elements = $this->dataRepo->getSignedElements($bulk);

        if (!$elements) {
            throw new \Exception(Exceptions::NON_EXISTENT, 409);
        }

        // Get first element
        /** @var SignableInterface $element */
        $element = $elements[0];

        // If element is File and it does not have page count yet, we need to count pages now
        if ($element->getElementType() == 'F' and !$element->getPages()) {
            /** @var File $element */
            // Count pages
            $element->setPages(count($this->storage->pagesPreview($element)));

            // We need to update file and bulk structure
            $this->dataRepo->updateFile($element);
            $structure = $bulk->getStructure(true);
            $structure['document']['pages'] = $element->getPages();
            $bulk->setStructure(Tools::jsonEncode($structure));
        }

        // Get creator
        $creator = $this->getSignatureCreator($bulk);

        // If signer has changed his name or document, we need to update them
        if ($signer->getName() != $code->getName() or $signer->getDocument() != $code->getDocument()) {
            // If signer is a registered user, we need to update his identity
            if ($signer->getIdIdentity()) {
                $this->usersRepo->saveIdentity((new UserIdentity())->setIdIdentity($signer->getIdIdentity())
                                                                   ->setConfirmed(1)
                                                                   ->setValue($signer->getEmail())
                                                                   ->setName($code->getName())
                                                                   ->setDocument($code->getDocument())
                                                                   ->setIp($code->getIp()));
                $signer->setIdIdentity($this->usersRepo->getIdentities((new User())->setIdUser($signer->getIdUser()))
                                                       ->getRows()[0]->getIdIdentity());
            }

            // Update signer with his new info
            $this->dataRepo->updateSigner($signer->setToken($code->getToken())
                                                 ->setName($code->getName())
                                                 ->setDocument($code->getDocument()));
        }

        // Signature data
        $data = [
            'name'        => $signer->getName(),
            'email'       => $signer->getEmail(),
            'id_document' => $signer->getDocument(),
            'ip'          => $code->getIp(),
            'method'      => $signer->getPhone() ? 'phone' : 'email'
        ];

        // If sign code was sent by mobile phone, add the phone number
        if ($signer->getPhone()) {
            $data['phone'] = $signer->getPhone();
        }

        // Additional data if available
        if ($code->getLatitude() and $code->getLongitude()) {
            $data['latitude'] = $code->getLatitude();
            $data['longitude'] = $code->getLongitude();
        }

        // Add the event to the bulk transaction
        $event = (new BulkEvent())->setIdBulk($signer->getIdBulk())
                                  ->setIp($code->getIp())
                                  ->setName('sign_' . $signer->getAccount())
                                  ->setTimestamp(new \DateTime())
                                  ->setData(Tools::jsonEncode($data));

        // If user is registered and logged
        if ($signer->getIdUser()) {
            $event->setClientType('U')->setIdClient($signer->getIdUser());
        }

        // Add the event
        $bulk = $this->bulkModel->addEvent($event->setBulkObj($bulk));

        // Update signature
        $this->dataRepo->signSigner($signer->setDate($event->getDate())->setIp($code->getIp()));

        if ($bulk->getSigners()[0]->getEmail() != $creator->getEmail()) {
            // Send email to creator
            $translate = TranslateFactory::factory($code->getLang());

            $url = $this->frontUrls['host'] . (ENV == 'development' ? DEVELOPER . '.' : '') .
                   $this->frontUrls['review_contract'] . '/s' . $bulk->getExternalId();

            // Send an email to the creator
            $response = $this->view->render(new Response(), 'email/sign_signed.html.twig', [
                'translate'    => $translate,
                'element_name' => $element->getElementName(),
                'datetime'     => $event->getDate()->format('Y-m-d H:i:s T'),
                'signer'       => $bulk->getSigners()[0],
                'user'         => $creator,
                'pending'      => $bulk->getPendingSigners(),
                'url'          => $url
            ]);

            // Send and email
            try {
                $res = $this->email->sendEmail($creator->getEmail(),
                    $translate->translate('sign_signed_subject', $bulk->getSigners()[0]->getName(),
                        $element->getElementName(32)), $response->getBody()->__toString());

                if (!$res or $res->http_response_code != 200) {
                    $this->logger->addError('Error sending an email',
                        ['creator' => $creator->toArray(), 'response' => $res ? $res->http_response_code : null]);
                }
            } catch (\Exception $e) {
                $this->logger->addError('Error sending an email',
                    ['creator' => $creator->toArray(), 'exception' => $e->getMessage()]);
            }
        }

        // Update process
        $signers = $this->dataRepo->signersList($bulk);
        $process = (new Process())->setType('S')
                                  ->setIdElement($bulk->getIdBulkTransaction())
                                  ->generateAdditionalData($signers->getRows());

        // If everyone has signed the document, we close the bulk transaction
        if ($bulk->getPendingSigners() == 0) {
            $this->bulkModel->closeBulk($bulk->setIp($code->getIp()));
            $process->setIdStatus(Process::STATUS_S_SIGNED);
        } else {
            $process->setIdStatus(Process::STATUS_S_NEW);
        }

        $this->procRepo->updateProcess($process);

        return $bulk->setIp(null);
    }

    /**
     * Get a signer through a token
     *
     * @param string $token
     * @param int    $idUser
     *
     * @return Signer
     * @throws \Exception
     */
    public function getSigner($token, $idUser = null)
    {
        return $this->dataRepo->getSigner($token, $idUser);
    }

    /**
     * Get a signature certificate
     *
     * @param BulkTransaction $bulk
     * @param string          $mode 'full' o 'simple' mode
     *
     * @return DocsSignature
     * @throws \Exception
     */
    public function signatureCertificate(BulkTransaction $bulk, $mode)
    {
        // Get bulk transaction if user is allowed
        $bulk = $this->bulkModel->documentSignatureBulk($bulk);

        if (!$bulk) {
            throw new \Exception(Exceptions::FEW_PRIVILEGES, 403);
        }

        // We will store all the information necessary to generate the certificate in a DocsSignature object
        $signature = (new DocsSignature())->setBulk($bulk);

        // In simple mode, we only need information about bulk transaction
        if ($mode == 'simple') {
            return $signature;
        }

        // Blockchain instance
        $net = \Api\Lib\BlockChain\BlockChain::getInstance();
        if (!$net) {
            $this->logger->addError(Exceptions::UNRECHEABLE_BLOCKCHAIN);
            throw new \Exception(Exceptions::UNRECHEABLE_BLOCKCHAIN, 503);
        }

        // Get account address
        $bulk->setAccount($net->getAccountAddress($bulk->getAccount()));

        // Get owner
        $owner = $this->getSignatureCreator($bulk);

        if (!$owner) {
            throw new \Exception(Exceptions::FEW_PRIVILEGES, 403);
        }

        // If owner is user, it is better to get current identity
        if ($owner instanceof User) {
            $owner = $this->usersRepo->getIdentities($owner, true)->getRows()[0];
        }

        // Set owner
        $signature->setIssuer($owner)->setIssuerType($bulk->getClientType());

        // Get involved files
        $files = $this->dataRepo->getSignedElements($bulk);

        if (!$files) {
            throw new \Exception(Exceptions::FEW_PRIVILEGES, 403);
        }

        // Set files
        $signature->setFiles($files);

        // Get signers
        $signers = $this->dataRepo->signersList($bulk);

        if ($signers->getNumRows() == 0) {
            throw new \Exception(Exceptions::FEW_PRIVILEGES, 403);
        }
        $signers = $signers->getRows();
        /** @var Signer[] $signers */

        // Get blockchain addresses
        foreach ($signers as $signer) {
            $signer->setAccount($net->getAccountAddress($signer->getAccount()));
        }

        // Set signers
        $signature->setSigners($signers);

        return $signature;
    }

    /**
     * Get a notarization certificate
     *
     * @param File   $file
     * @param string $mode 'full' o 'simple' mode
     *
     * @return DocsSignature
     * @throws \Exception
     */
    public function notarizationCertificate(File $file, $mode)
    {
        // Get file if user is allowed
        $file = $this->dataRepo->documentNotarization($file);

        if (!$file) {
            throw new \Exception(Exceptions::FEW_PRIVILEGES, 403);
        }

        // We will store all the information necessary to generate the certificate in a DocsSignature object
        $notarization = (new DocsSignature())->setFiles([$file]);

        // Get blockchain transaction
        $blockchain = $this->dataRepo->findTransaction((new BlockChain())->setTransaction($file->getTransaction()));

        if (!$blockchain) {
            throw new \Exception(Exceptions::FEW_PRIVILEGES, 403);
        }

        // Store blockchain transaction
        $notarization->setBlockchain($blockchain);

        // In simple mode, we only need information about file
        if ($mode == 'simple') {
            return $notarization;
        }

        // If owner is user, it is better to get current identity
        if ($file->getClientType() == 'U') {
            $owner = $this->usersRepo->getIdentities((new User())->setIdUser($file->getIdClient()), true)->getRows()[0];
        } else {
            $owner = $this->oauthRepo->oauthClient((new OAuthClient())->setIdClient($file->getIdClient()));
        }

        // Set owner
        $notarization->setIssuer($owner)->setIssuerType($file->getClientType());

        return $notarization;
    }

    /**
     * Validate a mobile phone number
     *
     * @param Signer $signer
     *
     * @return Signer
     * @throws \Exception
     */
    public function validateMobilePhone(Signer $signer)
    {
        // Check data
        if (!$signer->getPhone()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Validate mobile phone
        if (!$this->phone->validateNumber($signer->getPhone())) {
            $signer->setPhone(null);
        }

        return $signer;
    }
}