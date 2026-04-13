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
 * Hook for removing role records during account deletion.
 */
class AccountRoleDeletionHook implements IAccountDeletionHook
{
    /**
     * Deletes all role records associated with the given account.
     *
     * This method must be called inside a transaction to avoid an inconsistent
     * state in case of failure during cascading deletions.
     *
     * @param Account $account
     *   The account that is about to be deleted.
     * @throws \RuntimeException
     *   If any role record could not be deleted.
     */
    public function OnDeleteAccount(Account $account): void
    {
        foreach ($this->findAccountRoles($account) as $accountRole) {
            if (!$accountRole->Delete()) {
                throw new \RuntimeException("Failed to delete account role.");
            }
        }
    }

    /**
     * @param Account $account
     * @return AccountRole[]
     */
    protected function findAccountRoles(Account $account): array
    {
        return AccountRole::Find(
            condition: 'accountId = :accountId',
            bindings: ['accountId' => $account->id]
        );
    }
}
