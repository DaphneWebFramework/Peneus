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
use \Harmonia\Server;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Session;
use \Peneus\Api\Hooks\IAccountDeletionHook;
use \Peneus\Model\Account;
use \Peneus\Model\AccountRole;
use \Peneus\Model\PersistentLogin;
use \Peneus\Model\Role;

/**
 * Provides account-related utilities.
 */
class AccountService extends Singleton
{
    /**
     * The duration of the persistent login.
     *
     * The value is a relative interval string that can be passed directly to
     * the PHP `DateTime` constructor (e.g. `'+1 month'`, `'+30 days'`).
     *
     * @link https://www.php.net/manual/en/datetime.formats.php
     * @todo Make this configurable rather than hardcoded.
     */
    private const PERSISTENT_LOGIN_DURATION = '+1 month';

    /** @var IAccountDeletionHook[] */
    private array $deletionHooks;

    private readonly SecurityService $securityService;
    private readonly CookieService $cookieService;
    private readonly Session $session;
    private readonly Request $request;
    private readonly Server $server;

    protected function __construct()
    {
        $this->deletionHooks = [];
        $this->securityService = SecurityService::Instance();
        $this->cookieService = CookieService::Instance();
        $this->session = Session::Instance();
        $this->request = Request::Instance();
        $this->server = Server::Instance();
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
     *
     * @todo Create a periodic cron job to delete expired records:
     *   ```sql
     *   DELETE FROM persistentlogin WHERE timeExpires < NOW()
     *   ```
     */
    public function CreatePersistentLogin(Account $account): void
    {
        // 1
        $clientSignature = $this->clientSignature();
        // 2
        $pl = $this->findPersistentLoginForReuse(
            $account->id,
            $clientSignature
        );
        if ($pl === null) {
            $pl = $this->constructPersistentLogin(
                $account->id,
                $clientSignature
            );
        }
        // 3
        $this->issuePersistentLogin($pl);
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
        $cookieName = $this->persistentLoginCookieName();
        // 1
        $this->cookieService->DeleteCookie($cookieName);
        // 2
        if (!$this->request->Cookies()->Has($cookieName)) {
            return;
        }
        $cookieValue = $this->request->Cookies()->Get($cookieName);
        [$lookupKey] = $this->parsePersistentLoginCookieValue($cookieValue);
        if ($lookupKey === null) {
            return;
        }
        $pl = $this->findPersistentLogin($lookupKey);
        if ($pl === null) {
            return;
        }
        if (!$pl->Delete()) {
            throw new \RuntimeException("Failed to delete persistent login.");
        }
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
     * @return string
     */
    protected function persistentLoginCookieName(): string
    {
        return $this->cookieService->AppSpecificCookieName('PL');
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
        $cookieName = $this->persistentLoginCookieName();
        if (!$this->request->Cookies()->Has($cookieName)) {
            return null;
        }
        $cookieValue = $this->request->Cookies()->Get($cookieName);
        [$lookupKey, $token] = $this->parsePersistentLoginCookieValue($cookieValue);
        if ($lookupKey === null || $token === null) {
            return null;
        }
        // 2
        $pl = $this->findPersistentLogin($lookupKey);
        if ($pl === null ||
            $pl->clientSignature !== $this->clientSignature() ||
            !$this->securityService->VerifyPassword($token, $pl->tokenHash) ||
            $pl->timeExpires < $this->currentTime()
        ) {
            return null;
        }
        // 3
        $account = $this->findAccount($pl->accountId);
        if ($account === null) {
            return null;
        }
        $this->CreateSession($account);
        // 4
        $this->issuePersistentLogin($pl); // Rotate

        return $account;
    }

    /**
     * @param string $lookupKey
     * @return PersistentLogin|null
     */
    protected function findPersistentLogin(string $lookupKey): ?PersistentLogin
    {
        return PersistentLogin::FindFirst(
            condition: 'lookupKey = :lookupKey',
            bindings: ['lookupKey' => $lookupKey]
        );
    }

    /**
     * The purpose of this method is to avoid leaving behind orphaned records
     * when a user clears their cookies. If the same account logs in again with
     * the same browser and from the same IP address (as identified by client
     * signature), we reuse the existing record instead of creating a new one.
     *
     * @param int $accountId
     * @param string $clientSignature
     * @return PersistentLogin|null
     */
    protected function findPersistentLoginForReuse(
        int $accountId,
        string $clientSignature
    ): ?PersistentLogin
    {
        return PersistentLogin::FindFirst(
            condition:
                'accountId = :accountId AND clientSignature = :clientSignature',
            bindings: [
                'accountId' => $accountId,
                'clientSignature' => $clientSignature
            ]
        );
    }

    /**
     * @param int $accountId
     * @param string $clientSignature
     * @return PersistentLogin
     */
    protected function constructPersistentLogin(
        int $accountId,
        string $clientSignature
    ): PersistentLogin
    {
        $pl = new PersistentLogin();
        $pl->accountId = $accountId;
        $pl->clientSignature = $clientSignature;
        return $pl;
    }

    /**
     * @param PersistentLogin $pl
     * @throws \RuntimeException
     */
    protected function issuePersistentLogin(PersistentLogin $pl): void
    {
        // 1
        $token = $this->securityService->GenerateToken();
        // 2
        $pl->lookupKey = $this->securityService->GenerateToken(8); // 64 bits
        $pl->tokenHash = $this->securityService->HashPassword($token);
        $pl->timeExpires = $this->expiryTime();
        if (!$pl->Save()) {
            throw new \RuntimeException("Failed to save persistent login.");
        }
        // 3
        $this->cookieService->SetCookie(
            $this->persistentLoginCookieName(),
            $this->makePersistentLoginCookieValue($pl->lookupKey, $token),
            $pl->timeExpires->getTimestamp()
        );
    }

    /**
     * @param string $lookupKey
     * @param string $token
     * @return string
     */
    protected function makePersistentLoginCookieValue(
        string $lookupKey,
        string $token
    ): string
    {
        return "{$lookupKey}.{$token}";
    }

    /**
     * @param string $cookieValue
     * @return array{0: string|null, 1: string|null}
     */
    protected function parsePersistentLoginCookieValue(
        string $cookieValue
    ): array
    {
        $parts = \explode('.', $cookieValue, 2) + [null, null];
        return \array_map(
            static fn($part) => $part === '' ? null : $part,
            $parts
        );
    }

    /**
     * @return string
     */
    protected function clientSignature(): string
    {
        $clientAddress = $this->server->ClientAddress();
        $userAgent = $this->request->Headers()->GetOrDefault('user-agent', '');
        $hash = \hash('md5', "{$clientAddress}\0{$userAgent}", true);
        return \rtrim(\base64_encode($hash), '=');
    }

    /**
     * @return \DateTime
     */
    protected function currentTime(): \DateTime
    {
        return new \DateTime();
    }

    /**
     * @return \DateTime
     */
    protected function expiryTime(): \DateTime
    {
        return new \DateTime(self::PERSISTENT_LOGIN_DURATION);
    }

    #endregion protected
}
