<?php declare(strict_types=1);
/**
 * PersistentLoginManager.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Services;

use \Harmonia\Http\Request;
use \Harmonia\Server;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Peneus\Model\PersistentLogin;

/**
 * Manages the lifecycle of persistent login entries.
 */
class PersistentLoginManager
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
    private const DURATION = '+1 month';

    private readonly SecurityService $securityService;
    private readonly CookieService $cookieService;
    private readonly Request $request;
    private readonly Server $server;

    #region public -------------------------------------------------------------

    /**
     * Constructs a new instance by initializing the dependencies.
     */
    public function __construct()
    {
        $this->securityService = SecurityService::Instance();
        $this->cookieService = CookieService::Instance();
        $this->request = Request::Instance();
        $this->server = Server::Instance();
    }

    /**
     * Creates a new persistent login entry for an authenticated user.
     *
     * @param int $accountId
     *   The account ID of an authenticated user.
     * @throws \RuntimeException
     *   If an error occurs while storing the persistent login record or setting
     *   the associated cookie.
     *
     * @todo Create a periodic cron job to delete expired records:
     *   ```sql
     *   DELETE FROM persistentlogin WHERE timeExpires < NOW()
     *   ```
     */
    public function Create(int $accountId): void
    {
        // 1
        $clientSignature = $this->clientSignature();
        // 2. The purpose of this call is to avoid leaving behind orphaned
        //    records when a user clears their cookies. If the same user logs
        //    in again with the same browser and from the same IP address (as
        //    identified by client signature), we reuse the existing record
        //    instead of creating a new one.
        $pl = $this->findByAccountAndSignature(
            $accountId,
            $clientSignature
        );
        if ($pl === null) {
            $pl = $this->constructEntity(
                $accountId,
                $clientSignature
            );
        }
        // 3
        $this->issue($pl);
    }

    /**
     * Deletes the persistent login entry of the currently logged-in user.
     *
     * @throws \RuntimeException
     *   If an error occurs while deleting the persistent login record or the
     *   associated cookie.
     */
    public function Delete(): void
    {
        // 1
        $cookieName = $this->cookieName();
        // 2
        $this->cookieService->DeleteCookie($cookieName);
        // 3
        if (!$this->request->Cookies()->Has($cookieName)) {
            return;
        }
        $cookieValue = $this->request->Cookies()->Get($cookieName);
        [$lookupKey] = $this->parseCookieValue($cookieValue);
        if ($lookupKey === null) {
            return;
        }
        $pl = $this->findByLookupKey($lookupKey);
        if ($pl === null) {
            return;
        }
        if (!$pl->Delete()) {
            throw new \RuntimeException("Failed to delete persistent login.");
        }
    }

    /**
     * Attempts to resolve the account ID from the persistent login entry.
     *
     * @return int|null
     *   The account ID of the resolved account, or `null` if no valid
     *   persistent login entry is available.
     */
    public function Resolve(): ?int
    {
        // 1
        $cookieName = $this->cookieName();
        if (!$this->request->Cookies()->Has($cookieName)) {
            return null;
        }
        $cookieValue = $this->request->Cookies()->Get($cookieName);
        [$lookupKey, $token] = $this->parseCookieValue($cookieValue);
        if ($lookupKey === null || $token === null) {
            return null;
        }
        // 2
        $pl = $this->findByLookupKey($lookupKey);
        if ($pl === null ||
            $pl->clientSignature !== $this->clientSignature() ||
            !$this->securityService->VerifyPassword($token, $pl->tokenHash) ||
            $pl->timeExpires < $this->currentTime()
        ) {
            return null;
        }
        return $pl->accountId;
    }

    /**
     * Rotates the persistent login entry of the currently logged-in user.
     *
     * @param int $accountId
     *   The account ID of an authenticated user.
     * @throws \RuntimeException
     *   If an error occurs while storing the persistent login record or setting
     *   the associated cookie.
     */
    public function Rotate(int $accountId): void
    {
        $pl = $this->findByAccountAndSignature(
            $accountId,
            $this->clientSignature()
        );
        if ($pl === null) {
            return;
        }
        $this->issue($pl);
    }

    #endregion public

    #region protected ----------------------------------------------------------

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
        return new \DateTime(self::DURATION);
    }

    /**
     * @return string
     */
    protected function cookieName(): string
    {
        return $this->cookieService->AppSpecificCookieName('PL');
    }

    /**
     * @param string $lookupKey
     * @param string $token
     * @return string
     */
    protected function cookieValue(string $lookupKey, string $token): string
    {
        return "{$lookupKey}.{$token}";
    }

    /**
     * @param string $cookieValue
     * @return array{0: string|null, 1: string|null}
     */
    protected function parseCookieValue(string $cookieValue): array
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
     * @param string $lookupKey
     * @return PersistentLogin|null
     */
    protected function findByLookupKey(
        string $lookupKey
    ): ?PersistentLogin
    {
        return PersistentLogin::FindFirst(
            condition: 'lookupKey = :lookupKey',
            bindings: ['lookupKey' => $lookupKey]
        );
    }

    /**
     * @param int $accountId
     * @param string $clientSignature
     * @return PersistentLogin|null
     */
    protected function findByAccountAndSignature(
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
    protected function constructEntity(
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
    protected function issue(PersistentLogin $pl): void
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
            $this->cookieName(),
            $this->cookieValue($pl->lookupKey, $token),
            $pl->timeExpires->getTimestamp()
        );
    }

    #endregion protected
}
