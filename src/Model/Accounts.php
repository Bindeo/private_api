<?php

namespace Api\Model;

use Api\Entity\ResultSet;
use Api\Entity\User;
use Api\Languages\TranslateFactory;
use Api\Model\Email\EmailInterface;
use Api\Model\General\OAuthRegistry;
use Api\Repository\RepositoryAbstract;
use Bindeo\DataModel\Exceptions;
use \Psr\Log\LoggerInterface;
use Slim\Http\Response;
use Slim\Views\Twig;

/**
 * Class Accounts to manage Accounts controller functionality
 * @package Api\Model
 */
class Accounts
{
    /**
     * @var \Api\Repository\Users
     */
    private $usersRepo;

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

    public function __construct(
        RepositoryAbstract $usersRepo,
        LoggerInterface $logger,
        EmailInterface $email,
        Twig $view
    ) {
        $this->usersRepo = $usersRepo;
        $this->logger = $logger;
        $this->email = $email;
        $this->view = $view;
    }

    /**
     * Login the user
     *
     * @param User $user
     *
     * @return array
     * @throws \Exception
     */
    public function login(User $user)
    {
        $data = $this->usersRepo->login($user, OAuthRegistry::getInstance()->getAppName());

        return $data ? $data->toArray() : [];
    }

    /**
     * Create a new account
     *
     * @param User $user
     *
     * @return array
     * @throws \Exception
     */
    public function create(User $user)
    {
        // Create the user
        $data = $this->usersRepo->create($user);

        $translate = TranslateFactory::factory($user->getLang());

        $response = $this->view->render(new Response(), 'email/registry.html.twig',
            ['translate' => $translate, 'user' => $user, 'token' => $data['token']]);

        // Send and email
        try {
            $res = $this->email->sendEmail($user->getEmail(),
                $translate->translate('registry_subject', $user->getName()), $response->getBody()->__toString());

            if (!$res or $res->http_response_code != 200) {
                $this->logger->addError('Error sending and email', $user);
            }
        } catch (\Exception $e) {
            $this->logger->addError('Error sending and email', $user);
        }

        return $user->setIdUser($data['idUser'])->toArray();
    }

    /**
     * Modify an account
     *
     * @param User $user
     *
     * @return array
     * @throws \Exception
     */
    public function modify(User $user)
    {
        $data = $this->usersRepo->modify($user);

        return $data ? $data->toArray() : [];
    }

    /**
     * Modify an account password
     *
     * @param User $user
     *
     * @return array
     * @throws \Exception
     */
    public function modifyPassword(User $user)
    {
        $data = $this->usersRepo->modifyPassword($user);

        return $data ? $data->toArray() : [];
    }

    /**
     * Reset an account password
     *
     * @param User $user
     *
     * @throws \Exception
     */
    public function resetPassword(User $user)
    {
        // Check the requested params
        if (!$user->getLang()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Create the token
        $token = $this->usersRepo->resetPassword($user);

        // Render the email template
        $translate = TranslateFactory::factory($user->getLang());

        $response = $this->view->render(new Response(), 'email/password-reset.html.twig',
            ['translate' => $translate, 'user' => $user, 'token' => $token]);

        // Send and email
        try {
            $res = $this->email->sendEmail($user->getEmail(),
                $translate->translate('password_subject', $user->getName()), $response->getBody()->__toString());

            if (!$res or $res->http_response_code != 200) {
                $this->logger->addError('Error sending and email', $user);
            }
        } catch (\Exception $e) {
            $this->logger->addError('Error sending and email', $user);
        }
    }

    /**
     * Modify an email account
     *
     * @param User $user
     *
     * @throws \Exception
     */
    public function modifyEmail(User $user)
    {
        // Check data
        if (!$user->getLang() or !$user->getName() or !$user->getEmail()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Modify email
        $token = $this->usersRepo->modifyEmail($user);

        // Send a confirmation
        $translate = TranslateFactory::factory($user->getLang());

        $response = $this->view->render(new Response(), 'email/verification.html.twig',
            ['translate' => $translate, 'user' => $user, 'token' => $token]);

        // Send and email
        try {
            $res = $this->email->sendEmail($user->getEmail(),
                $translate->translate('verification_subject', $user->getName()), $response->getBody()->__toString());

            if (!$res or $res->http_response_code != 200) {
                $this->logger->addError('Error sending and email', $user);
            }
        } catch (\Exception $e) {
            $this->logger->addError('Error sending and email', $user);
        }
    }

    /**
     * Modify an account type
     *
     * @param User $user
     *
     * @return array
     * @throws \Exception
     */
    public function modifyType(User $user)
    {
        $data = $this->usersRepo->modifyType($user);

        return $data ? $data->toArray() : [];
    }

    /**
     * Resend the initial validation token
     *
     * @param User $user
     *
     * @throws \Exception
     */
    public function resendToken(User $user)
    {
        // Check data
        if (!$user->getLang() or !$user->getName() or !$user->getEmail()) {
            throw new \Exception(Exceptions::MISSING_FIELDS, 400);
        }

        // Modify email
        $token = $this->usersRepo->getValidationToken($user);

        // Send a confirmation
        $translate = TranslateFactory::factory($user->getLang());

        $response = $this->view->render(new Response(), 'email/verification.html.twig',
            ['translate' => $translate, 'user' => $user, 'token' => $token]);

        // Send and email
        try {
            $res = $this->email->sendEmail($user->getEmail(),
                $translate->translate('verification_subject', $user->getName()), $response->getBody()->__toString());

            if (!$res or $res->http_response_code != 200) {
                $this->logger->addError('Error sending and email', $user);
            }
        } catch (\Exception $e) {
            $this->logger->addError('Error sending and email', $user);
        }
    }

    /**
     * Validate a token
     *
     * @param string $token
     * @param string $ip
     * @param string $password [optional]
     *
     * @return array
     * @throws \Exception
     */
    public function validateToken($token, $ip, $password = null)
    {
        return $this->usersRepo->validateToken($token, $ip, $password)->toArray();
    }

    /**
     * Delete an account
     *
     * @param User $user
     *
     * @throws \Exception
     */
    public function delete(User $user)
    {
        $this->usersRepo->delete($user);
    }

    /**
     * Get active identities of the user
     *
     * @param User $user
     *
     * @return ResultSet
     * @throws \Exception
     */
    public function getIdentities(User $user)
    {
        return $this->usersRepo->getIdentities($user);
    }
}