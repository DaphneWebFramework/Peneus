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
     * The session storage key for the authenticated user's account ID.
     *
     * This key stores the user’s account ID after successful login and is
     * used to retrieve the associated account details.
     */
    public const ACCOUNT_ID_SESSION_KEY = 'ACCOUNT_ID';

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
        return CookieService::Instance()->GenerateCookieName('INTEGRITY');
    }

    /**
     * Retrieves the currently authenticated account.
     *
     * This method first verifies session integrity by checking whether the
     * session integrity token matches its hashed counterpart stored in the
     * cookie. If validation succeeds, the associated account is retrieved.
     *
     * If the session is compromised or no account is found, the session
     * is destroyed to prevent unauthorized access.
     *
     * @return ?Account
     *   The authenticated account, or `null` if authentication fails.
     * @throws \RuntimeException
     *   If the session cannot be started or destroyed.
     */
    public function GetAuthenticatedAccount(): ?Account
    {
        $session = Session::Instance()->Start();
        if (!$this->verifySessionIntegrity()) {
            $session->Destroy();
            return null;
        }
        $account = $this->retrieveAuthenticatedAccount();
        if ($account === null) {
            $session->Destroy();
            return null;
        }
        return $account;
    }

    #endregion public

    #region protected ----------------------------------------------------------

    /**
     * Verifies the integrity of the current session.
     *
     * This method ensures that the session is legitimate by checking if
     * the session integrity token matches its corresponding cookie value.
     * If the values do not match, the session is considered compromised.
     *
     * @return bool
     *   Returns `true` if the session integrity check passes, `false` otherwise.
     */
    protected function verifySessionIntegrity(): bool
    {
        $integrityToken = Session::Instance()->Get(self::INTEGRITY_TOKEN_SESSION_KEY);
        if ($integrityToken === null) {
            return false;
        }
        $guard = new TokenGuard($integrityToken, $this->IntegrityCookieName());
        return $guard->Verify();
    }

    /**
     * Retrieves the authenticated user's account object.
     *
     * This method fetches the account ID stored in the session and looks up
     * the corresponding account in the database.
     *
     * @return ?Account
     *   The `Account` instance of the authenticated user, or `null` if not found.
     */
    protected function retrieveAuthenticatedAccount(): ?Account
    {
        $accountId = Session::Instance()->Get(self::ACCOUNT_ID_SESSION_KEY);
        if ($accountId === null) {
            return null;
        }
        return $this->findAccountById($accountId);
    }

    /** @codeCoverageIgnore */
    protected function findAccountById(int $accountId): ?Account
    {
        return Account::FindById($accountId);
    }

    #endregion protected
}
