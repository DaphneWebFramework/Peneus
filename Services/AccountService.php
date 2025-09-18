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
use \Harmonia\Services\SecurityService;
use \Harmonia\Session;
use \Peneus\Api\Guards\TokenGuard;
use \Peneus\Api\Hooks\IAccountDeletionHook;
use \Peneus\Model\Account;
use \Peneus\Model\AccountRole;
use \Peneus\Model\Role;

/**
 * Provides account-related utilities.
 */
class AccountService extends Singleton
{
    /**
     * The suffix used for the integrity cookie name.
     */
    private const INTEGRITY_COOKIE_NAME_SUFFIX = 'INTEGRITY';

    /**
     * The session storage key for the session integrity token.
     *
     * This token ensures that the session is valid and prevents session
     * hijacking by verifying its integrity against its corresponding cookie.
     */
    private const INTEGRITY_TOKEN_SESSION_KEY = 'INTEGRITY_TOKEN';

    /**
     * The session storage key for the logged-in user's account ID.
     *
     * This key stores the user's account ID after successful login and is
     * used to retrieve the associated account details.
     */
    private const ACCOUNT_ID_SESSION_KEY = 'ACCOUNT_ID';

    /**
     * The session storage key for the logged-in user's account role.
     *
     * This key stores the user's account role after successful login and is
     * used to determine the user's permissions and access levels within the
     * application.
     */
    private const ACCOUNT_ROLE_SESSION_KEY = 'ACCOUNT_ROLE';

    /**
     * Hooks that will be called before an account is deleted.
     *
     * @var IAccountDeletionHook[]
     */
    private array $deletionHooks = [];

    #region public -------------------------------------------------------------

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
        return CookieService::Instance()->
            AppSpecificCookieName(self::INTEGRITY_COOKIE_NAME_SUFFIX);
    }

    /**
     * Establishes session integrity for a newly logged-in account.
     *
     * This method binds the server-side session to the authenticated user by
     * generating a cryptographically strong integrity token, storing it in the
     * session, and setting its corresponding cookie on the client. The token is
     * later verified on each request to detect and prevent session hijacking or
     * fixation attacks. In addition, the method stores the account ID and role
     * in the session to support authorization checks.
     *
     * @param Account $account
     *   The authenticated account for which to establish session integrity.
     * @return bool
     *   Returns `true` if the session integrity was successfully established,
     *   or `false` if an error occurred during the process.
     */
    public function EstablishSessionIntegrity(Account $account): bool
    {
        $integrity = SecurityService::Instance()->GenerateCsrfToken();
        try {
            $session = Session::Instance()
                ->Start()
                ->Clear()
                ->RenewId()
                ->Set(self::INTEGRITY_TOKEN_SESSION_KEY, $integrity->Token())
                ->Set(self::ACCOUNT_ID_SESSION_KEY, $account->id);
            $role = $this->findAccountRole($account->id);
            if ($role !== null) {
                $session->Set(self::ACCOUNT_ROLE_SESSION_KEY, $role->value);
            }
            $session->Close();
            CookieService::Instance()->SetCookie(
                $this->IntegrityCookieName(),
                $integrity->CookieValue()
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
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
        // Since the session will be used for read-only purposes, we close it
        // immediately after loading the data to avoid locking issues, such as
        // during concurrent AJAX calls.
        $session = Session::Instance()->Start()->Close();
        if (!$this->verifySessionIntegrity($session)) {
            $session->Start()->Destroy();
            return null;
        }
        $account = $this->retrieveLoggedInAccount($session);
        if ($account === null) {
            $session->Start()->Destroy();
            return null;
        }
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
        $session = Session::Instance()->Start()->Close();
        $value = $session->Get(self::ACCOUNT_ROLE_SESSION_KEY);
        if ($value === null) {
            return null;
        }
        return Role::tryFrom($value);
    }

    /**
     * Registers a hook to be triggered during account deletion.
     *
     * @param IAccountDeletionHook $hook
     *   The hook implementation to be registered.
     */
    public function RegisterDeletionHook(IAccountDeletionHook $hook): void
    {
        $this->deletionHooks[] = $hook;
    }

    /**
     * Returns all registered account deletion hooks.
     *
     * @return IAccountDeletionHook[]
     *   An array of registered deletion hook instances.
     */
    public function DeletionHooks(): array
    {
        return $this->deletionHooks;
    }

    #endregion public

    #region protected ----------------------------------------------------------

    /**
     * Finds the role of an account from the database.
     *
     * @param int $accountId
     *   The ID of the account to find the role of.
     * @return ?Role
     *   The role of the account, or `null` if not found.
     */
    protected function findAccountRole(int $accountId): ?Role
    {
        $accountRole = AccountRole::FindFirst(
            condition: 'accountId = :accountId',
            bindings: ['accountId' => $accountId]
        );
        if ($accountRole === null) {
            return null;
        }
        return Role::tryFrom($accountRole->role);
    }

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
        $guard = $this->createTokenGuard(
            $integrityToken,
            $this->IntegrityCookieName()
        );
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

    /**
     * @param string $token
     * @param string $cookieName
     * @return TokenGuard
     *
     * @codeCoverageIgnore
     */
    protected function createTokenGuard(string $token, string $cookieName): TokenGuard
    {
        return new TokenGuard($token, $cookieName);
    }

    #endregion protected
}
