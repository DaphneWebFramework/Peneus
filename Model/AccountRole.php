<?php declare(strict_types=1);
/**
 * AccountRole.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Model;

/**
 * Represents a role assigned to an account.
 */
class AccountRole extends Entity
{
    public int $accountId;
    public int $role;
}
