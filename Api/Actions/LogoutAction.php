<?php declare(strict_types=1);
/**
 * LogoutAction.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Actions;

use \Harmonia\Services\CookieService;
use \Harmonia\Session;
use \Peneus\Services\AccountService;

/**
 * Logs out the current user.
 */
class LogoutAction extends Action
{
    /**
     * Executes the logout process by deleting the session integrity cookie and
     * destroying the user's session.
     *
     * @return mixed
     *   Always returns `null`.
     * @throws \RuntimeException
     *   If the session integrity cookie cannot be deleted, or if the session
     *   cannot be destroyed.
     */
    protected function onExecute(): mixed
    {
        $integrityCookieName = AccountService::Instance()->IntegrityCookieName();
        if (!CookieService::Instance()->DeleteCookie($integrityCookieName)) {
            throw new \RuntimeException('Failed to delete integrity cookie.');
        }
        Session::Instance()->Start()->Destroy();
        return null;
    }
}
