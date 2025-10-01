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
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\ValidationSystem\Validator;
use \Peneus\Api\Traits\TransactionalEmailSender;
use \Peneus\Model\Account;
use \Peneus\Model\PasswordReset;
use \Peneus\Resource;

/**
 * Handles password reset requests for accounts.
 *
 * The action mitigates account enumeration by always returning the same generic
 * success message, even if no account exists for the given email.
 */
class SendPasswordResetAction extends Action
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
        $account = $this->findAccount($data->email);
        if ($account !== null) {
            // 3
            try {
                $this->database->WithTransaction(fn() =>
                    $this->doSend($account)
                );
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    "We couldn't send the email. Please try again later.",
                    StatusCode::InternalServerError->value,
                    $e
                );
            }
        }
        // 4
        $this->cookieService->DeleteCsrfCookie();
        return [
            'message' =>
                "A password reset link has been sent to your email address."
        ];
    }

    /**
     * @return object{email: string}
     * @throws \RuntimeException
     */
    protected function validateRequest(): \stdClass
    {
        $validator = new Validator([
            'email' => [
                'required',
                'email'
            ],
        ]);
        $da = $validator->Validate($this->request->FormParams());
        return (object)[
            'email' => $da->GetField('email')
        ];
    }

    /**
     * @param string $email
     * @return ?Account
     */
    protected function findAccount(string $email): ?Account
    {
        return Account::FindFirst(
            condition: 'email = :email',
            bindings: ['email' => $email]
        );
    }

    /**
     * @param Account $account
     * @throws \RuntimeException
     */
    protected function doSend(Account $account): void
    {
        // 1
        $resetCode = $this->securityService->GenerateToken();
        // 2
        $pr = $this->findOrConstructPasswordReset($account->id);
        $pr->resetCode = $resetCode;
        $pr->timeRequested = new \DateTime(); // now
        if (!$pr->Save()) {
            throw new \RuntimeException("Failed to save password reset.");
        }
        // 3
        if (!$this->sendEmail($account->email, $account->displayName, $resetCode)) {
            throw new \RuntimeException("Failed to send email.");
        }
    }

    /**
     * @param int $accountId
     * @return PasswordReset
     */
    protected function findOrConstructPasswordReset(int $accountId): PasswordReset
    {
        $pr = $this->findPasswordReset($accountId);
        if ($pr === null) {
            $pr = $this->constructPasswordReset($accountId);
        }
        return $pr;
    }

    /**
     * @param int $accountId
     * @return ?PasswordReset
     */
    protected function findPasswordReset(int $accountId): ?PasswordReset
    {
        return PasswordReset::FindFirst(
            condition: 'accountId = :accountId',
            bindings: ['accountId' => $accountId]
        );
    }

    /**
     * @param int $accountId
     * @return PasswordReset
     */
    protected function constructPasswordReset(int $accountId): PasswordReset
    {
        $pr = new PasswordReset();
        $pr->accountId = $accountId;
        return $pr;
    }

    /**
     * @param string $email
     * @param string $displayName
     * @param string $resetCode
     * @return bool
     */
    protected function sendEmail(
        string $email,
        string $displayName,
        string $resetCode
    ): bool
    {
        $appName = $this->config->OptionOrDefault('AppName', '');
        $actionUrl = $this->resource
            ->PageUrl('reset-password')
            ->Extend($resetCode)
            ->__toString();
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
        return $this->sendTransactionalEmail(
            $email,
            $displayName,
            $actionUrl,
            $substitutions
        );
    }
}
