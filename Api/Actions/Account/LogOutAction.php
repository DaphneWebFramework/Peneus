<?php declare(strict_types=1);
/**
 * LogOutAction.php
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

use \Peneus\Services\AccountService;

/**
 * Logs out the currently logged-in user.
 */
class LogOutAction extends Action
{
    private readonly AccountService $accountService;

    /**
     * Constructs a new instance by initializing dependencies.
     */
    public function __construct()
    {
        parent::__construct();
        $this->accountService = AccountService::Instance();
    }

    /**
     * @return null
     * @throws \RuntimeException
     */
    protected function onExecute(): mixed
    {
        $this->accountService->DeleteSession();
        return null;
    }
}
