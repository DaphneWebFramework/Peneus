<?php declare(strict_types=1);
/**
 * HandlerRegistry.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api;

use \Harmonia\Patterns\Singleton;

use \Harmonia\Core\CArray;
use \Peneus\Api\Handlers\IHandler;

class HandlerRegistry extends Singleton
{
    private readonly CArray $handlers;

    protected function __construct()
    {
        $this->handlers = new CArray();
    }

    #region public -------------------------------------------------------------

    public function RegisterHandler(string $handlerName, string $handlerClassName): void
    {
        $handlerName = \trim($handlerName);
        if ($handlerName === '') {
            throw new \InvalidArgumentException('Handler name cannot be empty.');
        }
        $handlerName = \strtolower($handlerName);
        if ($this->handlers->Has($handlerName)) {
            throw new \InvalidArgumentException('Handler already registered.');
        }
        if (!\class_exists($handlerClassName)) {
            throw new \RuntimeException('Handler class not found.');
        }
        if (!\is_subclass_of($handlerClassName, IHandler::class)) {
            throw new \InvalidArgumentException(
                'Handler class must implement IHandler interface.');
        }
        $this->handlers->Set($handlerName, $handlerClassName);
    }

    public function FindHandler(string $handlerName): ?IHandler
    {
        $handlerName = \strtolower(\trim($handlerName));
        if (!$this->handlers->Has($handlerName)) {
            return null;
        }
        $handlerClassName = $this->handlers->Get($handlerName);
        return new $handlerClassName();
    }

    #endregion public
}
