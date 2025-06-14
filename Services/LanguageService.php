<?php declare(strict_types=1);
/**
 * LanguageService.php
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

use \Harmonia\Config;
use \Harmonia\Http\Request;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Peneus\Api\Guards\TokenGuard;

/**
 * Provides language-related utilities.
 */
class LanguageService extends Singleton
{
    private const COOKIE_NAME_SUFFIX = 'LANG';
    private const CSRF_COOKIE_NAME_SUFFIX = 'LANG_CSRF';
    private const CSRF_TOKEN_NAME = 'csrfToken';

    private readonly Config $config;
    private readonly Request $request;
    private readonly CookieService $cookieService;
    private readonly SecurityService $securityService;

    protected function __construct()
    {
        $this->config = Config::Instance();
        $this->request = Request::Instance();
        $this->cookieService = CookieService::Instance();
        $this->securityService = SecurityService::Instance();
    }

    #region public -------------------------------------------------------------

    /**
     * Returns the currently configured language code.
     *
     * @return string
     *   The language code, such as "en" for English, "tr" for Turkish, etc.
     *   Defaults to "en" if no language is configured.
     */
    public function CurrentLanguage(): string
    {
        return $this->config->OptionOrDefault('Language', 'en');
    }

    /**
     * Returns the list of supported languages.
     *
     * @return array<string, string>
     *   An associative array of display name to language code mappings, such as
     *   `["English" => "en", "Türkçe" => "tr"]`. Returns an empty array if no
     *   languages are configured.
     */
    public function Languages(): array
    {
        return $this->config->OptionOrDefault('Languages', []);
    }

    /**
     * Determines whether the given language code is supported.
     *
     * @param string $languageCode
     *   A language code such as "en" or "tr".
     * @return bool
     *   Returns `true` if the language is among the configured supported codes.
     *   Otherwise, returns `false`.
     */
    public function IsSupported(string $languageCode): bool
    {
        return \in_array($languageCode, \array_values($this->Languages()), true);
    }

    /**
     * Generates a new CSRF token value and stores its hash in a cookie.
     *
     * This is typically used in client-side requests that require CSRF
     * protection.
     *
     * @return string
     *   The CSRF token to send with the request.
     */
    public function CsrfTokenValue(): string
    {
        $csrfToken = $this->securityService->GenerateCsrfToken();
        $this->cookieService->SetCookie(
            $this->csrfCookieName(),
            $csrfToken->CookieValue()
        );
        return $csrfToken->Token();
    }

    /**
     * Creates a token guard for validating CSRF token integrity.
     *
     * This guard is intended to be used by API handlers that perform
     * language-related operations requiring CSRF protection.
     *
     * @return TokenGuard
     *   A guard instance that encapsulates token and cookie validation logic.
     */
    public function CreateTokenGuard(): TokenGuard
    {
        return new TokenGuard(
            $this->request->FormParams()->GetOrDefault(self::CSRF_TOKEN_NAME, ''),
            $this->csrfCookieName());
    }

    /**
     * Reads the language code from the cookie and invokes the callback.
     *
     * If a valid language code is found in the language cookie, the given
     * callback will be executed with the language code. If the cookie is
     * missing or contains an unsupported value, it will be ignored or deleted.
     *
     * @param callable $onSuccess
     *   A callback to execute if a valid language code is found. It receives
     *   the code as a string.
     */
    public function ReadFromCookie(callable $onSuccess): void
    {
        $cookieName = $this->cookieName();
        $cookieValue = $this->request->Cookies()->Get($cookieName);
        if ($cookieValue === null) {
            return;
        }
        if (!$this->IsSupported($cookieValue)) {
            $this->cookieService->DeleteCookie($cookieName);
            return;
        }
        $onSuccess($cookieValue);
    }

    /**
     * Writes the given language code to the language cookie.
     *
     * @param string $languageCode
     *   The language code to persist, e.g. "en".
     * @param bool $strict
     *   (Optional) Whether to validate the language code before setting the
     *   cookie. Defaults to `true`.
     * @throws \InvalidArgumentException
     *   If `$strict` is `true` and the language code is not supported.
     */
    public function WriteToCookie(string $languageCode, bool $strict = true): void
    {
        if ($strict && !$this->IsSupported($languageCode)) {
            throw new \InvalidArgumentException(
                "Unsupported language code: $languageCode");
        }
        $this->cookieService->SetCookie($this->cookieName(), $languageCode);
    }

    /**
     * Deletes the CSRF cookie.
     *
     * This is typically called after the CSRF token has been verified and the
     * cookie is no longer needed.
     */
    public function DeleteCsrfCookie(): void
    {
        $this->cookieService->DeleteCookie($this->csrfCookieName());
    }

    #endregion public

    #region protected ----------------------------------------------------------

    /**
     * Returns the application-specific name for the language cookie.
     *
     * @return string
     *   The cookie name, e.g. "MYAPP_LANG".
     */
    protected function cookieName(): string
    {
        return $this->cookieService->AppSpecificCookieName(
            self::COOKIE_NAME_SUFFIX);
    }

    /**
     * Returns the application-specific name for the CSRF cookie.
     *
     * This cookie stores the hashed counterpart of the CSRF token generated
     * by `CsrfTokenValue`, and is used for request verification.
     *
     * @return string
     *   The cookie name, e.g. "MYAPP_LANG_CSRF".
     */
    protected function csrfCookieName(): string
    {
        return $this->cookieService->AppSpecificCookieName(
            self::CSRF_COOKIE_NAME_SUFFIX);
    }

    #endregion protected
}
