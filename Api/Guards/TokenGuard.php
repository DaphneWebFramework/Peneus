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
use \Peneus\Services\CsrfToken;
use \Peneus\Services\SecurityService;

class TokenGuard implements IGuard
{
    private readonly string $token;
    private readonly string $cookieName;

    public function __construct(string $token, string $cookieName)
    {
        $this->token = $token;
        $this->cookieName = $cookieName;
    }

    public function Authorize(): bool
    {
        $cookieValue = Request::Instance()->Cookies()->Get($this->cookieName);
        if ($cookieValue === null) {
            return false;
        }
        $csrfToken = new CsrfToken($this->token, $cookieValue);
        return SecurityService::Instance()->VerifyCsrfToken($csrfToken);
    }
}
