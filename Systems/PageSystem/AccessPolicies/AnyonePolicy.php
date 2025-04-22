<?php declare(strict_types=1);
/**
 * AnyonePolicy.php
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
 * Allows access to anyone, including both logged-in and anonymous users.
 *
 * This is the default policy when no explicit access policy is assigned to a
 * page.
 */
class AnyonePolicy implements IAccessPolicy
{
    /**
     * Allows access unconditionally.
     *
     * This method performs no checks and permits all users to access the page,
     * including both logged-in and anonymous users.
     */
    public function Enforce(): void
    {
        // No restrictions.
    }
}
