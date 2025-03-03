<?php declare(strict_types=1);
/**
 * AccountHandler.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Handlers;

use \Harmonia\Http\StatusCode;
use \Peneus\Api\Actions\Action;
use \Peneus\Api\Actions\LogoutAction;
use \Peneus\Api\Guards\SessionGuard;

/**
 * Handles account-related API actions.
 */
class AccountHandler extends Handler
{
    protected function createAction(string $actionName): ?Action
    {
        return match ($actionName) {
            'logout' => (new LogoutAction)
                ->AddGuard(new SessionGuard),
            default => null
        };
    }

}
