<?php declare(strict_types=1);
/**
 * Role.php
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
 * Enumeration of account roles.
 */
enum Role: int
{
    case None   = 0;
    case Editor = 10;
    case Admin  = 20;
}
