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

use \Peneus\Model\Role;
use \Peneus\Services\AccountService;

/**
 * A guard that verifies whether the request is from a logged-in user,
 * optionally enforcing a minimum role requirement.
 */
class SessionGuard implements IGuard
{
    private readonly ?Role $minimumRole;

    /**
     * Constructs a new instance with an optional minimum role.
     *
     * @param ?Role $minimumRole
     *   (Optional) The minimum role required for the request. If not provided,
     *   the guard will only check if the user is logged in without enforcing a
     *   specific role. If a role is provided, the guard will ensure that the
     *   logged-in user's role meets or exceeds this minimum requirement.
     */
    public function __construct(?Role $minimumRole = null)
    {
        $this->minimumRole = $minimumRole;
    }

    /**
     * Verifies that the request is from a logged-in user.
     *
     * If a minimum role is set, also ensures the user has at least that role.
     *
     * @return bool
     *   Returns `true` if the user is logged in and satisfies the role
     *   requirement, if any. Otherwise, returns `false`.
     */
    public function Verify(): bool
    {
        $accountService = AccountService::Instance();
        if ($accountService->LoggedInAccount() === null) {
            return false;
        }
        if ($this->minimumRole !== null) {
            $role = $accountService->LoggedInAccountRole();
            if ($role === null || $role->value < $this->minimumRole->value) {
                return false;
            }
        }
        return true;
    }
}
