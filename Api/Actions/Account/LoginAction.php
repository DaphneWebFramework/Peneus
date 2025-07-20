<?php declare(strict_types=1);
/**
 * LoginAction.php
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
use \Harmonia\Session;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\ValidationSystem\Validator;
use \Peneus\Api\Actions\Account\LogoutAction;
use \Peneus\Model\Account;
use \Peneus\Model\AccountRole;
use \Peneus\Model\Role;
use \Peneus\Services\AccountService;
use \Peneus\Translation;

/**
 * Logs in a user with provided credentials.
 */
class LoginAction extends Action
{
    /**
     * Executes the login process by verifying user credentials, updating the
     * last login time, establishing session integrity, and deleting the CSRF
     * cookie upon success.
     *
     * On failure, the session is destroyed, the session integrity cookie is
     * deleted, and an exception is thrown.
     *
     * @return mixed
     *   Always returns `null`.
     * @throws \RuntimeException
     *   If the user is already logged in, if the email address field is missing
     *   or invalid, if the password field is missing or invalid due to length
     *   limits, if the account's last login time cannot be updated, if session
     *   integrity cannot be established, or if the CSRF cookie cannot be deleted.
     *
     * @todo Define custom error messages for each validation rule.
     */
    protected function onExecute(): mixed
    {
        if (AccountService::Instance()->LoggedInAccount() !== null) {
            throw new \RuntimeException(
                Translation::Instance()->Get('error_already_logged_in'),
                StatusCode::Conflict->value
            );
        }
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
            ]
        ]);
        $dataAccessor = $validator->Validate(Request::Instance()->FormParams());
        $email = $dataAccessor->GetField('email');
        $password = $dataAccessor->GetField('password');
        $account = $this->findAccount($email);
        if ($account === null || !$this->verifyPassword($account, $password)) {
            throw new \RuntimeException(
                Translation::Instance()->Get('error_incorrect_email_or_password'),
                StatusCode::Unauthorized->value
            );
        }
        $result = Database::Instance()->WithTransaction(function() use($account) {
            if (!$this->updateLastLoginTime($account)) {
                throw new \RuntimeException('Failed to update last login time.');
            }
            if (!$this->establishSessionIntegrity($account)) {
                throw new \RuntimeException('Failed to establish session integrity.');
            }
            CookieService::Instance()->DeleteCsrfCookie();
            return true;
        });
        if ($result !== true) {
            $this->createLogoutAction()->Execute();
            throw new \RuntimeException(
                Translation::Instance()->Get('error_login_failed'),
                StatusCode::InternalServerError->value
            );
        }
        return null;
    }

    protected function findAccount(string $email): ?Account
    {
        return Account::FindFirst(
            condition: 'email = :email',
            bindings: ['email' => $email]
        );
    }

    protected function findAccountRole(int $accountId): ?Role
    {
        $accountRole = AccountRole::FindFirst(
            condition: 'accountId = :accountId',
            bindings: ['accountId' => $accountId]
        );
        if ($accountRole === null) {
            return null;
        }
        return Role::tryFrom($accountRole->role);
    }

    protected function verifyPassword(Account $account, string $password): bool
    {
        return SecurityService::Instance()->VerifyPassword(
            $password,
            $account->passwordHash
        );
    }

    protected function updateLastLoginTime(Account $account): bool
    {
        $account->timeLastLogin = new \DateTime(); // now
        return $account->Save();
    }

    protected function establishSessionIntegrity(Account $account): bool
    {
        $integrity = SecurityService::Instance()->GenerateCsrfToken();
        try {
            $session = Session::Instance()
                ->Start()
                ->Clear()
                ->RenewId()
                ->Set(AccountService::INTEGRITY_TOKEN_SESSION_KEY, $integrity->Token())
                ->Set(AccountService::ACCOUNT_ID_SESSION_KEY, $account->id);
            $role = $this->findAccountRole($account->id);
            if ($role !== null) {
                $session->Set(AccountService::ACCOUNT_ROLE_SESSION_KEY, $role->value);
            }
            $session->Close();
            CookieService::Instance()->SetCookie(
                AccountService::Instance()->IntegrityCookieName(),
                $integrity->CookieValue()
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function createLogoutAction(): LogoutAction
    {
        return new LogoutAction;
    }
}
