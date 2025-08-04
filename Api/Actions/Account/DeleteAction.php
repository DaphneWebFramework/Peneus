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
use \Peneus\Api\Actions\Account\LogoutAction;
use \Peneus\Services\AccountService;
use \Peneus\Translation;

/**
 * Deletes the currently logged-in account along with associated records.
 */
class DeleteAction extends Action
{
    /**
     * Executes the account deletion process in a database transaction.
     *
     * The method first checks whether a user is logged in. Then it invokes all
     * registered account deletion hooks for cleanup, deletes the account, and
     * finally logs the user out.
     *
     * @return mixed
     *   Always returns `null` if the operation is successful.
     * @throws \RuntimeException
     *   If the user is not logged in, if any hook cleanup fails, or if the
     *   account cannot be deleted.
     */
    protected function onExecute(): mixed
    {
        $translation = Translation::Instance();
        $accountService = AccountService::Instance();
        $account = $accountService->LoggedInAccount();
        if ($account === null) {
            throw new \RuntimeException(
                $translation->Get('error_no_permission_for_action'),
                StatusCode::Unauthorized->value
            );
        }
        $result = Database::Instance()->WithTransaction(function()
            use ($accountService, $account)
        {
            foreach ($accountService->DeletionHooks() as $hook) {
                $hook->OnDeleteAccount($account);
            }
            if (!$account->Delete()) {
                throw new \RuntimeException('Failed to delete account.');
            }
            return true;
        });
        if ($result !== true) {
            throw new \RuntimeException(
                $translation->Get('error_delete_account_failed'),
                StatusCode::InternalServerError->value
            );
        }
        $this->logOut();
        return null;
    }

    /** @codeCoverageIgnore */
    protected function logOut(): void
    {
        (new LogoutAction)->Execute();
    }
}
