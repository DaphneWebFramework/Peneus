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
use \Harmonia\Systems\ValidationSystem\DataAccessor;
use \Harmonia\Systems\ValidationSystem\Validator;
use \Peneus\Model\Account;
use \Peneus\Model\PasswordReset;
use \Peneus\Resource;

/**
 * Resets a user's password using a previously issued reset code.
 */
class ResetPasswordAction extends Action
{
    /**
     * Executes the password reset process by validating input, verifying the
     * reset code, updating the user's password, deleting the reset record,
     * and clearing the CSRF cookie.
     *
     * On failure, the database transaction is rolled back and an exception is
     * thrown.
     *
     * @return array<string, string>
     *   An associative array with a 'redirectUrl' key indicating where the user
     *   should be redirected after a successful password reset.
     * @throws \RuntimeException
     *   If the reset code field is missing or invalid, if the password field is
     *   missing or invalid due to length limits, if no matching reset record is
     *   found, if the account does not exist, if the password cannot be updated,
     *   if the reset record cannot be deleted, or if the CSRF cookie cannot be
     *   deleted.
     *
     * @todo Define custom error messages for each validation rule.
     */
    protected function onExecute(): mixed
    {
        $dataAccessor = $this->validateRequest();
        $resetCode = $dataAccessor->GetField('resetCode');
        $newPassword = $dataAccessor->GetField('newPassword');
        $passwordReset = $this->findPasswordReset($resetCode);
        if ($passwordReset === null) {
            throw new \RuntimeException(
                "No password reset record found for the given code.",
                StatusCode::NotFound->value
            );
        }
        $account = $this->findAccount($passwordReset->accountId);
        if ($account === null) {
            throw new \RuntimeException(
                "No account is associated with the password reset record.",
                StatusCode::NotFound->value
            );
        }
        $result = Database::Instance()->WithTransaction(function()
            use($account, $newPassword, $passwordReset)
        {
            if (!$this->updatePassword($account, $newPassword)) {
                throw new \RuntimeException('Failed to update account password.');
            }
            if (!$passwordReset->Delete()) {
                throw new \RuntimeException('Failed to delete password reset record.');
            }
            CookieService::Instance()->DeleteCsrfCookie();
            return true;
        });
        if ($result !== true) {
            throw new \RuntimeException(
                "Password reset failed.",
                StatusCode::InternalServerError->value
            );
        }
        return [
            'redirectUrl' => Resource::Instance()->LoginPageUrl('home')
        ];
    }

    /**
     * @return DataAccessor
     * @throws \RuntimeException
     */
    protected function validateRequest(): DataAccessor
    {
        $validator = new Validator([
            'resetCode' => [
                'required',
                'regex:' . SecurityService::Instance()->TokenPattern()
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
        return $validator->Validate(Request::Instance()->FormParams());
    }

    /**
     * @param string $resetCode
     * @return PasswordReset|null
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
     * @return Account|null
     */
    protected function findAccount(int $accountId): ?Account
    {
        return Account::FindById($accountId);
    }

    /**
     * @param Account $account
     * @param string $newPassword
     * @return bool
     */
    protected function updatePassword(Account $account, string $newPassword): bool
    {
        $account->passwordHash =
            SecurityService::Instance()->HashPassword($newPassword);
        return $account->Save();
    }
}
