<?php declare(strict_types=1);
/**
 * ResetPasswordAction.php
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

use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\ValidationSystem\Validator;
use \Peneus\Model\Account;
use \Peneus\Model\PasswordReset;
use \Peneus\Resource;

/**
 * Changes a user's password after verifying a previously issued reset code.
 */
class ResetPasswordAction extends Action
{
    private readonly Request $request;
    private readonly Database $database;
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
        $this->resource = Resource::Instance();
        $this->securityService = SecurityService::Instance();
        $this->cookieService = CookieService::Instance();
    }

    /**
     * @return array{redirectUrl: string}
     * @throws \RuntimeException
     */
    protected function onExecute(): mixed
    {
        // 1
        $payload = $this->validatePayload();
        // 2
        [$account, $pr] = $this->findAccountAndPasswordReset($payload->resetCode);
        // 3
        try {
            $this->database->WithTransaction(fn() =>
                $this->doReset($account, $payload->newPassword, $pr)
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Password reset failed.",
                StatusCode::InternalServerError->value,
                $e
            );
        }
        // 4
        $this->cookieService->DeleteCsrfCookie();
        return [
            'redirectUrl' => $this->resource->LoginPageUrl('home')
        ];
    }

    /**
     * @return object{
     *   resetCode: string,
     *   newPassword: string
     * }
     * @throws \RuntimeException
     */
    protected function validatePayload(): \stdClass
    {
        $validator = new Validator([
            'resetCode' => [
                'required',
                'regex:' . SecurityService::TOKEN_DEFAULT_PATTERN
            ],
            'newPassword' => [
                'required',
                'string',
                'minLength:' . SecurityService::PASSWORD_MIN_LENGTH,
                'maxLength:' . SecurityService::PASSWORD_MAX_LENGTH
            ]
        ], [
            'resetCode.required' => "Reset code is required.",
            'resetCode.regex' => "Reset code format is invalid."
        ]);
        $da = $validator->Validate($this->request->FormParams());
        return (object)[
            'resetCode' => $da->GetField('resetCode'),
            'newPassword' => $da->GetField('newPassword')
        ];
    }

    /**
     * @param string $resetCode
     * @return array{0: Account, 1: PasswordReset}
     * @throws \RuntimeException
     */
    protected function findAccountAndPasswordReset(string $resetCode): array
    {
        $pr = $this->findPasswordReset($resetCode);
        if ($pr === null ||
            ($account = $this->findAccount($pr->accountId)) === null
        ) {
            throw new \RuntimeException(
                "This password reset request is no longer valid.",
                StatusCode::BadRequest->value
            );
        }
        return [$account, $pr];
    }

    /**
     * @param string $resetCode
     * @return ?PasswordReset
     */
    protected function findPasswordReset(string $resetCode): ?PasswordReset
    {
        return PasswordReset::FindFirst(
            'resetCode = :resetCode',
            ['resetCode' => $resetCode]
        );
    }

    /**
     * @param int $accountId
     * @return ?Account
     */
    protected function findAccount(int $accountId): ?Account
    {
        return Account::FindById($accountId);
    }

    /**
     * @param Account $account
     * @param string $newPassword
     * @param PasswordReset $pr
     * @throws \RuntimeException
     */
    protected function doReset(
        Account $account,
        string $newPassword,
        PasswordReset $pr
    ): void
    {
        $account->passwordHash = $this->securityService->HashPassword($newPassword);
        if (!$account->Save()) {
            throw new \RuntimeException("Failed to save account.");
        }
        if (!$pr->Delete()) {
            throw new \RuntimeException("Failed to delete password reset.");
        }
    }
}
