<?php

namespace Api\Model\Callback;

use Api\Model\Email\EmailInterface;
use Api\Repository\RepositoryAbstract;
use Bindeo\DataModel\DataModelAbstract;
use Bindeo\DataModel\Exceptions;
use Slim\Views\Twig;
use \Psr\Log\LoggerInterface;

class CallbackCaller
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
     * @var \Api\Model\StoreData
     */
    private $dataModel;

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

    private $frontUrls;

    private $callbacks;

    public function __construct(
        RepositoryAbstract $bulkRepo,
        RepositoryAbstract $dataRepo,
        DataModelAbstract $dataModel,
        EmailInterface $email,
        Twig $view,
        LoggerInterface $logger,
        array $frontUrls
    ) {
        $this->bulkRepo = $bulkRepo;
        $this->dataRepo = $dataRepo;
        $this->dataModel = $dataModel;
        $this->email = $email;
        $this->view = $view;
        $this->logger = $logger;
        $this->frontUrls = $frontUrls;

        // Generate callbacks list
        $this->callbacks = [
            'SignDocument' => [
                'class' => 'Api\Model\Callback\SignDocument',
                'params' => [
                    $this->bulkRepo,
                    $this->dataRepo,
                    $this->dataModel,
                    $this->email,
                    $this->view,
                    $this->logger,
                    $this->frontUrls
                ]
            ]
        ];
    }

    /**
     * Execute callback
     *
     * @param string $callback
     * @param mixed  $obj
     *
     * @throws \Exception
     */
    public function call($callback, $obj)
    {
        if (isset($this->callbacks[$callback])) {
            // Instantiate callback class
            $class = $this->callbacks[$callback]['class'];
            $class = new $class(...$this->callbacks[$callback]['params']);

            // Execute callback
            $class($obj);
        } else {
            throw new \Exception(Exceptions::NON_EXISTENT, 409);
        }
    }
}