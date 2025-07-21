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

use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Queries\DeleteQuery;
use \Harmonia\Systems\DatabaseSystem\Queries\InsertQuery;
use \Harmonia\Systems\DatabaseSystem\Queries\SelectQuery;
use \Harmonia\Systems\DatabaseSystem\Queries\UpdateQuery;
use \Harmonia\Systems\DatabaseSystem\ResultSet;

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
     * If a corresponding property is a `DateTime` instance, it will be updated
     * using a provided value in string format (e.g., `'2025-03-15 12:45:00'`,
     * `'2025-03-15'`).
     *
     * @param ?array $data
     *   (Optional) An associative array of property values. Keys must match the
     *   entity's public properties. If `id` is specified, it is also assigned.
     * @throws \InvalidArgumentException
     *   If a property assignment fails due to an invalid value or type mismatch.
     */
    public function __construct(?array $data = null)
    {
        if ($data === null) {
            return;
        }
        $this->Populate($data);
    }

    #region Instance methods ---------------------------------------------------

    /**
     * Populates the entity's properties with the given data.
     *
     * If a corresponding property is a `DateTime` instance, it will be updated
     * using a provided value in string format (e.g., `'2025-03-15 12:45:00'`,
     * `'2025-03-15'`).
     *
     * @param array $data
     *   An associative array of property values. Keys must match the entity's
     *   public properties. If `id` is specified, it is also assigned.
     * @throws \InvalidArgumentException
     *   If a property assignment fails due to an invalid value or type mismatch.
     */
    public function Populate(array $data): void
    {
        foreach ($this->properties() as $key => $_) {
            if (!\array_key_exists($key, $data)) {
                // Skip properties that are not present in the data.
                continue;
            }
            $value = $data[$key];
            try {
                if ($this->$key instanceof \DateTime && \is_string($value)) {
                    $this->$key->modify($value);
                } else {
                    $this->$key = $value;
                }
            } catch (\Throwable $e) {
                throw new \InvalidArgumentException(
                    "Failed to assign value to property '{$key}'.", 0, $e);
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
        if ($this->id === 0) {
            return $this->insert();
        } else {
            return $this->update();
        }
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
            ->Table(static::TableName())
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
     *     public static function TableName(): string
     *     {
     *         return 'custom_table_name';
     *     }
     * }
     * ```
     *
     * @return string
     *   The table name associated with the entity.
     */
    public static function TableName(): string
    {
        $reflectionClass = new \ReflectionClass(static::class);
        return \strtolower($reflectionClass->getShortName());
    }

    /**
     * Returns the column names associated with the entity.
     *
     * The `id` column is always placed first, followed by all other public,
     * non-static, non-readonly properties whose values are considered bindable.
     * Bindable values exclude arrays, resources, and objects lacking a
     * `__toString()` method.
     *
     * @return string[]
     *   An ordered list of column names.
     */
    public static function Columns(): array
    {
        $columns = [];
        $instance = new static();
        foreach ($instance->properties() as $key => $value) {
            if ($key === 'id') {
                \array_unshift($columns, 'id'); // Push 'id' to the front
            } else if (self::isBindable($value)) {
                $columns[] = $key;
            }
        }
        return $columns;
    }

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
            ->Table(static::TableName())
            ->Where('id = :id')
            ->Bind(['id' => $id])
            ->Limit(1);
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
            ->Table(static::TableName())
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
            ->Table(static::TableName());
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

    /**
     * Returns the number of rows in the associated table that match a condition.
     *
     * @param ?string $condition
     *   (Optional) A filtering expression for rows to count (e.g.,
     *   `"status = :status"`). If `null`, all rows are counted.
     * @param ?array $bindings
     *   (Optional) An associative array of values to bind to placeholders
     *   in the condition (e.g., `['status' => 'active']`). If `null`, no
     *   bindings are applied.
     * @return int
     *   The number of matching rows. Returns `0` if the query fails.
     */
    public static function Count(
        ?string $condition = null,
        ?array $bindings = null
    ): int
    {
        $query = (new SelectQuery)
            ->Table(static::TableName())
            ->Columns('COUNT(*)');
        if ($condition !== null) {
            $query->Where($condition);
        }
        if ($bindings !== null) {
            $query->Bind($bindings);
        }
        $database = Database::Instance();
        $resultSet = $database->Execute($query);
        if ($resultSet === null) {
            return 0;
        }
        $row = $resultSet->Row(ResultSet::ROW_MODE_NUMERIC);
        if ($row === null) {
            return 0;
        }
        if (!isset($row[0])) {
            return 0;
        }
        return (int)$row[0];
    }

    #endregion Static methods

    #endregion public

    #region protected ----------------------------------------------------------

    /**
     * Inserts a new record into the database.
     *
     * @return bool
     *   Returns `true` if insertion succeeds, `false` otherwise.
     */
    protected function insert(): bool
    {
        $columns = [];
        $placeholders = [];
        $bindings = [];
        foreach ($this->properties() as $key => $value) {
            if ($key === 'id') {
                continue;
            }
            if (!self::isBindable($value)) {
                continue;
            }
            $columns[] = $key;
            $placeholders[] = ":{$key}";
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }
            $bindings[$key] = $value;
        }
        if (empty($columns)) {
            return false;
        }
        $query = (new InsertQuery)
            ->Table(static::TableName())
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
    protected function update(): bool
    {
        $columns = [];
        $placeholders = [];
        $bindings = ['id' => $this->id];
        foreach ($this->properties() as $key => $value) {
            if ($key === 'id') {
                continue;
            }
            if (!self::isBindable($value)) {
                continue;
            }
            $columns[] = $key;
            $placeholders[] = ":{$key}";
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }
            $bindings[$key] = $value;
        }
        if (empty($columns)) {
            return false;
        }
        $query = (new UpdateQuery)
            ->Table(static::TableName())
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

    #endregion protected

    #region private ------------------------------------------------------------

    /**
     * Checks if a value can be safely bound in a query.
     *
     * @param mixed $value
     *   The value to check.
     * @return bool
     *   Returns `true` if the value is bindable, `false` otherwise.
     */
    private static function isBindable(mixed $value): bool
    {
        if (\is_array($value) || \is_resource($value)) {
            return false;
        }
        if (\is_object($value)) {
            if ($value instanceof \DateTimeInterface) {
                return true;
            }
            return \method_exists($value, '__toString');
        }
        return true;
    }

    /**
     * Iterates over the public, non-static properties of the entity.
     *
     * This method uses reflection to retrieve the properties of the entity and
     * initializes them if necessary. It ensures that uninitialized properties
     * are assigned safe default values based on their type. Primitive types
     * (`bool`, `int`, `float`, `string`, `array`) receive their standard PHP
     * defaults, while `mixed` properties are always initialized to `null`.
     * Class-type properties are instantiated if the class exists.
     *
     * Properties are skipped if they are non-public, static, readonly, union,
     * intersection, or a class type that does not exist and is not nullable.
     * Instantiation of class-type properties is skipped if their constructor
     * requires arguments or is inaccessible. The primitive types `object`,
     * `resource`, `callable`, and `iterable` are skipped, unless nullable, in
     * which case they are assigned `null`.
     *
     * @return \Generator
     *   A generator yielding property names and their values.
     */
    private function properties(): \Generator
    {
        $reflectionClass = new \ReflectionClass($this);
        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            if (!$reflectionProperty->isPublic()) {
                continue;
            }
            if ($reflectionProperty->isStatic()) {
                continue;
            }
            if ($reflectionProperty->isReadOnly()) {
                continue;
            }
            $key = $reflectionProperty->getName();
            if ($reflectionProperty->isInitialized($this)) {
                // If the property is already initialized, yield it immediately.
                // This includes untyped properties, which are always implicitly
                // initialized to null.
                yield $key => $this->$key;
                continue;
            }
            $reflectionType = $reflectionProperty->getType();
            if (!$reflectionType instanceof \ReflectionNamedType) {
                // Skip properties with union or intersection types.
                continue;
            }
            $typeName = $reflectionType->getName();
            switch ($typeName)
            {
            case 'bool'  : $this->$key = false; break;
            case 'int'   : $this->$key = 0    ; break;
            case 'float' : $this->$key = 0.0  ; break;
            case 'string': $this->$key = ''   ; break;
            case 'array' : $this->$key = []   ; break;
            case 'mixed' : $this->$key = null ; break;
            default:
                if (\class_exists($typeName, false)) {
                    try {
                        $this->$key = new $typeName();
                    } catch (\Throwable $e) {
                        continue 2; // foreach
                    }
                } elseif ($reflectionType->allowsNull()) {
                    $this->$key = null;
                } else {
                    continue 2; // foreach
                }
            }
            yield $key => $this->$key;
        }
    }

    #endregion private
}
