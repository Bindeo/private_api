<?php

namespace Api\Model\Callback;

use Api\Entity\BulkTransaction;
use Api\Entity\File;
use Api\Entity\Signer;
use Api\Languages\TranslateFactory;
use Api\Model\Email\EmailInterface;
use Api\Model\StoreData;
use Api\Repository\RepositoryAbstract;
use Bindeo\DataModel\Exceptions;
use Slim\Views\Twig;
use \Psr\Log\LoggerInterface;
use Slim\Http\Response;

class SignDocument
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

    public function __construct(
        RepositoryAbstract $bulkRepo,
        RepositoryAbstract $dataRepo,
        StoreData $dataModel,
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
    }

    /**
     * Execute callback for process of sign documents. It is called when bulk transaction has been confirmed in
     * blockchain
     *
     * @param BulkTransaction $bulk
     *
     * @throws \Exception
     */
    public function __invoke(BulkTransaction $bulk)
    {
        // Check correct type for this callback procedure
        if ($bulk->getType() != 'Sign Document') {
            throw new \Exception(Exceptions::NON_EXISTENT, 409);
        }

        // Extract the file id
        $id = explode('_', $bulk->getExternalId())[2];

        // Find the file
        if (!($file = $this->dataRepo->findFile((new File())->setIdFile($id)))) {
            throw new \Exception(Exceptions::NON_EXISTENT, 409);
        }

        // Get signers list
        $signers = $this->dataRepo->signersList($file);

        if ($signers->getNumRows() == 0) {
            throw new \Exception(Exceptions::NON_EXISTENT, 409);
        } else {
            $signers = $signers->getRows();
            /** @var Signer[] $signers */
        }

        // Prepare urls
        $url = $this->frontUrls['host'] . (ENV == 'development' ? DEVELOPER . '.' : '') .
               $this->frontUrls['review_contract'];
        $urlLogin = $this->frontUrls['host'] . (ENV == 'development' ? DEVELOPER . '.' : '') .
                    $this->frontUrls['login'];

        // Document creator
        $creator = $this->dataModel->getSignatureCreator($file);

        // Instantiate creator language
        $translate = TranslateFactory::factory($creator->getLang());

        // Send the email to the creator
        $response = $this->view->render(new Response(), 'email/sign_completed.html.twig', [
            'translate'    => $translate,
            'element_name' => $file->getElementName(),
            'user'         => $creator,
            'url'          => $url . '/' . $file->getIdFile()
        ]);

        // Send and email
        try {
            $res = $this->email->sendEmail($creator->getEmail(), $translate->translate('sign_completed_subject'),
                $response->getBody()->__toString());

            if (!$res or $res->http_response_code != 200) {
                $this->logger->addError('Error sending and email', $creator->toArray());
            }
        } catch (\Exception $e) {
            $this->logger->addError('Error sending and email', $creator->toArray());
        }

        // Send emails to signers
        foreach ($signers as $signer) {
            if (!$signer->getCreator()) {
                // Instantiate signer language
                $translate = TranslateFactory::factory($signer->getLang() ? $signer->getLang() : $creator->getLang());

                // Send the email to the signer
                $response = $this->view->render(new Response(), 'email/sign_completed_copy.html.twig', [
                    'translate'    => $translate,
                    'element_name' => $file->getElementName(),
                    'creator'      => $creator,
                    'user'         => $signer,
                    'url'          => $url . '/' . $signer->getToken(),
                    'urlLogin'     => $urlLogin
                ]);

                // Send and email
                try {
                    $res = $this->email->sendEmail($signer->getEmail(),
                        $translate->translate('sign_signed_subject', $signer->getName()),
                        $response->getBody()->__toString());

                    if (!$res or $res->http_response_code != 200) {
                        $this->logger->addError('Error sending and email', $signer->toArray());
                    }
                } catch (\Exception $e) {
                    $this->logger->addError('Error sending and email', $signer->toArray());
                }
            }
        }
    }

}