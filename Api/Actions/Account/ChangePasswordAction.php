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
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\ValidationSystem\Validator;
use \Peneus\Api\Traits\AccountFinder;
use \Peneus\Api\Traits\LoggedInEnsurer;
use \Peneus\Model\Account;
use \Peneus\Model\AccountView;

/**
 * Changes the password of the currently logged-in account.
 */
class ChangePasswordAction extends Action
{
    use LoggedInEnsurer;
    use AccountFinder;

    private readonly Request $request;
    private readonly Database $database;
    private readonly SecurityService $securityService;

    /**
     * Constructs a new instance by initializing dependencies.
     */
    public function __construct()
    {
        parent::__construct();
        $this->request = Request::Instance();
        $this->database = Database::Instance();
        $this->securityService = SecurityService::Instance();
    }

    /**
     * @return null
     * @throws \RuntimeException
     */
    protected function onExecute(): mixed
    {
        $accountView = $this->ensureLoggedIn();
        $this->ensureLocalAccount($accountView);
        $account = $this->findAccount($accountView->id);
        $payload = $this->validatePayload();
        $this->verifyCurrentPassword($payload->currentPassword, $account->passwordHash);
        $this->doTransaction($account, $payload->newPassword);
        return null;
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
     * @return object{
     *   currentPassword: string,
     *   newPassword: string
     * }
     * @throws \RuntimeException
     */
    protected function validatePayload(): \stdClass
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
    protected function doTransaction(Account $account, string $newPassword): void
    {
        $this->database->WithTransaction(function() use($account, $newPassword) {
            $account->passwordHash = $this->securityService->HashPassword($newPassword);
            if (!$account->Save()) {
                throw new \RuntimeException("Failed to save account.");
            }
        });
    }
}
