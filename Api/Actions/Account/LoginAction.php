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
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\ValidationSystem\Validator;
use \Peneus\Model\Account;
use \Peneus\Services\AccountService;

/**
 * Authenticates a user with email and password credentials.
 */
class LoginAction extends Action
{
    private readonly Request $request;
    private readonly Database $database;
    private readonly AccountService $accountService;
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
        $this->accountService = AccountService::Instance();
        $this->securityService = SecurityService::Instance();
        $this->cookieService = CookieService::Instance();
    }

    /**
     * @return null
     * @throws \RuntimeException
     */
    protected function onExecute(): mixed
    {
        // 1
        $this->ensureNotLoggedIn();
        // 2
        $data = $this->validateRequest();
        // 3
        $account = $this->findAndAuthenticateAccount(
            $data->email,
            $data->password
        );
        // 4
        try {
            $this->database->WithTransaction(fn() =>
                $this->doLogIn($account, $data->keepLoggedIn)
            );
        } catch (\Throwable $e) {
            $this->logOut();
            throw new \RuntimeException(
                "Login failed.",
                StatusCode::InternalServerError->value,
                $e
            );
        }
        // 5
        $this->cookieService->DeleteCsrfCookie();
        return null;
    }

    /**
     * @throws \RuntimeException
     */
    protected function ensureNotLoggedIn(): void
    {
        if (null !== $this->accountService->LoggedInAccount()) {
            throw new \RuntimeException(
                "You are already logged in.",
                StatusCode::Conflict->value
            );
        }
    }

    /**
     * @return object{email: string, password: string, keepLoggedIn: bool}
     * @throws \RuntimeException
     */
    protected function validateRequest(): \stdClass
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
            ],
            'keepLoggedIn' => [
                'string',
                fn($value) => $value === 'on'
            ]
        ]);
        $da = $validator->Validate($this->request->FormParams());
        return (object)[
            'email' => $da->GetField('email'),
            'password' => $da->GetField('password'),
            'keepLoggedIn' => 'on' === $da->GetFieldOrDefault('keepLoggedIn')
        ];
    }

    /**
     * @param string $email
     * @param string $password
     * @return Account
     * @throws \RuntimeException
     */
    protected function findAndAuthenticateAccount(
        string $email,
        string $password
    ): Account
    {
        $account = $this->findAccount($email);
        if ($account === null ||
            !$this->securityService->VerifyPassword(
                $password,
                $account->passwordHash
            )
        ) {
            throw new \RuntimeException(
                "Incorrect email address or password.",
                StatusCode::Unauthorized->value
            );
        }
        return $account;
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
     * @param bool $keepLoggedIn
     * @throws \RuntimeException
     */
    protected function doLogIn(Account $account, bool $keepLoggedIn): void
    {
        $account->timeLastLogin = new \DateTime(); // now
        if (!$account->Save()) {
            throw new \RuntimeException("Failed to save account.");
        }
        $this->accountService->CreateSession($account);
        if ($keepLoggedIn) {
            $this->accountService->CreatePersistentLogin($account);
        }
    }

    /**
     * @throws \RuntimeException
     */
    protected function logOut(): void
    {
        $this->accountService->DeleteSession();
        $this->accountService->DeletePersistentLogin();
    }
}
