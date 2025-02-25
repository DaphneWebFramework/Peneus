<?php declare(strict_types=1);
/**
 * Entity.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Model;

use \Harmonia\Database\Database;
use \Harmonia\Database\Queries\DeleteQuery;
use \Harmonia\Database\Queries\InsertQuery;
use \Harmonia\Database\Queries\UpdateQuery;

/**
 * Base class for Active Record entities.
 *
 * This class provides common CRUD (Create, Read, Update, Delete) functionality
 * for database-backed objects. It supports automatic property mapping, database
 * persistence, and deletion.
 *
 * Subclasses should define public properties that correspond to table columns.
 */
abstract class Entity
{
    /**
     * The primary key of the entity in the database.
     *
     * @var int
     */
    public int $id = 0;

    /**
     * Constructs an entity with the given data.
     *
     * @param ?array $data
     *   (Optional) An associative array of property values. Keys must match the
     *   entity's public properties. If `id` is specified, it is also assigned.
     */
    public function __construct(?array $data = null)
    {
        if ($data === null) {
            return;
        }
        foreach ($this->properties(true) as $key => $value) { // Including id
            if (\array_key_exists($key, $data)) {
                $this->$key = $data[$key];
            }
        }
    }

    /**
     * Saves the entity to the database.
     *
     * If `id` is `0`, a new record is inserted. Otherwise, an existing record
     * is updated.
     *
     * @return bool
     *   Returns `true` on success, `false` on failure.
     */
    public function Save(): bool
    {
        return $this->id === 0 ? $this->insert() : $this->update();
    }

    /**
     * Deletes the entity from the database.
     *
     * On successful deletion, the `id` property is set to `0`.
     *
     * @return bool
     *   Returns `true` if the entity was successfully deleted. Returns `false`
     *   if `id` is `0` or if deletion fails.
     */
    public function Delete(): bool
    {
        if ($this->id === 0) {
            return false;
        }
        $query = (new DeleteQuery)
            ->Table(self::tableName())
            ->Where('id = :id')
            ->Bind(['id' => $this->id]);
        $database = Database::Instance();
        $result = $database->Execute($query);
        if ($result === null) {
            return false;
        }
        if ($database->LastAffectedRowCount() !== 1) {
            return false;
        }
        $this->id = 0;
        return true;
    }

    #region private ------------------------------------------------------------

    /**
     * Returns the table name of the entity.
     *
     * @return string
     *   The table name, derived from the entity's class name.
     */
    private static function tableName(): string
    {
        $reflectionClass = new \ReflectionClass(static::class);
        return \strtolower($reflectionClass->getShortName());
    }

    /**
     * Iterates over the public, non-static properties of the entity.
     *
     * @param bool $includingId
     *   (Optional) Whether to include the `id` property. Defaults to `false`.
     * @return \Generator
     *   A generator yielding property names and their values.
     */
    private function properties(bool $includingId = false): \Generator
    {
        $reflectionClass = new \ReflectionClass($this);
        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            $propertyName = $reflectionProperty->getName();
            if (!$includingId && $propertyName === 'id') {
                continue;
            }
            if (!$reflectionProperty->isPublic()) {
                continue;
            }
            if ($reflectionProperty->isStatic()) {
                continue;
            }
            yield $propertyName => $this->$propertyName;
        }
    }

    /**
     * Inserts a new record into the database.
     *
     * @return bool
     *   Returns `true` if insertion succeeds, `false` otherwise.
     */
    private function insert(): bool
    {
        $columns = [];
        $placeholders = [];
        $bindings = [];
        foreach ($this->properties() as $key => $value) {
            $columns[] = $key;
            $placeholders[] = ":{$key}";
            $bindings[$key] = $value;
        }
        $query = (new InsertQuery)
            ->Table(self::tableName())
            ->Columns(...$columns)
            ->Values(...$placeholders)
            ->Bind($bindings);
        $database = Database::Instance();
        $result = $database->Execute($query);
        if ($result === null) {
            return false;
        }
        $this->id = $database->LastInsertId();
        return true;
    }

    /**
     * Updates an existing record in the database.
     *
     * @return bool
     *   Returns `true` if the update succeeds, `false` otherwise.
     */
    private function update(): bool
    {
        $columns = [];
        $placeholders = [];
        $bindings = ['id' => $this->id];
        foreach ($this->properties() as $key => $value) {
            $columns[] = $key;
            $placeholders[] = ":{$key}";
            $bindings[$key] = $value;
        }
        $query = (new UpdateQuery)
            ->Table(self::tableName())
            ->Columns(...$columns)
            ->Values(...$placeholders)
            ->Where('id = :id')
            ->Bind($bindings);
        $database = Database::Instance();
        $result = $database->Execute($query);
        if ($result === null) {
            return false;
        }
        if ($database->LastAffectedRowCount() === -1) {
            return false;
        }
        return true;
    }

    #endregion private
}
