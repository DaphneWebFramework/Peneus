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
    private readonly Role $minimumRole;

    /**
     * Constructs a new instance with an optional minimum role.
     *
     * @param Role $minimumRole
     *   (Optional) The minimum role required for the request. Defaults to
     *   `Role::None`. When `Role::None` is specified, only login status is
     *   enforced.
     */
    public function __construct(Role $minimumRole = Role::None)
    {
        $this->minimumRole = $minimumRole;
    }

    /**
     * Verifies that the request is from a logged-in user and optionally
     * enforces a minimum role requirement.
     *
     * @return bool
     *   Returns `true` if the user is logged in and satisfies the role
     *   requirement, if any. Otherwise, returns `false`.
     */
    public function Verify(): bool
    {
        $accountView = AccountService::Instance()->LoggedInAccount();
        if ($accountView === null) {
            return false;
        }
        return Role::Parse($accountView->role)->AtLeast($this->minimumRole);
    }
}
