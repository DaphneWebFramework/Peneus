<?php declare(strict_types=1);
/**
 * LoggedInEnsurer.php
 *
 * (C) 2026 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Traits;

use \Harmonia\Http\StatusCode;
use \Peneus\Model\AccountView;
use \Peneus\Services\AccountService;

trait LoggedInEnsurer
{
    /**
     * @return AccountView
     * @throws \RuntimeException
     */
    protected function ensureLoggedIn(): AccountView
    {
        $accountService = $this->accountService ?? AccountService::Instance();
        $accountView = $accountService->SessionAccount();
        if ($accountView === null) {
            throw new \RuntimeException(
                "You do not have permission to perform this action.",
                StatusCode::Unauthorized->value
            );
        }
        return $accountView;
    }
}
