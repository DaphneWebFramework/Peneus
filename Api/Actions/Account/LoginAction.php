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
use \Harmonia\Systems\ValidationSystem\DataAccessor;
use \Harmonia\Systems\ValidationSystem\Validator;
use \Peneus\Api\Actions\Account\LogoutAction;
use \Peneus\Model\Account;
use \Peneus\Model\AccountRole;
use \Peneus\Model\Role;
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
     *   Always returns `null` if the operation is successful.
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
        // 1
        if ($this->isAccountLoggedIn()) {
            throw new \RuntimeException(
                "You are already logged in.",
                StatusCode::Conflict->value
            );
        }
        // 2
        $dataAccessor = $this->validateRequest();
        $email = $dataAccessor->GetField('email');
        $password = $dataAccessor->GetField('password');
        // 3
        $account = $this->findAccount($email);
        if ($account === null || !$this->verifyPassword($account, $password)) {
            throw new \RuntimeException(
                "Incorrect email address or password.",
                StatusCode::Unauthorized->value
            );
        }
        $account->timeLastLogin = new \DateTime(); // now
        // 4
        $result = Database::Instance()->WithTransaction(function() use($account) {
            if (!$account->Save()) {
                throw new \RuntimeException('Failed to save account.');
            }
            if (!AccountService::Instance()->EstablishSessionIntegrity($account)) {
                throw new \RuntimeException('Failed to establish session integrity.');
            }
            $this->deleteCsrfCookie();
            return true;
        });
        // 5
        if ($result !== true) {
            $this->logOut();
            throw new \RuntimeException(
                "Login failed.",
                StatusCode::InternalServerError->value
            );
        }
        return null;
    }

    /**
     * @return bool
     */
    protected function isAccountLoggedIn(): bool
    {
        return AccountService::Instance()->LoggedInAccount() !== null;
    }

    /**
     * @return DataAccessor
     * @throws \RuntimeException
     */
    protected function validateRequest(): DataAccessor
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
            ]
        ]);
        return $validator->Validate(Request::Instance()->FormParams());
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
     * @param string $password
     * @return bool
     */
    protected function verifyPassword(Account $account, string $password): bool
    {
        return SecurityService::Instance()->VerifyPassword(
            $password,
            $account->passwordHash
        );
    }

    /**
     * @return void
     * @throws \RuntimeException
     */
    protected function deleteCsrfCookie(): void
    {
        CookieService::Instance()->DeleteCsrfCookie();
    }

    /**
     * @return void
     *
     * @codeCoverageIgnore
     */
    protected function logOut(): void
    {
        $action = new LogoutAction();
        $action->Execute();
    }
}
