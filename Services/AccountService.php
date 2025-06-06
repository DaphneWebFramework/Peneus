<?php declare(strict_types=1);
/**
 * AccountService.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Services;

use \Harmonia\Patterns\Singleton;

use \Harmonia\Services\CookieService;
use \Harmonia\Session;
use \Peneus\Api\Guards\TokenGuard;
use \Peneus\Model\Account;
use \Peneus\Model\Role;

/**
 * Provides account-related utilities.
 */
class AccountService extends Singleton
{
    #region public -------------------------------------------------------------

    /**
     * The session storage key for the session integrity token.
     *
     * This token ensures that the session is valid and prevents session
     * hijacking by verifying its integrity against its corresponding cookie.
     */
    public const INTEGRITY_TOKEN_SESSION_KEY = 'INTEGRITY_TOKEN';

    /**
     * The session storage key for the logged-in user's account ID.
     *
     * This key stores the user's account ID after successful login and is
     * used to retrieve the associated account details.
     */
    public const ACCOUNT_ID_SESSION_KEY = 'ACCOUNT_ID';

    /**
     * The session storage key for the logged-in user's account role.
     *
     * This key stores the user's account role after successful login and is
     * used to determine the user's permissions and access levels within the
     * application.
     */
    public const ACCOUNT_ROLE_SESSION_KEY = 'ACCOUNT_ROLE';

    /**
     * Regular expression pattern for validating display names.
     *
     * Matches a 2–50 character display name starting with a letter or number,
     * allowing letters, numbers, spaces, dots, hyphens, and apostrophes, with
     * full Unicode support.
     */
    public const DISPLAY_NAME_PATTERN = "/^[\p{L}\p{N}][\p{L}\p{N} .\-']{1,49}$/u";

    /**
     * Returns the name of the session integrity cookie.
     *
     * This cookie stores a hashed version of the session integrity token,
     * ensuring that the session has not been hijacked or tampered with.
     *
     * The generated name includes the application name to avoid conflicts
     * when multiple applications share the same framework.
     *
     * @return string
     *   The session integrity cookie name.
     */
    public function IntegrityCookieName(): string
    {
        return CookieService::Instance()->AppSpecificCookieName('INTEGRITY');
    }

    /**
     * Retrieves the currently logged-in user's account.
     *
     * This method first verifies session integrity by checking whether the
     * session integrity token matches its hashed counterpart stored in the
     * cookie. If validation succeeds, the associated account is retrieved.
     *
     * If the session is compromised or no account is found, the session
     * is destroyed to prevent unauthorized access.
     *
     * @return ?Account
     *   The logged-in user's account, or `null` if no user is logged in or
     *   the session is compromised.
     * @throws \RuntimeException
     *   If the session cannot be started, closed, or destroyed.
     */
    public function LoggedInAccount(): ?Account
    {
        $session = Session::Instance()->Start();
        if (!$this->verifySessionIntegrity($session)) {
            $session->Destroy();
            return null;
        }
        $account = $this->retrieveLoggedInAccount($session);
        if ($account === null) {
            $session->Destroy();
            return null;
        }
        $session->Close();
        return $account;
    }

    /**
     * Retrieves the role of the logged-in user's account.
     *
     * @return ?Role
     *   The role of the logged-in user's account, or `null` if not set in the
     *   session.
     * @throws \RuntimeException
     *   If the session cannot be started or closed.
     */
    public function LoggedInAccountRole(): ?Role
    {
        $session = Session::Instance()->Start();
        $value = $session->Get(self::ACCOUNT_ROLE_SESSION_KEY);
        $session->Close();
        if ($value === null) {
            return null;
        }
        return Role::tryFrom($value);
    }

    #endregion public

    #region protected ----------------------------------------------------------

    /**
     * Verifies the integrity of the session.
     *
     * This method ensures that the session is legitimate by checking if
     * the session integrity token matches its corresponding cookie value.
     * If the values do not match, the session is considered compromised.
     *
     * @param Session $session
     *   The started session instance.
     * @return bool
     *   Returns `true` if the session integrity check passes, `false` otherwise.
     */
    protected function verifySessionIntegrity(Session $session): bool
    {
        $integrityToken = $session->Get(self::INTEGRITY_TOKEN_SESSION_KEY);
        if ($integrityToken === null) {
            return false;
        }
        $guard = new TokenGuard($integrityToken, $this->IntegrityCookieName());
        return $guard->Verify();
    }

    /**
     * Retrieves the logged-in user's account object.
     *
     * This method fetches the account ID stored in the session and looks up
     * the corresponding account in the database.
     *
     * @param Session $session
     *   The started session instance.
     * @return ?Account
     *   The `Account` instance of the logged-in user, or `null` if not found.
     */
    protected function retrieveLoggedInAccount(Session $session): ?Account
    {
        $accountId = $session->Get(self::ACCOUNT_ID_SESSION_KEY);
        if ($accountId === null) {
            return null;
        }
        return Account::FindById($accountId);
    }

    #endregion protected
}
