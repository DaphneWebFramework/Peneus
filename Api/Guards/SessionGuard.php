<?php declare(strict_types=1);
/**
 * SessionGuard.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Guards;

use \Peneus\Services\AccountService;

/**
 * A guard that verifies whether a request is from an authenticated user.
 */
class SessionGuard implements IGuard
{
    /**
     * Verifies whether the request is from an authenticated user.
     *
     * @return bool
     *   Returns `true` if the request is from an authenticated user,
     *   otherwise `false`.
     */
    public function Verify(): bool
    {
        return AccountService::Instance()->AuthenticatedAccount() !== null;
    }
}
