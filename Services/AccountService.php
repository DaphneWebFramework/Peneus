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

use \Harmonia\Http\Request;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Session;
use \Peneus\Api\Hooks\IAccountDeletionHook;
use \Peneus\Model\Account;
use \Peneus\Model\AccountRole;
use \Peneus\Model\Role;

/**
 * Provides account-related utilities.
 */
class AccountService extends Singleton
{
    /** @var IAccountDeletionHook[] */
    private array $deletionHooks;

    private readonly PersistentLoginManager $plm;
    private readonly SecurityService $securityService;
    private readonly CookieService $cookieService;
    private readonly Session $session;
    private readonly Request $request;

    /**
     * @param PersistentLoginManager|null $plm
     */
    protected function __construct(?PersistentLoginManager $plm = null)
    {
        $this->deletionHooks = [];
        $this->plm = $plm ?? new PersistentLoginManager();
        $this->securityService = SecurityService::Instance();
        $this->cookieService = CookieService::Instance();
        $this->session = Session::Instance();
        $this->request = Request::Instance();
    }

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
     * Creates a new session for an authenticated user.
     *
     * @param Account $account
     *   The account of an authenticated user.
     * @throws \RuntimeException
     *   If an error occurs while establishing the session or setting the
     *   associated cookie.
     */
    public function CreateSession(Account $account): void
    {
        // 1
        [$token, $cookieValue] = $this->securityService->GenerateCsrfPair();
        // 2
        $this->session
            ->Start()
            ->Clear()
            ->RenewId()
            ->Set('BINDING_TOKEN', $token)
            ->Set('ACCOUNT_ID', $account->id);
        $role = $this->findAccountRole($account->id);
        if ($role !== null) {
            $this->session->Set('ACCOUNT_ROLE', $role->value);
        }
        $this->session->Close();
        // 3
        $this->cookieService->SetCookie(
            $this->sessionBindingCookieName(),
            $cookieValue
        );
    }

    /**
     * Deletes the session of the currently logged-in user.
     *
     * @throws \RuntimeException
     *   If an error occurs while deleting the session or the associated cookie.
     */
    public function DeleteSession(): void
    {
        // 1
        $this->cookieService->DeleteCookie($this->sessionBindingCookieName());
        // 2
        $this->session->Start()->Destroy();
    }

    /**
     * Creates a new persistent login for an authenticated user.
     *
     * @param Account $account
     *   The account of an authenticated user.
     * @throws \RuntimeException
     *   If an error occurs while storing the persistent login or setting the
     *   associated cookie.
     */
    public function CreatePersistentLogin(Account $account): void
    {
        $this->plm->Create($account->id);
    }

    /**
     * Deletes the persistent login of the currently logged-in user.
     *
     * @throws \RuntimeException
     *   If an error occurs while deleting the persistent login or the
     *   associated cookie.
     */
    public function DeletePersistentLogin(): void
    {
        $this->plm->Delete();
    }

    /**
     * Retrieves the account of the currently logged-in user.
     *
     * This method first tries to resolve the account from the session. If not
     * found, it attempts to log in the user using the persistent login feature.
     *
     * @return ?Account
     *   The account of the currently logged-in user, or `null` if no valid
     *   session or persistent login is available.
     */
    public function LoggedInAccount(): ?Account
    {
        $account = $this->accountFromSession();
        if ($account !== null) {
            $this->rotatePersistentLoginIfNeeded($account->id);
            return $account;
        }
        return $this->tryPersistentLogin();
    }

    /**
     * Retrieves the role of the currently logged-in user's account.
     *
     * @return ?Role
     *   The role of the currently logged-in user's account, or `null` if no
     *   user is currently logged in or if no role is assigned.
     */
    public function LoggedInAccountRole(): ?Role
    {
        $this->session->Start()->Close();
        $value = $this->session->Get('ACCOUNT_ROLE');
        if (!\is_int($value)) {
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
     * @return string
     */
    protected function sessionBindingCookieName(): string
    {
        return $this->cookieService->AppSpecificCookieName('SB');
    }

    /**
     * @param int $accountId
     * @return Role|null
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
     * @return Account|null
     * @throws \RuntimeException
     */
    protected function accountFromSession(): ?Account
    {
        $this->session->Start()->Close();
        if (!$this->validateSession()) {
            $this->session->Start()->Destroy();
            return null;
        }
        $account = $this->resolveAccountFromSession();
        if ($account === null) {
            $this->session->Start()->Destroy();
            return null;
        }
        return $account;
    }

    /**
     * @return bool
     */
    protected function validateSession(): bool
    {
        $token = $this->session->Get('BINDING_TOKEN');
        if (!\is_string($token)) {
            return false;
        }
        $cookieName = $this->sessionBindingCookieName();
        if (!$this->request->Cookies()->Has($cookieName)) {
            return false;
        }
        $cookieValue = $this->request->Cookies()->Get($cookieName);
        return $this->securityService->VerifyCsrfPair($token, $cookieValue);
    }

    /**
     * @return Account|null
     */
    protected function resolveAccountFromSession(): ?Account
    {
        $accountId = $this->session->Get('ACCOUNT_ID');
        if (!\is_int($accountId)) {
            return null;
        }
        return $this->findAccount($accountId);
    }

    /**
     * @param int $accountId
     * @return Account|null
     */
    protected function findAccount(int $accountId): ?Account
    {
        return Account::FindById($accountId);
    }

    /**
     * @return Account|null
     * @throws \RuntimeException
     */
    protected function tryPersistentLogin(): ?Account
    {
        // 1
        $accountId = $this->plm->Resolve();
        if ($accountId === null) {
            return null;
        }
        // 2
        $account = $this->findAccount($accountId);
        if ($account === null) {
            return null;
        }
        $this->CreateSession($account);
        // 3. It is important to set the rotation flag after session creation,
        //    so it survives the clear.
        $this->session
            ->Start()
            ->Set('PL_ROTATE_NEEDED', true)
            ->Close();

        return $account;
    }

    /**
     * @param int $accountId
     * @throws \RuntimeException
     */
    protected function rotatePersistentLoginIfNeeded(int $accountId): void
    {
        $this->session->Start();
        if ($this->session->Has('PL_ROTATE_NEEDED')) {
            $this->plm->Rotate($accountId);
            $this->session->Remove('PL_ROTATE_NEEDED');
        }
        $this->session->Close();
    }

    #endregion protected
}
