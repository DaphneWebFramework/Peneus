<?php declare(strict_types=1);
/**
 * Action.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Actions;

use \Harmonia\Core\CSequentialArray;
use \Harmonia\Http\StatusCode;
use \Peneus\Api\Guards\IGuard;

/**
 * Base class for API actions with security enforcement.
 */
abstract class Action
{
    private readonly CSequentialArray $guards;

    /**
     * Defines the logic to be executed when the action runs.
     *
     * Implementing classes must override this method to provide the specific
     * functionality of the action.
     *
     * @return mixed
     *   The result of the executed action.
     * @throws \RuntimeException
     *   If any runtime error occurs during the execution of the action.
     */
    abstract protected function onExecute(): mixed;

    #region public -------------------------------------------------------------

    /**
     * Constructs a new instance.
     */
    public function __construct()
    {
        $this->guards = new CSequentialArray();
    }

    /**
     * Adds a guard that must pass verification before the action can execute.
     *
     * @param IGuard $guard
     *   The guard to add.
     * @return self
     *   The current instance.
     */
    public function AddGuard(IGuard $guard): self
    {
        $this->guards->PushBack($guard);
        return $this;
    }

    /**
     * Executes the action after verifying all assigned guards.
     *
     * @return mixed
     *   The result of the executed action.
     * @throws \RuntimeException
     *   If any guard verification fails, or any other runtime error occurs
     *   during the execution of the action.
     */
    public function Execute(): mixed
    {
        foreach ($this->guards as $guard) {
            if (!$guard->Verify()) {
                throw new \RuntimeException(
                    'You do not have permission to perform this action.',
                    StatusCode::Unauthorized->value
                );
            }
        }
        return $this->onExecute();
    }

    #endregion public
}
