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

class CsrfToken
{
    private readonly string $token;
    private readonly string $cookieValue;

    public function __construct(string $token, string $cookieValue)
    {
        $this->token = $token;
        $this->cookieValue = $cookieValue;
    }

    public function Token(): string
    {
        return $this->token;
    }

    public function CookieValue(): string
    {
        return $this->cookieValue;
    }
}
