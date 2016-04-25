<?php

namespace Api\Model;

use Api\Entity\BlockChain;
use Api\Entity\BulkEvent;
use Api\Entity\BulkTransaction;
use Api\Entity\OAuthClient;
use Api\Entity\SignatureGenerator;
use Api\Entity\SignCode;
use Api\Entity\Signer;
use Api\Entity\User;
use Api\Entity\File;
use Api\Languages\TranslateFactory;
use Api\Model\Email\EmailInterface;
use Api\Model\General\ScriptsLauncher;
use Bindeo\DataModel\Exceptions;
use Api\Model\General\FilesInterface;
use Api\Repository\RepositoryAbstract;
use Bindeo\DataModel\NotarizableInterface;
use Bindeo\DataModel\SpendingStorageInterface;
use Bindeo\Filter\FilesFilter;
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
     * Auxiliary method to implement additional functionality in file creation for files that are going to be signed
     *
     * @param File   $file
     * @param string $lang
     *
     * @throws \Exception
     */
    private function fileToSign(File $file, $lang)
    {
        // File is prepared to be signed
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

            // Send emails to signers
            $translate = TranslateFactory::factory($lang);

            $url = $this->frontUrls['host'] . (ENV == 'development' ? DEVELOPER . '.' : '') .
                   $this->frontUrls['review_contract'];

            // Send an email by each signer distinct from creator
            for ($i = 1, $count = count($signers); $i < $count; $i++) {
                $response = $this->view->render(new Response(), 'email/sign_request.html.twig', [
                    'translate' => $translate,
                    'filename'  => $file->getFileOrigName(),
                    'creator'   => $signers[0],
                    'user'      => $signers[$i],
                    'url'       => $url
                ]);

                // Send and email
                try {
                    $res = $this->email->sendEmail($signers[$i]->getEmail(),
                        $translate->translate('sign_request_subject', $signers[0]->getName()),
                        $response->getBody()->__toString(), [], null, $signers[0]->getEmail());

                    if (!$res or $res->http_response_code != 200) {
                        $this->logger->addError('Error sending and email', $signers[$i]->toArray());
                    }
                } catch (\Exception $e) {
                    $this->logger->addError('Error sending and email', $signers[$i]->toArray());
                }
            }
        }
    }

    /**
     * Save the file in storage and database
     *
     * @param File   $file
     * @param string $lang [optional]
     *
     * @return array
     * @throws \Exception
     */
    public function saveFile(File $file, $lang = null)
    {
        // We try to create file first
        if (!in_array($file->getClientType(), ['U', 'C']) or !$file->getIdClient() or !$file->getPath() or
            !file_exists($file->getPath()) or !$file->getFileOrigName() or
            ($file->getMode() == 'S' and (!$file->getSigners() or !$lang))
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

        // File has been created in mode to be signed
        if ($file->getMode() == 'S') {
            $this->fileToSign($file, $lang);

            // Launch doc conversion script
            ScriptsLauncher::getInstance()->execBackground('convert-documents.sh ' . $file->getPath());
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
        $blockchainObj = (new BlockChain())->setIp($file->getIp())
                                           ->setNet($blockchain->getNet())
                                           ->setClientType($file->getClientType())
                                           ->setIdClient($file->getIdClient())
                                           ->setIdIdentity($signature->getAuxIdentity())
                                           ->setHash($signature->generateHash())
                                           ->setJsonData($signature->generate(true))
                                           ->setType($signature->getAssetType())
                                           ->setIdElement($file->getIdFile());

        // Signature
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
            $res = $blockchain->storeSignableData($blockchainObj->getHash(), $accounts, $bulk->getAccount());
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

    /**
     * Use an existent and valid token to get a signable element
     *
     * @param $token
     *
     * @return array
     * @throws \Exception
     */
    public function getSignableElement($token)
    {
        if (!$token) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Get the signable element
        $element = $this->dataRepo->getSignableElement($token);

        if (!$element) {
            throw new \Exception(Exceptions::NON_EXISTENT, 409);
        }

        // Save current signer in JSON field
        $element->setSignerJson($element->getSigners()[0]->toArray());

        // If signer is the first time that he viewed document, we need to send a email to the creator
        $res = $this->dataRepo->getSignatureCreator($element);

        if ($res->getNumRows() == 1) {
            /** @var Signer $creator */
            $creator = $res->getRows()[0];

            if ($element->getSigners()[0]->getViewed() == 0 and
                $element->getSigners()[0]->getEmail() != $creator->getEmail()
            ) {
                // Send email to the creator
                $translate = TranslateFactory::factory($creator->getLang());

                $url = $this->frontUrls['host'] . (ENV == 'development' ? DEVELOPER . '.' : '') .
                       $this->frontUrls['review_contract'];

                // Send an email to the creator
                $response = $this->view->render(new Response(), 'email/sign_viewed.html.twig', [
                    'translate'    => $translate,
                    'element_name' => $element->getElementName(),
                    'datetime'     => (new \DateTime())->format('Y-m-d H:i:s T'),
                    'user'         => $creator,
                    'viewer'       => $element->getSigners()[0],
                    'url'          => $url
                ]);

                // Send and email
                try {
                    $res = $this->email->sendEmail($creator->getEmail(),
                        $translate->translate('sign_viewed_subject', $element->getSigners()[0]->getName()),
                        $response->getBody()->__toString());

                    if (!$res or $res->http_response_code != 200) {
                        $this->logger->addError('Error sending and email', $creator->toArray());
                    }
                } catch (\Exception $e) {
                    $this->logger->addError('Error sending and email', $creator->toArray());
                }
            }
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

        // Get the signable element
        $element = $this->dataRepo->getSignableElement($code->getToken());

        if (!$element) {
            throw new \Exception(Exceptions::NON_EXISTENT, 409);
        }

        // Get the sign code
        $code = $this->dataRepo->getFreshSignCode($code);

        // Send an email with the code
        $translate = TranslateFactory::factory($code->getLang());

        // Send an email to the creator
        $response = $this->view->render(new Response(), 'email/sign_code.html.twig', [
            'translate'    => $translate,
            'element_name' => $element->getElementName(),
            'user'         => $element->getSigners()[0],
            'code'         => $code->getCode()
        ]);

        // Send and email
        try {
            $res = $this->email->sendEmail($element->getSigners()[0]->getEmail(),
                $translate->translate('sign_code_subject'), $response->getBody()->__toString());

            if (!$res or $res->http_response_code != 200) {
                $this->logger->addError('Error sending and email', $element->getSigners()[0]->toArray());
            }
        } catch (\Exception $e) {
            $this->logger->addError('Error sending and email', $element->getSigners()[0]->toArray());
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
        if (!$code->getToken() or !$code->getCode() or !$code->getIp()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Validate received code
        $signer = $this->dataRepo->validateSignCode($code);

        // Get the signable element
        $element = $this->dataRepo->getSignableElement($code->getToken());

        // Get creator
        $creator = $this->dataRepo->getSignatureCreator($element);

        // Signature data
        $data = ['name' => $signer->getName(), 'email' => $signer->getEmail(), 'ip' => $code->getIp()];

        // Additional data if available
        if ($code->getLatitude() and $code->getLongitude()) {
            $data['latitude'] = $code->getLatitude();
            $data['longitude'] = $code->getLongitude();
        }

        if ($signer->getPhone()) {
            $data['phone'] = $signer->getPhone();
        }

        // Add the event to the bulk transaction
        $event = (new BulkEvent())->setIdBulk($element->getIdBulk())
                                  ->setIp($code->getIp())
                                  ->setName('sign_' . $signer->getAccount())
                                  ->setTimestamp(new \DateTime())
                                  ->setData(json_encode($data));

        // If user is registered and logged
        if ($signer->getIdUser()) {
            $event->setClientType('U')->setIdClient($signer->getIdUser());
        }

        // Obtain bulk transaction
        $bulk = $this->bulkModel->getBulk((new BulkTransaction())->setIdBulkTransaction($event->getIdBulk()));

        // Set linked transaction if bulk doesn't have it
        if (!$bulk->getLinkedTransaction()) {
            $structure = $bulk->setLinkedTransaction($element->getTransaction())->getStructure(true);

            // Modify structure for Sign Document type of bulk transaction
            $structure['document']['transaction'] = $element->getTransaction();
            $bulk->setStructure(json_encode($structure));
        }

        // Add the event
        $bulk = $this->bulkModel->addEvent($event->setBulkObj($bulk));

        // Update signature
        $this->dataRepo->updateSigner($signer->setDate($event->getDate())->setIp($code->getIp()));

        if ($element->getSigners()[0]->getEmail() != $creator->getRows()[0]->getEmail()) {
            // Send email to creator
            $translate = TranslateFactory::factory($code->getLang());

            $url = $this->frontUrls['host'] . (ENV == 'development' ? DEVELOPER . '.' : '') .
                   $this->frontUrls['review_contract'];

            // Send an email to the creator
            $response = $this->view->render(new Response(), 'email/sign_signed.html.twig', [
                'translate'    => $translate,
                'element_name' => $element->getElementName(),
                'datetime'     => $event->getDate()->format('Y-m-d H:i:s T'),
                'signer'       => $element->getSigners()[0],
                'user'         => $creator->getRows()[0],
                'pending'      => $element->getPendingSigners(),
                'url'          => $url
            ]);

            // Send and email
            try {
                $res = $this->email->sendEmail($creator->getRows()[0]->getEmail(),
                    $translate->translate('sign_signed_subject', $element->getSigners()[0]->getName()),
                    $response->getBody()->__toString());

                if (!$res or $res->http_response_code != 200) {
                    $this->logger->addError('Error sending and email', $creator->getRows()[0]->toArray());
                }
            } catch (\Exception $e) {
                $this->logger->addError('Error sending and email', $creator->getRows()[0]->toArray());
            }
        }

        // If everyone has signed the document, we close the bulk transaction
        if ($element->getPendingSigners() == 0) {
            $this->bulkModel->closeBulk($bulk->setIp($code->getIp()));
        }

        return $bulk->setIp(null);
    }
}