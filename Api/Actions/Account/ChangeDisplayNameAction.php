<?php declare(strict_types=1);
/**
 * ChangeDisplayNameAction.php
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
use \Harmonia\Systems\ValidationSystem\Validator;
use \Peneus\Model\Account;
use \Peneus\Model\AccountView;
use \Peneus\Services\AccountService;

/**
 * Changes the display name of the currently logged-in account.
 */
class ChangeDisplayNameAction extends Action
{
    private readonly Request $request;
    private readonly AccountService $accountService;

    /**
     * Constructs a new instance by initializing dependencies.
     */
    public function __construct()
    {
        parent::__construct();
        $this->request = Request::Instance();
        $this->accountService = AccountService::Instance();
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
        $account = $this->findAccount($accountView->id);
        // 3
        $payload = $this->validatePayload();
        // 4
        $this->doChange($account, $payload->displayName);
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
     * @return object{
     *   displayName: string
     * }
     * @throws \RuntimeException
     */
    protected function validatePayload(): \stdClass
    {
        $validator = new Validator([
            'displayName' => [
                'required',
                'regex:' . AccountService::DISPLAY_NAME_PATTERN
            ]
        ], [
            'displayName.regex' => "Display name is invalid. It must start"
                . " with a letter or number and may only contain letters,"
                . " numbers, spaces, dots, hyphens, and apostrophes."
        ]);
        $da = $validator->Validate($this->request->FormParams());
        return (object)[
            'displayName' => $da->GetField('displayName')
        ];
    }

    /**
     * @param Account $account
     * @param string $displayName
     * @throws \RuntimeException
     */
    protected function doChange(Account $account, string $displayName): void
    {
        $account->displayName = $displayName;
        if (!$account->Save()) {
            throw new \RuntimeException("Failed to change display name.");
        }
    }
}
