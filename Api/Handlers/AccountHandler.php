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

use \Peneus\Api\Actions\Action;
use \Peneus\Api\Actions\ActivateAccountAction;
use \Peneus\Api\Actions\LoginAction;
use \Peneus\Api\Actions\LogoutAction;
use \Peneus\Api\Actions\RegisterAccountAction;
use \Peneus\Api\Actions\ResetPasswordAction;
use \Peneus\Api\Actions\SendPasswordResetAction;
use \Peneus\Api\Guards\FormTokenGuard;
use \Peneus\Api\Guards\SessionGuard;

/**
 * Handles account-related API actions.
 */
class AccountHandler extends Handler
{
    protected function createAction(string $actionName): ?Action
    {
        return match ($actionName) {
            'register' => (new RegisterAccountAction)
                ->AddGuard(new FormTokenGuard),
            'activate' => (new ActivateAccountAction)
                ->AddGuard(new FormTokenGuard),
            'login' => (new LoginAction)
                ->AddGuard(new FormTokenGuard),
            'logout' => (new LogoutAction)
                ->AddGuard(new SessionGuard),
            'send-password-reset' => (new SendPasswordResetAction)
                ->AddGuard(new FormTokenGuard),
            'reset-password' => (new ResetPasswordAction)
                ->AddGuard(new FormTokenGuard),
            default => null
        };
    }
}
