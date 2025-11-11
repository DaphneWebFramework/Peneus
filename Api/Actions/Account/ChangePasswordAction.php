<?php declare(strict_types=1);
/**
 * ChangePasswordAction.php
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
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\ValidationSystem\Validator;
use \Peneus\Model\Account;
use \Peneus\Model\AccountView;
use \Peneus\Services\AccountService;

/**
 * Changes the password of the currently logged-in account.
 */
class ChangePasswordAction extends Action
{
    private readonly Request $request;
    private readonly AccountService $accountService;
    private readonly SecurityService $securityService;

    /**
     * Constructs a new instance by initializing dependencies.
     */
    public function __construct()
    {
        parent::__construct();
        $this->request = Request::Instance();
        $this->accountService = AccountService::Instance();
        $this->securityService = SecurityService::Instance();
    }

    /**
     * @return null
     * @throws \RuntimeException
     */
    protected function onExecute(): mixed
    {
        // 1
        $accountView = $this->ensureLoggedIn();
        // 2
        $this->ensureLocalAccount($accountView);
        // 3
        $account = $this->findAccount($accountView->id);
        // 4
        $payload = $this->validateRequest();
        // 5
        $this->verifyCurrentPassword(
            $payload->currentPassword,
            $account->passwordHash
        );
        // 6
        $this->doChange($account, $payload->newPassword);
        return null;
    }

    /**
     * @return AccountView
     * @throws \RuntimeException
     */
    protected function ensureLoggedIn(): AccountView
    {
        $accountView = $this->accountService->SessionAccount();
        if ($accountView === null) {
            throw new \RuntimeException(
                "You do not have permission to perform this action.",
                StatusCode::Unauthorized->value
            );
        }
        return $accountView;
    }

    /**
     * @param AccountView $accountView
     * @throws \RuntimeException
     */
    protected function ensureLocalAccount(AccountView $accountView): void
    {
        if (!$accountView->isLocal) {
            throw new \RuntimeException(
                "This account does not have a local password.",
                StatusCode::Forbidden->value
            );
        }
    }

    /**
     * @param int $id
     * @return Account
     * @throws \RuntimeException
     */
    protected function findAccount(int $id): Account
    {
        $account = Account::FindById($id);
        if ($account === null) {
            throw new \RuntimeException(
                "Account not found.",
                StatusCode::NotFound->value
            );
        }
        return $account;
    }

    /**
     * @return object{currentPassword: string, newPassword: string}
     * @throws \RuntimeException
     */
    protected function validateRequest(): \stdClass
    {
        $validator = new Validator([
            'currentPassword' => [
                'required',
                'string',
                'minLength:' . SecurityService::PASSWORD_MIN_LENGTH,
                'maxLength:' . SecurityService::PASSWORD_MAX_LENGTH
            ],
            'newPassword' => [
                'required',
                'string',
                'minLength:' . SecurityService::PASSWORD_MIN_LENGTH,
                'maxLength:' . SecurityService::PASSWORD_MAX_LENGTH
            ]
        ]);
        $da = $validator->Validate($this->request->FormParams());
        return (object)[
            'currentPassword' => $da->GetField('currentPassword'),
            'newPassword' => $da->GetField('newPassword')
        ];
    }

    /**
     * @param string $currentPassword
     * @param string $passwordHash
     * @throws \RuntimeException
     */
    protected function verifyCurrentPassword(
        string $currentPassword,
        string $passwordHash
    ): void
    {
        if (!$this->securityService->VerifyPassword(
            $currentPassword,
            $passwordHash
        )) {
            throw new \RuntimeException(
                "Current password is incorrect.",
                StatusCode::Unauthorized->value
            );
        }
    }

    /**
     * @param Account $account
     * @param string $newPassword
     * @throws \RuntimeException
     */
    protected function doChange(Account $account, string $newPassword): void
    {
        $account->passwordHash =
            $this->securityService->HashPassword($newPassword);
        if (!$account->Save()) {
            throw new \RuntimeException("Failed to change password.");
        }
    }
}
