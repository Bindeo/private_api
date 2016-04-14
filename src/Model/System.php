<?php

namespace Api\Model;

use Api\Entity\BlockChain;
use Api\Entity\BulkTransaction;
use Api\Entity\BulkType;
use Api\Repository\RepositoryAbstract;
use Bindeo\DataModel\Exceptions;
use Api\Model\Email\EmailInterface;
use Slim\Views\Twig;
use \Psr\Log\LoggerInterface;

/**
 * Class StoreData to manage StoreData controller functionality
 * @package Api\Model
 */
class System
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
     * @var EmailInterface
     */
    private $email;

    /**
     * @var Twig
     */
    private $view;

    /**
     * @var \Monolog\Logger
     */
    private $logger;

    public function __construct(
        RepositoryAbstract $bulkRepo,
        RepositoryAbstract $dataRepo,
        EmailInterface $email,
        Twig $view,
        LoggerInterface $logger
    ) {
        $this->bulkRepo = $bulkRepo;
        $this->dataRepo = $dataRepo;
        $this->email = $email;
        $this->view = $view;
        $this->logger = $logger;
    }

    /**
     * Execute callback procedure for confirmed blockchain transaction
     *
     * @param BlockChain $blockchain
     *
     * @throws \Exception
     */
    private function executeCallbak(BlockChain $blockchain)
    {
        if ($blockchain->getClientType() == 'C' and $blockchain->getType() == 'B') {
            if (($bulk = $this->bulkRepo->getBulk((new BulkTransaction())->setIdBulkTransaction($blockchain->getIdElement()))) and
                ($bulkType = $this->bulkRepo->getType((new BulkType())->setType($bulk->getType())
                                                                      ->setClientType($bulk->getClientType())
                                                                      ->setIdClient($bulk->getIdClient())))
            ) {
                // Execute callback for client
                if ($bulkType->getCallbackType() == 'E') {
                    // Send email
                    $to = ENV == 'development' ? DEVELOPER . '@bindeo.com' : $bulkType->getCallbackValue();

                    // Send and email
                    try {
                        $txt = 'Transaction has been confirmed!<br>' . '<br>Transaction id:' .
                               $blockchain->getTransaction() . '<br>Collection type:' . $bulk->getType() .
                               '<br>Collection id:' . $bulk->getExternalId();
                        $res = $this->email->sendEmail($to, 'Transaction confirmed', $txt);

                        if (!$res or $res->http_response_code != 200) {
                            $this->logger->addError('Error sending and email', $to);
                        }
                    } catch (\Exception $e) {
                        $this->logger->addError('Error sending and email', $to);
                    }
                }
            }
        }
    }

    /**
     * Check pending blockchain transaction looking for confirmations
     */
    public function blockchainConfirmations($net = 'bitcoin')
    {
        // Get unconfirmed transactions from database
        $resultset = $this->dataRepo->unconfirmedTransactions($net);

        // Connect to blockchain
        $blockchain = \Api\Lib\BlockChain\BlockChain::getInstance($net);
        if (!$blockchain) {
            $this->logger->addError(Exceptions::UNRECHEABLE_BLOCKCHAIN);
            throw new \Exception(Exceptions::UNRECHEABLE_BLOCKCHAIN, 503);
        }

        // If we have unconfirmed transactions, we need to check them against the blockchain
        if ($resultset->getNumRows() > 0) {
            /** @var BlockChain $row */
            foreach ($resultset->getRows() as $row) {
                // Obtain encoded data from blockchain
                $res = $blockchain->getTransaction($row->getTransaction());

                // If transaction has confirmations, confirm it
                if (isset($res['confirmations']) and $res['confirmations'] > 0) {
                    $this->dataRepo->confirmTransaction($row);

                    // Execute callback process depending on the client
                    $this->executeCallbak($row);
                }
            }
        }
    }

    /**
     * Transfer coins between accounts
     *
     * @param int    $amount
     * @param string $accountTo
     * @param int    $numberOutputs
     * @param string $accountFrom
     *
     * @throws \Exception
     */
    public function transferCoins($net = 'bitcoin', $amount, $accountTo, $numberOutputs = 1, $accountFrom = '')
    {
        // Connect to blockchain
        $blockchain = \Api\Lib\BlockChain\BlockChain::getInstance($net);
        if (!$blockchain) {
            $this->logger->addError(Exceptions::UNRECHEABLE_BLOCKCHAIN);
            throw new \Exception(Exceptions::UNRECHEABLE_BLOCKCHAIN, 503);
        }

        $blockchain->transferCoins($amount, $accountTo, $numberOutputs, $accountFrom);
    }
}