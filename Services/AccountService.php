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
use \Peneus\Model\AccountView;

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
     * @param int $accountId
     *   The account ID of an authenticated user.
     * @throws \RuntimeException
     *   If an error occurs while establishing the session or setting the
     *   associated cookie.
     */
    public function CreateSession(int $accountId): void
    {
        // 1
        [$token, $cookieValue] = $this->securityService->GenerateCsrfPair();
        // 2
        $this->session
            ->Start()
            ->Clear()
            ->RenewId()
            ->Set('BINDING_TOKEN', $token)
            ->Set('ACCOUNT_ID', $accountId)
            ->Close();
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
     * Creates a new persistent login entry for an authenticated user.
     *
     * @param int $accountId
     *   The account ID of an authenticated user.
     * @throws \RuntimeException
     *   If an error occurs while storing the persistent login record or setting
     *   the associated cookie.
     */
    public function CreatePersistentLogin(int $accountId): void
    {
        $this->plm->Create($accountId);
    }

    /**
     * Deletes the persistent login entry of the currently logged-in user.
     *
     * @throws \RuntimeException
     *   If an error occurs while deleting the persistent login record or the
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
     * @return ?AccountView
     *   The account of the currently logged-in user, or `null` if no valid
     *   session or persistent login entry is available.
     */
    public function LoggedInAccount(): ?AccountView
    {
        $accountView = $this->accountFromSession();
        if ($accountView !== null) {
            $this->rotatePersistentLoginIfNeeded($accountView->id);
            return $accountView;
        }
        return $this->tryPersistentLogin();
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
     * @param int $id
     * @return AccountView|null
     */
    protected function findAccountView(int $id): ?AccountView
    {
        return AccountView::FindById($id);
    }

    /**
     * @return AccountView|null
     * @throws \RuntimeException
     */
    protected function accountFromSession(): ?AccountView
    {
        $this->session->Start()->Close();
        if (!$this->validateSession()) {
            $this->session->Start()->Destroy();
            return null;
        }
        $accountView = $this->resolveAccountFromSession();
        if ($accountView === null) {
            $this->session->Start()->Destroy();
            return null;
        }
        return $accountView;
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
     * @return AccountView|null
     */
    protected function resolveAccountFromSession(): ?AccountView
    {
        $accountId = $this->session->Get('ACCOUNT_ID');
        if (!\is_int($accountId)) {
            return null;
        }
        return $this->findAccountView($accountId);
    }

    /**
     * @return AccountView|null
     * @throws \RuntimeException
     */
    protected function tryPersistentLogin(): ?AccountView
    {
        // 1
        $accountId = $this->plm->Resolve();
        if ($accountId === null) {
            return null;
        }
        // 2
        $accountView = $this->findAccountView($accountId);
        if ($accountView === null) {
            return null;
        }
        // 3
        $this->CreateSession($accountId);
        // 4. It is important to set the rotation flag after session creation,
        //    so it survives the clear.
        $this->session
            ->Start()
            ->Set('PL_ROTATE_NEEDED', true)
            ->Close();
        // 5
        return $accountView;
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
