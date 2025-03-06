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
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Session;
use \Peneus\Api\Actions\LogoutAction;
use \Peneus\Model\Account;
use \Peneus\Services\AccountService;

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
     *   If username or password is missing, if credentials are invalid, if the
     *   account's last login time cannot be updated, or if session integrity
     *   cannot be established.
     *
     * @todo Integrate validation.
     */
    protected function onExecute(): mixed
    {
        $formParams = Request::Instance()->FormParams();
        $username = $formParams->Get('username');
        if ($username === null) {
            throw new \RuntimeException('Username is required.');
        }
        $password = $formParams->Get('password');
        if ($password === null) {
            throw new \RuntimeException('Password is required.');
        }
        $account = $this->findAccount($username);
        if ($account === null || !$this->verifyPassword($account, $password)) {
            throw new \RuntimeException('Invalid username or password.');
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
            throw new \RuntimeException('Failed to log in.');
        }
        return null;
    }

    #region protected ----------------------------------------------------------

    protected function findAccount(string $username): ?Account
    {
        return Account::FindFirst(
            condition: 'username = :username',
            bindings: ['username' => $username]
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
                ->Set(AccountService::INTEGRITY_TOKEN_SESSION_KEY, $integrity->Token())
                ->Set(AccountService::ACCOUNT_ID_SESSION_KEY, $account->id)
                ->Close();
            CookieService::Instance()->SetCookie(
                AccountService::Instance()->IntegrityCookieName(),
                $integrity->CookieValue()
            );
            return true;
        } catch (\Exception $e) {
            // todo: log the error
            return false;
        }
    }

    protected function createLogoutAction(): LogoutAction
    {
        return new LogoutAction;
    }

    #endregion protected
}
