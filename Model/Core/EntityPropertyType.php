<?php declare(strict_types=1);
/**
 * EntityPropertyType.php
 *
 * (C) 2026 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Model\Core;

/**
 * Supported types for entity properties.
 */
enum EntityPropertyType: int
{
    case Boolean     = 1;
    case Integer     = 2;
    case Float       = 3;
    case String      = 4;
    case DateTime    = 5;
    case Enumeration = 6;
}
