<?php declare(strict_types=1);
/**
 * CsrfToken.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Services\Model;

/**
 * Represents a CSRF token and its hashed counterpart stored in a cookie.
 */
class CsrfToken
{
    private readonly string $token;
    private readonly string $cookieValue;

    /**
     * Creates a new instance.
     *
     * @param string $token
     *   The token value.
     * @param string $cookieValue
     *   The token hash stored in a cookie.
     */
    public function __construct(string $token, string $cookieValue)
    {
        $this->token = $token;
        $this->cookieValue = $cookieValue;
    }

    /**
     * Gets the token value.
     *
     * @return string
     *   The token value.
     */
    public function Token(): string
    {
        return $this->token;
    }

    /**
     * Gets the token hash stored in a cookie.
     *
     * @return string
     *   The token hash.
     */
    public function CookieValue(): string
    {
        return $this->cookieValue;
    }
}
