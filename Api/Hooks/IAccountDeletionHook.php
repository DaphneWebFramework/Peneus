<?php declare(strict_types=1);
/**
 * IAccountDeletionHook.php
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

/**
 * Interface for components that need to clean up data related to an account
 * before it is deleted.
 */
interface IAccountDeletionHook
{
    /**
     * Cleans up data linked to the given account, such as database records
     * or other resources.
     *
     * @param Account $account
     *   The account that is about to be deleted.
     */
    public function OnDeleteAccount(Account $account): void;
}
