<?php declare(strict_types=1);
/**
 * HeaderTokenGuard.php
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
use \Harmonia\Services\CookieService;

/**
 * A guard that verifies a CSRF token sent via custom HTTP header against
 * a hash value stored in a cookie.
 *
 * This class ensures protection against session hijacking and CSRF attacks by
 * comparing the header-sent token with its hashed counterpart stored in an
 * application-specific cookie.
 */
class HeaderTokenGuard extends TokenGuard
{
    /**
     * The name of the HTTP header that contains the CSRF token.
     */
    public const CSRF_HEADER_NAME = 'x-csrf-token';

    /**
     * Constructs a new instance using the CSRF token from HTTP headers and the
     * application-specific CSRF cookie name.
     */
    public function __construct()
    {
        parent::__construct(
            Request::Instance()->Headers()->GetOrDefault(self::CSRF_HEADER_NAME, ''),
            CookieService::Instance()->CsrfCookieName()
        );
    }
}
