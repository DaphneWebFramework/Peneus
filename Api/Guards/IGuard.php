<?php declare(strict_types=1);
/**
 * IGuard.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Guards;

/**
 * Defines a contract for security guards.
 *
 * Guards enforce security conditions by determining whether access should
 * be granted or an action should proceed.
 */
interface IGuard
{
    /**
     * Verifies whether the guard's condition is satisfied.
     *
     * @return bool
     *   Returns `true` if verification succeeds, otherwise `false`.
     */
    public function Verify(): bool;
}
