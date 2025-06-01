<?php declare(strict_types=1);
/**
 * ActivateAccountAction.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Actions;

use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\ValidationSystem\Validator;
use \Peneus\Api\Actions\Traits\LoginUrlBuilder;
use \Peneus\Model\Account;
use \Peneus\Model\PendingAccount;
use \Peneus\Translation;

/**
 * Handles account activation via activation code.
 */
class ActivateAccountAction extends Action
{
    use LoginUrlBuilder;

    /**
     * Executes the account activation process.
     *
     * @return array<string, string>
     *   An associative array with a 'redirectUrl' key indicating where the user
     *   should be redirected after successful activation.
     * @throws \RuntimeException
     *   If the activation code is missing or invalid, if no pending account is
     *   found for the given code, if the email is already registered, if the
     *   account cannot be saved, if the pending account cannot be deleted, or
     *   if the CSRF cookie cannot be deleted.
     */
    protected function onExecute(): mixed
    {
        $translation = Translation::Instance();
        $validator = new Validator([
            'activationCode' => [
                'required',
                'regex:' . SecurityService::TOKEN_PATTERN
            ]
        ], [
            'activationCode.required' =>
                $translation->Get('error_activation_code_required'),
            'activationCode.regex' =>
                $translation->Get('error_activation_code_invalid')
        ]);
        $dataAccessor = $validator->Validate(Request::Instance()->FormParams());
        $activationCode = $dataAccessor->GetField('activationCode');
        $pendingAccount = $this->findPendingAccount($activationCode);
        if ($pendingAccount === null) {
            throw new \RuntimeException(
                $translation->Get('error_pending_account_not_found'),
                StatusCode::NotFound->value
            );
        }
        if ($this->isEmailAlreadyRegistered($pendingAccount->email)) {
            throw new \RuntimeException(
                $translation->Get('error_email_already_registered'),
                StatusCode::Conflict->value
            );
        }
        $result = Database::Instance()->WithTransaction(function()
            use($pendingAccount)
        {
            $account = $this->createAccountFromPendingAccount($pendingAccount);
            if (!$account->Save()) {
                throw new \RuntimeException('Failed to save account.');
            }
            if (!$pendingAccount->Delete()) {
                throw new \RuntimeException('Failed to delete pending account.');
            }
            CookieService::Instance()->DeleteCsrfCookie();
            return true;
        });
        if ($result !== true) {
            throw new \RuntimeException(
                $translation->Get('error_activate_account_failed'),
                StatusCode::InternalServerError->value
            );
        }
        return [
            'redirectUrl' => $this->buildLoginUrl(),
        ];
    }

    protected function findPendingAccount(string $activationCode): ?PendingAccount
    {
        return PendingAccount::FindFirst(
            condition: 'activationCode = :activationCode',
            bindings: ['activationCode' => $activationCode]
        );
    }

    protected function isEmailAlreadyRegistered(string $email): bool
    {
        return 0 !== Account::Count(
            condition: 'email = :email',
            bindings: ['email' => $email]
        );
    }

    protected function createAccountFromPendingAccount(
        PendingAccount $pendingAccount,
        \DateTime $timeActivated = null
    ): Account
    {
        $account = new Account();
        $account->email = $pendingAccount->email;
        $account->passwordHash = $pendingAccount->passwordHash;
        $account->displayName = $pendingAccount->displayName;
        $account->timeActivated = $timeActivated ?? new \DateTime();
        $account->timeLastLogin = null;
        return $account;
    }
}
