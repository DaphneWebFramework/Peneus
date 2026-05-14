<?php declare(strict_types=1);
/**
 * NotLoggedInEnsurer.php
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
use \Peneus\Services\AccountService;

trait NotLoggedInEnsurer
{
    /**
     * @throws \RuntimeException
     */
    protected function ensureNotLoggedIn(): void
    {
        $accountService = $this->accountService ?? AccountService::Instance();
        if (null !== $accountService->SessionAccount()) {
            throw new \RuntimeException(
                "You are already logged in.",
                StatusCode::Conflict->value
            );
        }
    }
}
