<?php declare(strict_types=1);
/**
 * IAccountActivationHook.php
 *
 * (C) 2026 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Hooks;

use \Peneus\Model\Account;

/**
 * Interface for processing tasks related to an account after it is activated.
 */
interface IAccountActivationHook
{
    /**
     * Initializes data linked to the given account, such as database records
     * or other resources.
     *
     * @param Account $account
     *   The account that has just been activated.
     */
    public function OnActivateAccount(Account $account): void;
}
