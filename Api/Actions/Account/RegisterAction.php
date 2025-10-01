<?php declare(strict_types=1);
/**
 * RegisterAction.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Actions\Account;

use \Peneus\Api\Actions\Action;

use \Harmonia\Config;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\ValidationSystem\Validator;
use \Peneus\Api\Traits\TransactionalEmailSender;
use \Peneus\Model\Account;
use \Peneus\Model\PendingAccount;
use \Peneus\Resource;
use \Peneus\Services\AccountService;

/**
 * Registers a new user account and sends an activation email.
 */
class RegisterAction extends Action
{
    use TransactionalEmailSender;

    private readonly Request $request;
    private readonly Database $database;
    private readonly Config $config;
    private readonly Resource $resource;
    private readonly SecurityService $securityService;
    private readonly CookieService $cookieService;

    /**
     * Constructs a new instance by initializing dependencies.
     */
    public function __construct()
    {
        parent::__construct();
        $this->request = Request::Instance();
        $this->database = Database::Instance();
        $this->config = Config::Instance();
        $this->resource = Resource::Instance();
        $this->securityService = SecurityService::Instance();
        $this->cookieService = CookieService::Instance();
    }

    /**
     * @return array{message: string}
     * @throws \RuntimeException
     */
    protected function onExecute(): mixed
    {
        // 1
        $data = $this->validateRequest();
        // 2
        $this->ensureNotRegistered($data->email);
        $this->ensureNotPending($data->email);
        // 3
        try {
            $this->database->WithTransaction(fn() =>
                $this->doRegister($data->email, $data->password, $data->displayName)
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Account registration failed.",
                StatusCode::InternalServerError->value,
                $e
            );
        }
        // 4
        $this->cookieService->DeleteCsrfCookie();
        return [
            'message' =>
                "An account activation link has been sent to your email address."
        ];
    }

    /**
     * @return object{email: string, password: string, displayName: string}
     * @throws \RuntimeException
     */
    protected function validateRequest(): \stdClass
    {
        $validator = new Validator([
            'email' => [
                'required',
                'email'
            ],
            'password' => [
                'required',
                'string',
                'minLength:' . SecurityService::PASSWORD_MIN_LENGTH,
                'maxLength:' . SecurityService::PASSWORD_MAX_LENGTH
            ],
            'displayName' => [
                'required',
                'regex:' . AccountService::DISPLAY_NAME_PATTERN
            ]
        ], [
            'displayName.regex' => "Display name is invalid. It must start"
                . " with a letter or number and may only contain letters,"
                . " numbers, spaces, dots, hyphens, and apostrophes."
        ]);
        $da = $validator->Validate($this->request->FormParams());
        return (object)[
            'email' => $da->GetField('email'),
            'password' => $da->GetField('password'),
            'displayName' => $da->GetField('displayName')
        ];
    }

    /**
     * @param string $email
     * @throws \RuntimeException
     */
    protected function ensureNotRegistered(string $email): void
    {
        if (0 !== Account::Count(
            condition: 'email = :email',
            bindings: ['email' => $email]
        )) {
            throw new \RuntimeException(
                "This account is already registered.",
                StatusCode::Conflict->value
            );
        }
    }

    /**
     * @param string $email
     * @throws \RuntimeException
     */
    protected function ensureNotPending(string $email): void
    {
        if (0 !== PendingAccount::Count(
            condition: 'email = :email',
            bindings: ['email' => $email]
        )) {
            throw new \RuntimeException(
                "This account is already awaiting activation.",
                StatusCode::Conflict->value
            );
        }
    }

    /**
     * @param string $email
     * @param string $password
     * @param string $displayName
     * @throws \RuntimeException
     */
    protected function doRegister(
        string $email,
        string $password,
        string $displayName
    ): void
    {
        // 1
        $activationCode = $this->securityService->GenerateToken();
        // 2
        $pa = $this->constructPendingAccount(
            $email,
            $password,
            $displayName,
            $activationCode
        );
        if (!$pa->Save()) {
            throw new \RuntimeException("Failed to save pending account.");
        }
        // 3
        if (!$this->sendEmail($email, $displayName, $activationCode)) {
            throw new \RuntimeException("Failed to send email.");
        }
    }

    /**
     * @param string $email
     * @param string $password
     * @param string $displayName
     * @param string $activationCode
     * @return PendingAccount
     */
    protected function constructPendingAccount(
        string $email,
        string $password,
        string $displayName,
        string $activationCode
    ): PendingAccount
    {
        $pa = new PendingAccount();
        $pa->email = $email;
        $pa->passwordHash = $this->securityService->HashPassword($password);
        $pa->displayName = $displayName;
        $pa->activationCode = $activationCode;
        $pa->timeRegistered = new \DateTime(); // now
        return $pa;
    }

    /**
     * @param string $email
     * @param string $displayName
     * @param string $activationCode
     * @return bool
     */
    protected function sendEmail(
        string $email,
        string $displayName,
        string $activationCode
    ): bool
    {
        $appName = $this->config->OptionOrDefault('AppName', '');
        $actionUrl = $this->resource
            ->PageUrl('activate-account')
            ->Extend($activationCode)
            ->__toString();
        $substitutions = [
            'heroText' =>
                "Welcome to {$appName}!",
            'introText' =>
                "You're almost there! Just click the button below to"
              . " activate your account.",
            'buttonText' =>
                "Activate My Account",
            'disclaimerText' =>
                "You received this email because your email address was"
              . " used to register on {$appName}. If this wasn't you, you"
              . " can safely ignore this email."
        ];
        return $this->sendTransactionalEmail(
            $email,
            $displayName,
            $actionUrl,
            $substitutions
        );
    }
}
