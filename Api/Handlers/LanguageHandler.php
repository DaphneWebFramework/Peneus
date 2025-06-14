<?php declare(strict_types=1);
/**
 * LanguageHandler.php
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
use \Peneus\Api\Actions\Language\ChangeAction;
use \Peneus\Services\LanguageService;

/**
 * Handles language-related API actions.
 */
class LanguageHandler extends Handler
{
    protected function createAction(string $actionName): ?Action
    {
        return match ($actionName) {
            'change' => (new ChangeAction)
                ->AddGuard(LanguageService::Instance()->CreateTokenGuard()),
            default => null
        };
    }
}
