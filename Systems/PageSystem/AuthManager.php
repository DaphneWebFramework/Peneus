<?php declare(strict_types=1);
/**
 * AuthManager.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Systems\PageSystem;

use \Harmonia\Core\CUrl;
use \Harmonia\Http\Response;
use \Harmonia\Http\StatusCode;
use \Harmonia\Patterns\CachedValue;
use \Peneus\Model\AccountView;
use \Peneus\Model\Role;
use \Peneus\Resource;
use \Peneus\Services\AccountService;

/**
 * Provides access to the currently logged-in account and associated role,
 * along with role-based access control.
 */
class AuthManager
{
    private readonly CachedValue $loggedInAccount;

    /**
     * Constructs a new instance with uncached account state.
     */
    public function __construct()
    {
        $this->loggedInAccount = new CachedValue();
    }

    /**
     * Returns the currently logged-in user's account.
     *
     * The result is cached after the first retrieval.
     *
     * @return ?AccountView
     *   An `AccountView` object associated with the logged-in user, or `null`
     *   if no user is logged in.
     */
    public function LoggedInAccount(): ?AccountView
    {
        return $this->loggedInAccount->Get(fn() =>
            AccountService::Instance()->LoggedInAccount()
        );
    }

    /**
     * Restricts access to logged-in users.
     *
     * If no user is logged in, the user is redirected to the login page. If a
     * minimum role is specified, the logged-in user's role is checked against
     * it. If the user's role is insufficient, the user is redirected to the
     * error page with an HTTP 401 Unauthorized response.
     *
     * @param Role $minimumRole
     *   (Optional) The minimum role required to access the page. Defaults to
     *   `Role::None`.
     */
    public function RequireLogin(Role $minimumRole = Role::None): void
    {
        $accountView = $this->LoggedInAccount();
        if ($accountView === null) {
            $this->redirect(Resource::Instance()->LoginPageUrl());
        } else if (!Role::Parse($accountView->role)->AtLeast($minimumRole)) {
            $this->redirect(Resource::Instance()->ErrorPageUrl(StatusCode::Unauthorized));
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
