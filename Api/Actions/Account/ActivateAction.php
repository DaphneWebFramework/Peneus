<?php declare(strict_types=1);
/**
 * ActivateAction.php
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
use \Peneus\Model\PendingAccount;
use \Peneus\Resource;

/**
 * Handles account activation via activation code.
 */
class ActivateAction extends Action
{
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
        $dataAccessor = $this->validateRequest();
        $activationCode = $dataAccessor->GetField('activationCode');
        $pendingAccount = $this->findPendingAccount($activationCode);
        if ($pendingAccount === null) {
            throw new \RuntimeException(
                "No account is awaiting activation for the given code.",
                StatusCode::NotFound->value
            );
        }
        if ($this->isEmailAlreadyRegistered($pendingAccount->email)) {
            throw new \RuntimeException(
                "This email address is already registered.",
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
                "Account activation failed.",
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
            'activationCode' => [
                'required',
                'regex:' . SecurityService::Instance()->TokenPattern()
            ]
        ], [
            'activationCode.required' => "Activation code is required.",
            'activationCode.regex' => "Activation code format is invalid."
        ]);
        return $validator->Validate(Request::Instance()->FormParams());
    }

    /**
     * @param string $activationCode
     * @return PendingAccount|null
     */
    protected function findPendingAccount(string $activationCode): ?PendingAccount
    {
        return PendingAccount::FindFirst(
            condition: 'activationCode = :activationCode',
            bindings: ['activationCode' => $activationCode]
        );
    }

    /**
     * @param string $email
     * @return bool
     */
    protected function isEmailAlreadyRegistered(string $email): bool
    {
        return 0 !== Account::Count(
            condition: 'email = :email',
            bindings: ['email' => $email]
        );
    }

    /**
     * @param PendingAccount $pendingAccount
     * @param \DateTime|null $timeActivated
     * @return Account
     */
    protected function createAccountFromPendingAccount(
        PendingAccount $pendingAccount,
        ?\DateTime $timeActivated = null
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
