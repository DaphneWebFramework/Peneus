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

abstract class Action
{
    private readonly CSequentialArray $guards;

    abstract protected function onExecute(): mixed;

    #region public -------------------------------------------------------------

    public function __construct()
    {
        $this->guards = new CSequentialArray();
    }

    public function AddGuard(IGuard $guard): self
    {
        $this->guards->PushBack($guard);
        return $this;
    }

    public function Execute(): mixed
    {
        foreach ($this->guards as $guard) {
            if (!$guard->Verify()) {
                throw new \Exception(
                    'You do not have permission to execute this action.',
                    StatusCode::Unauthorized->value
                );
            }
        }
        return $this->onExecute();
    }

    #endregion public
}
