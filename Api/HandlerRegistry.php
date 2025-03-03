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
use \Peneus\Api\Handlers\Handler;

/**
 * Manages the registration and retrieval of API handlers.
 */
class HandlerRegistry extends Singleton
{
    private readonly CArray $handlers;

    /**
     * Constructs a new instance.
     */
    protected function __construct()
    {
        $this->handlers = new CArray();
    }

    #region public -------------------------------------------------------------

    /**
     * Registers a handler class by name.
     *
     * @param string $handlerName
     *   The unique name of the handler.
     * @param string $handlerClassName
     *   The fully qualified class name of the handler.
     * @throws \InvalidArgumentException
     *   If the handler name is empty, already registered, or the class does not
     *   extend the `Handler` class.
     * @throws \RuntimeException
     *   If the specified handler class does not exist.
     */
    public function RegisterHandler(string $handlerName, string $handlerClassName): void
    {
        $handlerName = \trim($handlerName);
        if ($handlerName === '') {
            throw new \InvalidArgumentException(
                'Handler name cannot be empty.');
        }
        $handlerName = \strtolower($handlerName);
        if ($this->handlers->Has($handlerName)) {
            throw new \InvalidArgumentException(
                "Handler already registered: $handlerName");
        }
        if (!\class_exists($handlerClassName)) {
            throw new \RuntimeException(
                "Handler class not found: $handlerClassName");
        }
        if (!\is_subclass_of($handlerClassName, Handler::class)) {
            throw new \InvalidArgumentException(
                "Class must extend Handler class: $handlerClassName");
        }
        $this->handlers->Set($handlerName, $handlerClassName);
    }

    /**
     * Finds and returns an instance of the requested handler.
     *
     * @param string $handlerName
     *   The name of the handler.
     * @return ?Handler
     *   The handler instance if found, otherwise `null`.
     */
    public function FindHandler(string $handlerName): ?Handler
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
