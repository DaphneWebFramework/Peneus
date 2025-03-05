<?php declare(strict_types=1);
/**
 * TokenGuard.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Guards;

use \Harmonia\Http\Request;
use \Harmonia\Services\Security\CsrfToken;
use \Harmonia\Services\SecurityService;

/**
 * A guard that verifies a token against a hash value stored in a cookie.
 *
 * This class ensures protection against session hijacking and CSRF attacks by
 * comparing the provided token with its hashed counterpart stored in a cookie.
 */
class TokenGuard implements IGuard
{
    private readonly string $token;
    private readonly string $cookieName;

    /**
     * Constructs a new instance with the specified token and cookie name.
     *
     * @param string $token
     *   The token value to verify.
     * @param string $cookieName
     *   The name of the cookie storing the expected token hash.
     */
    public function __construct(string $token, string $cookieName)
    {
        $this->token = $token;
        $this->cookieName = $cookieName;
    }

    /**
     * Verifies whether the provided token matches its hashed version stored
     * in the specified cookie.
     *
     * @return bool
     *   Returns `true` if the cookie exists and its value matches the expected
     *   token, otherwise `false`.
     */
    public function Verify(): bool
    {
        $cookieValue = Request::Instance()->Cookies()->Get($this->cookieName);
        if ($cookieValue === null) {
            return false;
        }
        return SecurityService::Instance()->VerifyCsrfToken(
            new CsrfToken($this->token, $cookieValue));
    }
}
