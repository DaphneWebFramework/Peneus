<?php declare(strict_types=1);
/**
 * FormTokenGuard.php
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
 * A guard that verifies a form-submitted CSRF token against a hash value stored
 * in a cookie.
 *
 * This class ensures protection against session hijacking and CSRF attacks by
 * comparing the form-submitted token with its hashed counterpart stored in an
 * application-specific cookie.
 */
class FormTokenGuard extends TokenGuard
{
    /**
     * The name of the form field that contains the CSRF token submitted.
     */
    public const CSRF_TOKEN_NAME = 'csrfToken';

    /**
     * Constructs a new instance using the form-submitted CSRF token and the
     * application-specific CSRF cookie name.
     */
    public function __construct()
    {
        parent::__construct(
            Request::Instance()->FormParams()->GetOrDefault(self::CSRF_TOKEN_NAME, ''),
            CookieService::Instance()->CsrfCookieName()
        );
    }
}
