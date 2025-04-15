<?php declare(strict_types=1);
/**
 * IAccessPolicy.php
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
 * Represents an access control policy for a page.
 *
 * Implementations determine whether the current user is allowed to access
 * the page and may perform redirects or other side effects as needed.
 */
interface IAccessPolicy
{
    /**
     * Enforces the access policy.
     *
     * If the access is not permitted, the method may redirect the user,
     * terminate execution, or take any other appropriate action.
     */
    public function Enforce(): void;
}
