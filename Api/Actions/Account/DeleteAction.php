<?php declare(strict_types=1);
/**
 * DeleteAction.php
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

use \Harmonia\Systems\DatabaseSystem\Database;
use \Peneus\Api\Traits\AccountFinder;
use \Peneus\Api\Traits\LoggedInEnsurer;
use \Peneus\Model\Account;
use \Peneus\Services\AccountService;

/**
 * Deletes the currently logged-in account.
 *
 * Aside from the account table, all associated records in related tables
 * are removed, and the user is fully logged out.
 */
class DeleteAction extends Action
{
    use LoggedInEnsurer;
    use AccountFinder;

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
        $accountView = $this->ensureLoggedIn();
        $account = $this->findAccount($accountView->id);
        try {
            $this->database->WithTransaction(fn() =>
                $this->doDelete($account)
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException("Failed to delete account.", 0, $e);
        }
        $this->logOut();
        return null;
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
