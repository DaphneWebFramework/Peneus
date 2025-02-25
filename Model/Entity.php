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
use \Harmonia\Database\Queries\SelectQuery;
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
    #region public -------------------------------------------------------------

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

    #region Instance methods ---------------------------------------------------

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
            ->Table(static::tableName())
            ->Where('id = :id')
            ->Bind(['id' => $this->id]);
        $database = Database::Instance();
        $resultSet = $database->Execute($query);
        if ($resultSet === null) {
            return false;
        }
        if ($database->LastAffectedRowCount() !== 1) {
            return false;
        }
        $this->id = 0;
        return true;
    }

    #endregion Instance methods

    #region Static methods -----------------------------------------------------

    /**
     * Retrieves an entity by its primary key.
     *
     * @param int $id
     *   The primary key of the entity to retrieve.
     * @return static|null
     *   An instance of the called class if a matching record is found,
     *   `null` otherwise.
     */
    public static function FindById(int $id): ?static
    {
        $query = (new SelectQuery)
            ->Table(static::tableName())
            ->Where('id = :id')
            ->Bind(['id' => $id]);
        $database = Database::Instance();
        $resultSet = $database->Execute($query);
        if ($resultSet === null) {
            return null;
        }
        $row = $resultSet->Row();
        if ($row === null) {
            return null;
        }
        return new static($row);
    }

    /**
     * Retrieves the first entity that matches the given condition.
     *
     * @param ?string $condition
     *   (Optional) A filtering expression that determines which entity to
     *   retrieve (e.g., `"status = :status"`). If `null` (default), no
     *   filtering is applied.
     * @param ?array $bindings
     *   (Optional) An associative array of values to replace placeholders
     *   in the condition (e.g., `['status' => 'active']`). If `null` (default),
     *   no parameters are bound.
     * @param ?string $orderBy
     *   (Optional) A sorting expression that determines which matching entity
     *   is returned first (e.g., `"createdAt DESC"`). If `null` (default), no
     *   ordering is applied.
     * @return ?static
     *   An instance of the called class if a matching record is found,
     *   `null` otherwise.
     */
    public static function FindFirst(
        ?string $condition = null,
        ?array $bindings = null,
        ?string $orderBy = null
    ): ?static
    {
        $query = (new SelectQuery)
            ->Table(static::tableName())
            ->Limit(1);
        if ($condition !== null) {
            $query->Where($condition);
        }
        if ($bindings !== null) {
            $query->Bind($bindings);
        }
        if ($orderBy !== null) {
            $query->OrderBy($orderBy);
        }
        $database = Database::Instance();
        $resultSet = $database->Execute($query);
        if ($resultSet === null) {
            return null;
        }
        $row = $resultSet->Row();
        if ($row === null) {
            return null;
        }
        return new static($row);
    }

    /**
     * Retrieves all entities that match the given condition.
     *
     * @param ?string $condition
     *   (Optional) A filtering expression that determines which entities to
     *   retrieve (e.g., `"status = :status"`). If `null` (default), no
     *   filtering is applied.
     * @param ?array $bindings
     *   (Optional) An associative array of values to replace placeholders
     *   in the condition (e.g., `['status' => 'active']`). If `null` (default),
     *   no parameters are bound.
     * @param ?string $orderBy
     *   (Optional) A sorting expression that determines the order of the
     *   returned entities (e.g., `"createdAt DESC"`). If `null` (default), no
     *   ordering is applied.
     * @param ?int $limit
     *   (Optional) The maximum number of entities to return. If `null` (default),
     *   all matching entities are returned.
     * @param ?int $offset
     *   (Optional) The number of entities to skip before returning results. If
     *   `null` (default), no offset is applied.
     * @return array
     *   An array of instances of the called class.
     */
    public static function Find(
        ?string $condition = null,
        ?array $bindings = null,
        ?string $orderBy = null,
        ?int $limit = null,
        ?int $offset = null
    ): array
    {
        $query = (new SelectQuery)
            ->Table(static::tableName());
        if ($condition !== null) {
            $query->Where($condition);
        }
        if ($bindings !== null) {
            $query->Bind($bindings);
        }
        if ($orderBy !== null) {
            $query->OrderBy($orderBy);
        }
        if ($limit !== null) {
            $query->Limit($limit, $offset);
        }
        $database = Database::Instance();
        $resultSet = $database->Execute($query);
        if ($resultSet === null) {
            return [];
        }
        $entities = [];
        while ($row = $resultSet->Row()) {
            $entities[] = new static($row);
        }
        return $entities;
    }

    #endregion Static methods

    #endregion public

    #region protected ----------------------------------------------------------

    /**
     * Returns the table name of the entity.
     *
     * By default, the table name is derived from the entity's class name,
     * converted to lowercase. Subclasses can override this method to specify
     * a custom table name.
     *
     * #### Example
     * ```php
     * class CustomEntity extends Entity
     * {
     *     protected static function tableName(): string
     *     {
     *         return 'custom_table_name';
     *     }
     * }
     * ```
     *
     * @return string
     *   The table name associated with the entity.
     */
    protected static function tableName(): string
    {
        $reflectionClass = new \ReflectionClass(static::class);
        return \strtolower($reflectionClass->getShortName());
    }

    #endregion protected

    #region private ------------------------------------------------------------

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
            ->Table(static::tableName())
            ->Columns(...$columns)
            ->Values(...$placeholders)
            ->Bind($bindings);
        $database = Database::Instance();
        $resultSet = $database->Execute($query);
        if ($resultSet === null) {
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
            ->Table(static::tableName())
            ->Columns(...$columns)
            ->Values(...$placeholders)
            ->Where('id = :id')
            ->Bind($bindings);
        $database = Database::Instance();
        $resultSet = $database->Execute($query);
        if ($resultSet === null) {
            return false;
        }
        if ($database->LastAffectedRowCount() === -1) {
            return false;
        }
        return true;
    }

    #endregion private
}
