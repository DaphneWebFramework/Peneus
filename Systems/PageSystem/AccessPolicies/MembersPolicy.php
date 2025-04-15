<?php declare(strict_types=1);
/**
 * MembersPolicy.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Systems\PageSystem\AccessPolicies;

use \Harmonia\Core\CUrl;
use \Harmonia\Http\Response;
use \Harmonia\Http\StatusCode;
use \Peneus\Model\Role;
use \Peneus\Resource;
use \Peneus\Services\AccountService;

/**
 * Restricts access to authenticated users, optionally requiring a minimum role.
 */
class MembersPolicy implements IAccessPolicy
{
    private Role $minimumRole;

    /**
     * Constructs a new instance with an optional minimum role.
     *
     * @param Role $minimumRole
     *   (Optional) The minimum role required to access the page. Defaults to
     *   `Role::None`.
     */
    public function __construct(Role $minimumRole = Role::None)
    {
        $this->minimumRole = $minimumRole;
    }

    /**
     * Restricts access to authenticated users.
     *
     * If no user is signed in, the user is redirected to the login page. If a
     * minimum role is specified, the authenticated user's role is checked
     * against it. If the user's role is insufficient, an HTTP 401 Unauthorized
     * response is sent and execution is terminated.
     */
    public function Enforce(): void
    {
        $accountService = AccountService::Instance();
        if ($accountService->AuthenticatedAccount() === null) {
            $this->redirect(Resource::Instance()->LoginPageUrl());
        }
        $accountRole = $accountService->RoleOfAuthenticatedAccount() ?? Role::None;
        if ($accountRole->value < $this->minimumRole->value) {
            $this->redirect(Resource::Instance()->ErrorPageUrl(
                StatusCode::Unauthorized
            ));
        }
    }

    #region protected ----------------------------------------------------------

    /** @codeCoverageIgnore */
    protected function redirect(CUrl $url): void
    {
        (new Response)->Redirect($url);
    }

    #endregion protected
}
