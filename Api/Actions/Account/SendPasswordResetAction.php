<?php declare(strict_types=1);
/**
 * SendPasswordResetAction.php
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
use \Peneus\Api\Traits\TransactionalEmailSender;
use \Peneus\Model\Account;
use \Peneus\Model\PasswordReset;
use \Peneus\Model\Traits\AccountFinder;
use \Peneus\Model\Traits\PasswordResetFinder;
use \Peneus\Resource;

/**
 * Handles password reset requests for accounts.
 *
 * Mitigates account enumeration by always returning the same generic success
 * message, even if no account exists for the given email or if the account is
 * third-party and therefore has an empty password hash.
 */
class SendPasswordResetAction extends Action
{
    use AccountFinder;
    use PasswordResetFinder;
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
        $this->doTransaction($payload);
        $this->cookieService->DeleteCsrfCookie();
        return $this->composeResult();
    }

    /**
     * @return object{
     *   email: string
     * }
     * @throws \RuntimeException
     */
    protected function validatePayload(): \stdClass
    {
        $validator = new Validator([
            'email' => [
                'required',
                'email'
            ]
        ]);
        $da = $validator->Validate($this->request->FormParams());
        return (object)[
            'email' => $da->GetField('email')
        ];
    }

    /**
     * @param object{
     *   email: string
     * } $payload
     * @throws \RuntimeException
     */
    protected function doTransaction(\stdClass $payload): void
    {
        $account = $this->tryFindAccountByEmail($payload->email);
        if ($account === null || $this->isThirdPartyAccount($account)) {
            return;
        }
        $this->database->WithTransaction(function() use($account) {
            $resetCode = $this->securityService->GenerateToken();
            $passwordReset = $this->findOrMakePasswordReset($account->id);
            $passwordReset->resetCode = $resetCode;
            $passwordReset->timeRequested = new \DateTime(); // now
            if (!$passwordReset->Save()) {
                throw new \RuntimeException("Failed to save password reset.");
            }
            $this->sendEmail($account->email, $account->displayName, $resetCode);
        });
    }

    /**
     * @param Account $account
     * @return bool
     */
    protected function isThirdPartyAccount(Account $account): bool
    {
        return $account->passwordHash === '';
    }

    /**
     * @param int $accountId
     * @return PasswordReset
     */
    protected function findOrMakePasswordReset(int $accountId): PasswordReset
    {
        $passwordReset = $this->tryFindPasswordResetByAccountId($accountId);
        if ($passwordReset === null) {
            $passwordReset = $this->makePasswordReset($accountId);
        }
        return $passwordReset;
    }

    /**
     * @param int $accountId
     * @return PasswordReset
     */
    protected function makePasswordReset(int $accountId): PasswordReset
    {
        return new PasswordReset([
            'accountId' => $accountId
        ]);
    }

    /**
     * @param string $email
     * @param string $displayName
     * @param string $resetCode
     * @throws \RuntimeException
     */
    protected function sendEmail(
        string $email,
        string $displayName,
        string $resetCode
    ): void
    {
        $appName = $this->config->Option('AppName');
        $actionUrl = $this->resource->PageUrl('reset-password')
                                    ->Extend($resetCode);
        $substitutions = [
            'heroText' =>
                "Reset your password",
            'introText' =>
                "Follow the link below to choose a new password.",
            'buttonText' =>
                "Reset My Password",
            'disclaimerText' =>
                "You received this email because a password reset was"
              . " requested for your account on {$appName}. If you did"
              . " not request this, you can safely ignore this email."
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
                "A password reset link has been sent to your email address."
        ];
    }
}
