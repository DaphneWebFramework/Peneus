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

use \Peneus\Api\Actions\Account\ActivateAction;
use \Peneus\Api\Actions\Account\ChangeDisplayNameAction;
use \Peneus\Api\Actions\Account\ChangePasswordAction;
use \Peneus\Api\Actions\Account\LoginAction;
use \Peneus\Api\Actions\Account\LogoutAction;
use \Peneus\Api\Actions\Account\RegisterAction;
use \Peneus\Api\Actions\Account\ResetPasswordAction;
use \Peneus\Api\Actions\Account\SendPasswordResetAction;
use \Peneus\Api\Actions\Action;
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
            'register' => (new RegisterAction)
                ->AddGuard(new FormTokenGuard),
            'activate' => (new ActivateAction)
                ->AddGuard(new FormTokenGuard),
            'login' => (new LoginAction)
                ->AddGuard(new FormTokenGuard),
            'logout' => (new LogoutAction)
                ->AddGuard(new SessionGuard),
            'send-password-reset' => (new SendPasswordResetAction)
                ->AddGuard(new FormTokenGuard),
            'reset-password' => (new ResetPasswordAction)
                ->AddGuard(new FormTokenGuard),
            'change-display-name' => (new ChangeDisplayNameAction)
                ->AddGuard(new SessionGuard),
            'change-password' => (new ChangePasswordAction)
                ->AddGuard(new SessionGuard),
            default => null
        };
    }
}
