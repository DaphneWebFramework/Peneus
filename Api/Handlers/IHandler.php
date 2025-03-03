<?php declare(strict_types=1);
/**
 * IHandler.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Handlers;

/**
 * Defines a contract for API action handlers.
 *
 * Implementing classes must define logic for executing actions based on the
 * provided action name. Additional configuration, such as adding guards,
 * may be performed before execution.
 */
interface IHandler
{
    /**
     * Executes an action based on the provided action name.
     *
     * @param string $actionName
     *   The name of the action to execute.
     * @return mixed
     *   The result of the executed action.
     * @throws \RuntimeException
     *   If the action cannot be executed due to an unknown action name or
     *   another failure.
     */
    public function HandleAction(string $actionName): mixed;
}
