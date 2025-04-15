<?php declare(strict_types=1);
/**
 * CustomPolicy.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Systems\PageSystem\AccessPolicies;

/**
 * A flexible access policy that delegates access control logic to a user-defined callback.
 *
 * This policy allows project-specific logic to be injected without defining a
 * standalone class. The provided closure is responsible for enforcing access
 * by performing any required checks, redirects, or side effects.
 */
class CustomPolicy implements IAccessPolicy
{
    /**
     * The callback to be executed when the policy is enforced.
     *
     * @var \Closure
     */
    private \Closure $callback;

    /**
     * Constructs a new instance.
     *
     * @param \Closure $callback
     *   A closure that performs access enforcement. It should redirect, send an
     *   error response, or take other appropriate action when access is denied.
     */
    public function __construct(\Closure $callback)
    {
        $this->callback = $callback;
    }

    /**
     * Executes the user-defined callback to enforce access.
     */
    public function Enforce(): void
    {
        ($this->callback)();
    }
}
