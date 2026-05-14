<?php declare(strict_types=1);
/**
 * ActivationHooksTriggerer.php
 *
 * (C) 2026 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Traits;

use \Peneus\Model\Account;
use \Peneus\Services\AccountService;

trait ActivationHooksTriggerer
{
    /**
     * @param Account $account
     * @throws \RuntimeException
     */
    protected function triggerActivationHooks(Account $account): void
    {
        $accountService = $this->accountService ?? AccountService::Instance();
        foreach ($accountService->ActivationHooks() as $hook) {
            $hook->OnActivateAccount($account);
        }
    }
}
