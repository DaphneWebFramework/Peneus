<?php declare(strict_types=1);
/**
 * DeleteAction.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 */

namespace Peneus\Api\Actions\Account;

use \Peneus\Api\Actions\Action;

use \Harmonia\Http\StatusCode;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Peneus\Model\Account;
use \Peneus\Model\AccountView;
use \Peneus\Services\AccountService;

/**
 * Deletes the currently logged-in account.
 *
 * Aside from the account table, all associated records in related tables
 * are removed, and the user is fully logged out.
 */
class DeleteAction extends Action
{
    private readonly Database $database;
    private readonly AccountService $accountService;

    /**
     * Constructs a new instance by initializing dependencies.
     */
    public function __construct()
    {
        parent::__construct();
        $this->database = Database::Instance();
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
        try {
            $this->database->WithTransaction(fn() =>
                $this->doDelete($account)
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Failed to delete account.",
                StatusCode::InternalServerError->value,
                $e
            );
        }
        // 4
        $this->logOut();
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
     * @param Account $account
     * @throws \RuntimeException
     */
    protected function doDelete(Account $account): void
    {
        foreach ($this->accountService->DeletionHooks() as $hook) {
            $hook->OnDeleteAccount($account);
        }
        if (!$account->Delete()) {
            throw new \RuntimeException("Failed to delete account.");
        }
    }

    /**
     * @throws \RuntimeException
     */
    protected function logOut(): void
    {
        $this->accountService->DeleteSession();
    }
}
