<?php declare(strict_types=1);
/**
 * Handler.php
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

use \Harmonia\Http\StatusCode;
use \Peneus\Translation;

/**
 * Base class for API action handlers.
 */
abstract class Handler
{
    /**
     * Creates and returns an instance of the requested action.
     *
     * Implementing classes may apply additional configuration, such as adding
     * guards, before returning the action.
     *
     * @param string $actionName
     *   The name of the action to create. The action name is provided trimmed
     *   and in lowercase.
     * @return ?Action
     *   The created action instance, or `null` if the action is unknown.
     */
    abstract protected function createAction(string $actionName): ?Action;

    #region public -------------------------------------------------------------

    /**
     * Executes an action based on the provided action name.
     *
     * @param string $actionName
     *   The name of the action to execute.
     * @return mixed
     *   The result of the executed action.
     * @throws \RuntimeException
     *   If the action cannot be created due to an unknown action name.
     */
    public function HandleAction(string $actionName): mixed
    {
        $actionName = \strtolower(\trim($actionName));
        $action = $this->createAction($actionName);
        if ($action === null) {
            throw new \RuntimeException(
                Translation::Instance()->Get('error_action_not_found', $actionName),
                StatusCode::NotFound->value
            );
        }
        return $action->Execute();
    }

    #endregion public
}
