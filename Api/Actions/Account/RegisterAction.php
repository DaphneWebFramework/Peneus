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
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\ValidationSystem\Validator;
use \Peneus\Api\Traits\NotPendingEnsurer;
use \Peneus\Api\Traits\NotRegisteredEnsurer;
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
    use NotRegisteredEnsurer;
    use NotPendingEnsurer;
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
     * @return array{
     *   message: string
     * }
     * @throws \RuntimeException
     */
    protected function onExecute(): mixed
    {
        $payload = $this->validatePayload();
        $this->ensureNotRegistered($payload->email);
        $this->ensureNotPending($payload->email);
        $this->doTransaction($payload);
        $this->cookieService->DeleteCsrfCookie();
        return $this->composeResult();
    }

    /**
     * @return object{
     *   email       : string,
     *   password    : string,
     *   displayName : string
     * }
     * @throws \RuntimeException
     */
    protected function validatePayload(): \stdClass
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
            'email'       => $da->GetField('email'),
            'password'    => $da->GetField('password'),
            'displayName' => $da->GetField('displayName')
        ];
    }

    /**
     * @param object{
     *   email       : string,
     *   password    : string,
     *   displayName : string
     * }
     * @throws \RuntimeException
     */
    protected function doTransaction(\stdClass $payload): void
    {
        $this->database->WithTransaction(function() use($payload) {
            $activationCode = $this->securityService->GenerateToken();
            $pendingAccount = $this->makePendingAccount($payload, $activationCode);
            if (!$pendingAccount->Save()) {
                throw new \RuntimeException("Failed to save pending account.");
            }
            $this->sendEmail($payload->email, $payload->displayName, $activationCode);
        });
    }

    /**
     * @param object{
     *   email       : string,
     *   password    : string,
     *   displayName : string
     * }
     * @param string $activationCode
     * @return PendingAccount
     */
    protected function makePendingAccount(
        \stdClass $payload,
        string $activationCode
    ): PendingAccount
    {
        return new PendingAccount([
            'email'          => $payload->email,
            'passwordHash'   => $this->securityService->HashPassword($payload->password),
            'displayName'    => $payload->displayName,
            'activationCode' => $activationCode
        ]);
    }

    /**
     * @param string $email
     * @param string $displayName
     * @param string $activationCode
     * @throws \RuntimeException
     */
    protected function sendEmail(
        string $email,
        string $displayName,
        string $activationCode
    ): void
    {
        $appName = $this->config->Option('AppName');
        $actionUrl = $this->resource->PageUrl('activate-account')
                                    ->Extend($activationCode);
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
        if (!$this->sendTransactionalEmail(
            $email,
            $displayName,
            $actionUrl,
            $substitutions
        )) {
            throw new \RuntimeException("Failed to send email.");
        }
    }

    /**
     * @return array{
     *   message: string
     * }
     */
    protected function composeResult(): array
    {
        return [
            'message' =>
                "An account activation link has been sent to your email address."
        ];
    }
}
