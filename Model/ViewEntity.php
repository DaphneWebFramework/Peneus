<?php declare(strict_types=1);
/**
 * ViewEntity.php
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
 * Base class for entities that represent database views.
 *
 * Subclasses must implement the `ViewDefinition` method, which provides a
 * SQL `SELECT` statement. These entities are automatically treated as views,
 * and are created using `CREATE OR REPLACE VIEW` when the `CreateTable` method
 * is invoked.
 */
abstract class ViewEntity extends Entity
{
    /**
     * Returns the SQL `SELECT` statement that defines the view.
     *
     * The returned SQL should not include a trailing semicolon (`;`), although
     * including one does not cause failure.
     *
     * @return string
     *   A `SELECT` query that defines the logical contents of the view.
     *
     * @codeCoverageIgnore
     */
    abstract public static function ViewDefinition(): string;
}
