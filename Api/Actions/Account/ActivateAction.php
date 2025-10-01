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
use \Harmonia\Systems\ValidationSystem\Validator;
use \Peneus\Model\Account;
use \Peneus\Model\PendingAccount;
use \Peneus\Resource;

/**
 * Handles account activation via activation code.
 */
class ActivateAction extends Action
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
        $data = $this->validateRequest();
        // 2
        $pa = $this->findPendingAccount($data->activationCode);
        // 3
        $this->ensureNotRegistered($pa->email);
        // 4
        try {
            $this->database->WithTransaction(fn() =>
                $this->doActivate($pa)
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Account activation failed.",
                StatusCode::InternalServerError->value,
                $e
            );
        }
        // 5
        $this->cookieService->DeleteCsrfCookie();
        return [
            'redirectUrl' => $this->resource->LoginPageUrl('home')
        ];
    }

    /**
     * @return object{activationCode: string}
     * @throws \RuntimeException
     */
    protected function validateRequest(): \stdClass
    {
        $validator = new Validator([
            'activationCode' => [
                'required',
                'regex:' . SecurityService::TOKEN_DEFAULT_PATTERN
            ]
        ], [
            'activationCode.required' => "Activation code is required.",
            'activationCode.regex' => "Activation code format is invalid."
        ]);
        $da = $validator->Validate($this->request->FormParams());
        return (object)[
            'activationCode' => $da->GetField('activationCode')
        ];
    }

    /**
     * @param string $activationCode
     * @return PendingAccount
     * @throws \RuntimeException
     */
    protected function findPendingAccount(string $activationCode): PendingAccount
    {
        $pa = PendingAccount::FindFirst(
            condition: 'activationCode = :activationCode',
            bindings: ['activationCode' => $activationCode]
        );
        if ($pa === null) {
            throw new \RuntimeException(
                "No account is awaiting activation for the given code.",
                StatusCode::NotFound->value
            );
        }
        return $pa;
    }

    /**
     * @param string $email
     * @throws \RuntimeException
     */
    protected function ensureNotRegistered(string $email): void
    {
        if (0 !== Account::Count(
            condition: 'email = :email',
            bindings: ['email' => $email]
        )) {
            throw new \RuntimeException(
                "This account is already registered.",
                StatusCode::Conflict->value
            );
        }
    }

    /**
     * @param PendingAccount $pa
     * @throws \RuntimeException
     */
    protected function doActivate(PendingAccount $pa): void
    {
        $account = $this->constructAccount($pa);
        if (!$account->Save()) {
            throw new \RuntimeException("Failed to save account.");
        }
        if (!$pa->Delete()) {
            throw new \RuntimeException("Failed to delete pending account.");
        }
    }

    /**
     * @param PendingAccount $pa
     * @return Account
     */
    protected function constructAccount(PendingAccount $pa): Account
    {
        $account = new Account();
        $account->email = $pa->email;
        $account->passwordHash = $pa->passwordHash;
        $account->displayName = $pa->displayName;
        $account->timeActivated = new \DateTime(); // now
        $account->timeLastLogin = null;
        return $account;
    }
}
