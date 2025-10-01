<?php declare(strict_types=1);
/**
 * AccountRoleDeletionHook.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Hooks;

use \Peneus\Model\Account;
use \Peneus\Model\AccountRole;

/**
 * Hook for removing role associations during account deletion.
 */
class AccountRoleDeletionHook implements IAccountDeletionHook
{
    /**
     * Deletes all records related to the account's assigned roles.
     *
     * @param Account $account
     *   The account that is about to be deleted.
     * @throws \RuntimeException
     *   If any role entry could not be deleted.
     */
    public function OnDeleteAccount(Account $account): void
    {
        $accountRoles = AccountRole::Find(
            condition: 'accountId = :accountId',
            bindings: ['accountId' => $account->id]
        );
        foreach ($accountRoles as $accountRole) {
            if (!$accountRole->Delete()) {
                throw new \RuntimeException("Failed to delete account role.");
            }
        }
    }
}
