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

namespace Peneus\Api\Actions;

use \Harmonia\Database\Database;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Logger;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Session;
use \Harmonia\Validation\Validator;
use \Peneus\Api\Actions\LogoutAction;
use \Peneus\Model\Account;
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
     *   If the user is already logged in, if the email address or password is
     *   missing or invalid, if the account's last login time cannot be updated,
     *   or if session integrity cannot be established.
     */
    protected function onExecute(): mixed
    {
        if (AccountService::Instance()->GetAuthenticatedAccount() !== null) {
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
                'minLength: 8',
                'maxLength: 72'
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

    #region protected ----------------------------------------------------------

    protected function findAccount(string $email): ?Account
    {
        return Account::FindFirst(
            condition: 'email = :email',
            bindings: ['email' => $email]
        );
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
            Session::Instance()
                ->Start()
                ->Clear()
                ->Set(AccountService::INTEGRITY_TOKEN_SESSION_KEY,
                      $integrity->Token())
                ->Set(AccountService::ACCOUNT_ID_SESSION_KEY,
                      $account->id)
                ->Close();
            CookieService::Instance()->SetCookie(
                AccountService::Instance()->IntegrityCookieName(),
                $integrity->CookieValue()
            );
            return true;
        } catch (\Exception $e) {
            Logger::Instance()->Error($e->getMessage());
            return false;
        }
    }

    protected function createLogoutAction(): LogoutAction
    {
        return new LogoutAction;
    }

    #endregion protected
}
