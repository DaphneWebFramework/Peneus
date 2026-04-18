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
use \Harmonia\Systems\DatabaseSystem\Queries\IdentifierEscaper;
use \Harmonia\Systems\DatabaseSystem\Queries\InsertQuery;
use \Harmonia\Systems\DatabaseSystem\Queries\RawQuery;
use \Harmonia\Systems\DatabaseSystem\Queries\SelectQuery;
use \Harmonia\Systems\DatabaseSystem\Queries\UpdateQuery;
use \Harmonia\Systems\DatabaseSystem\ResultSet;
use \Peneus\Model\Core\EntityPropertyInfo;
use \Peneus\Model\Core\EntityPropertyType;

/**
 * Base class for Active Record entities.
 *
 * This class provides common CRUD (Create, Read, Update, Delete) functionality
 * for database-backed objects. It supports automatic property mapping, database
 * persistence, and deletion.
 *
 * Subclasses should define public properties that correspond to table columns.
 */
abstract class Entity implements \JsonSerializable
{
    use IdentifierEscaper;

    /**
     * Standard format for date-time values.
     */
    private const DATETIME_FORMAT = 'Y-m-d H:i:s';

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
     * @param array|object|null $data
     *   (Optional) An associative array or an object containing values for the
     *   entity's public properties. Keys (for arrays) or property names (for
     *   objects) must match the entity's property names. If `id` is specified,
     *   it is also assigned.
     * @throws \InvalidArgumentException
     *   If a property assignment fails due to an invalid value or type mismatch.
     *
     * @see Populate
     */
    public function __construct(array|object|null $data = null)
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
     * If a property is a scalar (`bool`, `int`, `float`, `string`), the value
     * is cast to that scalar type.
     *
     * If a property is a `DateTime`, the value may be a `DateTime` instance or
     * a string in "YYYY-MM-DD" or "YYYY-MM-DD HH:MM:SS" format.
     *
     * Backed enum properties accept either an enum case or a scalar value of
     * the same backing type (int or string).
     *
     * @param array|object $data
     *   An associative array or an object containing values for the entity's
     *   public properties. Keys (for arrays) or property names (for objects)
     *   must match the entity's property names. If `id` is specified, it is
     *   also assigned.
     * @throws \InvalidArgumentException
     *   If a property assignment fails due to an invalid value or type mismatch.
     */
    public function Populate(array|object $data): void
    {
        if (\is_object($data)) {
            $data = \get_object_vars($data);
        }
        foreach ($this->properties() as $propName => $propInfo) {
            if (!\array_key_exists($propName, $data)) {
                continue;
            }
            $value = $data[$propName];
            try {
                if ($value === null) {
                    if (!$propInfo->IsNullable()) {
                        throw new \InvalidArgumentException(
                            "Cannot assign null to non-nullable property.");
                    }
                    $this->$propName = null;
                    continue;
                }
                switch ($propInfo->Type()) {
                case EntityPropertyType::Boolean:
                    $this->$propName = (bool)$value;
                    break;
                case EntityPropertyType::Integer:
                    $this->$propName = (int)$value;
                    break;
                case EntityPropertyType::Float:
                    $this->$propName = (float)$value;
                    break;
                case EntityPropertyType::String:
                    $this->$propName = (string)$value;
                    break;
                case EntityPropertyType::DateTime:
                    $this->$propName = ($value instanceof \DateTime)
                        ? $value
                        : new \DateTime($value);
                    break;
                case EntityPropertyType::Enumeration:
                    $class = $propInfo->Class();
                    $this->$propName = ($value instanceof $class)
                        ? $value
                        : $class::from($value);
                    break;
                default:
                    $this->$propName = $value;
                }
            } catch (\Throwable $e) {
                throw new \InvalidArgumentException(
                    "Failed to set property '{$propName}': {$e->getMessage()}");
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
            ->Where('`id` = :id')
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

    /**
     * Specifies how the entity should be serialized to JSON.
     *
     * @return array
     *   An associative array of property names and their serialized values.
     */
    public function jsonSerialize(): mixed
    {
        $serialized = [];
        foreach ($this->properties() as $propName => $propInfo) {
            $serialized[$propName] = self::scalarize($this->$propName, $propInfo);
        }
        // Ensure 'id' is always the first key in the serialized output.
        if (\array_key_exists('id', $serialized)) {
            $id = $serialized['id'];
            unset($serialized['id']);
            $serialized = ['id' => $id] + $serialized;
        }
        return $serialized;
    }

    /**
     * Serializes the entity to an associative array, excluding specified
     * properties.
     *
     * Uses the same logic as `jsonSerialize`, but allows certain fields to be
     * omitted dynamically.
     *
     * @param string ...$excludes
     *   Property names to exclude from the result.
     * @return array
     *   An associative array of property names and their serialized values,
     *   excluding any specified properties.
     *
     * @see jsonSerialize
     */
    public function Without(string ...$excludes): array
    {
        $serialized = $this->jsonSerialize();
        foreach ($excludes as $propName) {
            unset($serialized[$propName]);
        }
        return $serialized;
    }

    #endregion Instance methods

    #region Static methods -----------------------------------------------------

    /**
     * Determines whether the entity represents a view.
     *
     * This method checks if the entity class extends `ViewEntity`, indicating
     * that it should be treated as a database view rather than a regular table.
     *
     * @return bool
     *   Returns `true` if the entity is a view, `false` if it is a regular
     *   table entity.
     */
    public static function IsView(): bool
    {
        return \is_subclass_of(static::class, ViewEntity::class);
    }

    /**
     * Returns the table name of the entity.
     *
     * By default, the table name is derived from the entity's class name,
     * converted to lowercase.
     *
     * Subclasses can override this method to specify a custom table name.
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
     *   The table name for the entity.
     */
    public static function TableName(): string
    {
        $reflectionClass = new \ReflectionClass(static::class);
        return \strtolower($reflectionClass->getShortName());
    }

    /**
     * Returns metadata for all supported properties of the entity.
     *
     * The `id` column is always placed first, followed by other properties.
     * Each entry contains the property's name, its SQL type ("BIT", "INT",
     * "DOUBLE", "TEXT", or "DATETIME"), and its nullability flag. Backed enums
     * are mapped to "INT" or "TEXT" based on their backing type.
     *
     * @return array<int, array<string, mixed>>
     *   An ordered array of metadata entries for each property. Each entry
     *   is an associative array with keys `name`, `type`, and `nullable`.
     */
    public static function Metadata(): array
    {
        $result = [];
        $instance = new static();
        foreach ($instance->properties() as $propName => $propInfo) {
            $entry = [
                'name' => $propName,
                'type' => match ($propInfo->Type()) {
                    EntityPropertyType::Boolean     => 'BIT',
                    EntityPropertyType::Integer     => 'INT',
                    EntityPropertyType::Float       => 'DOUBLE',
                    EntityPropertyType::String      => 'TEXT',
                    EntityPropertyType::DateTime    => 'DATETIME',
                    EntityPropertyType::Enumeration =>
                        match ($propInfo->EnumBackingType()) {
                            'int'    => 'INT',
                            'string' => 'TEXT'
                        }
                },
                'nullable' => $propInfo->IsNullable()
            ];
            if ($propName === 'id') {
                \array_unshift($result, $entry);
            } else {
                $result[] = $entry;
            }
        }
        return $result;
    }

    /**
     * Checks whether the entity's associated table or view exists in the
     * database.
     *
     * @return bool
     *   Returns `true` if a table or view with the matching name exists,
     *   `false` otherwise.
     */
    public static function TableExists(): bool
    {
        $database = Database::Instance();
        $tableName = $database->EscapeString(static::TableName());
        $query = (new RawQuery)
            ->Sql("SHOW TABLES LIKE '$tableName'");
        $resultSet = $database->Execute($query);
        if ($resultSet === null) {
            return false;
        }
        return $resultSet->RowCount() > 0;
    }

    /**
     * Creates the database table or view for the entity.
     *
     * If the entity is a view (extends `ViewEntity`), a `CREATE OR REPLACE VIEW`
     * statement is executed using the SQL returned from `ViewDefinition` method.
     * Otherwise, a `CREATE TABLE` statement is generated based on the entity's
     * properties.
     *
     * @return bool
     *   Returns `true` on success. Returns `false` if the entity is not a view
     *   and defines no properties (other than `id`) that can be used for table
     *   creation, or if query execution fails.
     */
    public static function CreateTable(): bool
    {
        $tableName = self::escapeIdentifier(static::TableName());
        if (static::IsView())
        {
            $viewDefinition = static::ViewDefinition();
            $sql = "CREATE OR REPLACE VIEW `$tableName` AS $viewDefinition";
        }
        else
        {
            $columns = ['`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY'];
            foreach (static::Metadata() as $column) {
                $name = $column['name'];
                if ($name === 'id') {
                    continue;
                }
                $type = $column['type'];
                $nullability = $column['nullable'] ? 'NULL' : 'NOT NULL';
                $columns[] = "`{$name}` {$type} {$nullability}";
            }
            if (count($columns) === 1) {
                return false;
            }
            $columns = implode(', ', $columns);
            $sql = "CREATE TABLE `$tableName` ($columns) ENGINE=InnoDB";
        }
        $query = (new RawQuery)->Sql($sql);
        $database = Database::Instance();
        return $database->Execute($query) !== null;
    }

    /**
     * Drops the database table or view associated with the entity.
     *
     * @return bool
     *   Returns `true` on success, `false` on failure.
     */
    public static function DropTable(): bool
    {
        $tableName = self::escapeIdentifier(static::TableName());
        $sql = static::IsView()
            ? "DROP VIEW `$tableName`"
            : "DROP TABLE `$tableName`";
        $query = (new RawQuery)->Sql($sql);
        $database = Database::Instance();
        return $database->Execute($query) !== null;
    }

    /**
     * Retrieves an entity by its primary key.
     *
     * @param int $id
     *   The primary key of the entity to retrieve.
     * @return static|null
     *   Returns an instance of the called class if a matching record is found
     *   and the entity can be populated from it; `null` otherwise.
     */
    public static function FindById(int $id): ?static
    {
        $query = (new SelectQuery)
            ->Table(static::TableName())
            ->Where('`id` = :id')
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
        try {
            return new static($row);
        } catch (\Throwable $e) {
            return null;
        }
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
     *   Returns an instance of the called class if a matching record is found
     *   and the entity can be populated from it; `null` otherwise.
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
        try {
            return new static($row);
        } catch (\Throwable $e) {
            return null;
        }
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
     * @return static[]
     *   Returns an array of instances of the called class for all matching
     *   records. Only entities that can be populated from these records are
     *   included. Returns an empty array if no matching rows are found, the
     *   query fails, or no entities can be populated.
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
            try {
                $entities[] = new static($row);
            } catch (\Throwable $e) {
                continue;
            }
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
     *   The number of matching rows. Returns `0` if no matching rows are found
     *   or if the query fails.
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
        foreach ($this->properties() as $propName => $propInfo) {
            if ($propName === 'id') {
                continue;
            }
            $columns[] = "`$propName`";
            $placeholders[] = ":{$propName}";
            $bindings[$propName] = self::scalarize($this->$propName, $propInfo);
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
        foreach ($this->properties() as $propName => $propInfo) {
            if ($propName === 'id') {
                continue;
            }
            $columns[] = "`$propName`";
            $placeholders[] = ":{$propName}";
            $bindings[$propName] = self::scalarize($this->$propName, $propInfo);
        }
        if (empty($columns)) {
            return false;
        }
        $query = (new UpdateQuery)
            ->Table(static::TableName())
            ->Columns(...$columns)
            ->Values(...$placeholders)
            ->Where('`id` = :id')
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
     * Iterates over the properties of this Entity and yields their name and
     * information.
     *
     * Only public, non-static, and non-readonly properties are included.
     * Supported types are `bool`, `int`, `float`, `string`, `DateTime`, and
     * backed enums with at least one case.
     *
     * Nullable properties are supported. Nullability is reflected in the
     * returned information.
     *
     * If a property is uninitialized, it is assigned a safe default before
     * being yielded. Nullable properties receive null. Non-nullable properties
     * receive a type-specific default.
     *
     * @return \Generator<string, EntityPropertyInfo>
     *   Yields a key-value pair where the key is the property name and the
     *   value is an EntityPropertyInfo instance.
     */
    private function properties(): \Generator
    {
        $reflectionClass = new \ReflectionClass($this);
        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            if (!$reflectionProperty->isPublic() ||
                $reflectionProperty->isStatic() ||
                $reflectionProperty->isReadOnly()
            ) {
                continue;
            }
            $reflectionType = $reflectionProperty->getType();
            if ($reflectionType === null) {
                continue;
            }
            $propInfo = EntityPropertyInfo::From($reflectionType);
            if ($propInfo === null) {
                continue;
            }
            $propName = $reflectionProperty->getName();
            // Assign a default value to avoid "Typed property must not be
            // accessed before initialization" errors.
            if (!$reflectionProperty->isInitialized($this)) {
                if ($propInfo->IsNullable()) {
                    $this->$propName = null;
                } else {
                    $this->$propName = $propInfo->DefaultValue();
                }
            }
            // 5
            yield $propName => $propInfo;
        }
    }

    /**
     * Converts a property value into a scalar representation suitable for
     * database storage or serialization.
     *
     * @param mixed $value
     *   The property value to scalarize.
     * @param EntityPropertyInfo $propInfo
     *   Information about the property's type.
     * @return mixed
     *   The scalarized value.
     */
    private static function scalarize(
        mixed $value,
        EntityPropertyInfo $propInfo
    ): mixed
    {
        if ($value === null) {
            return null;
        }
        return match ($propInfo->Type()) {
            EntityPropertyType::DateTime =>
                $value->format(self::DATETIME_FORMAT),
            EntityPropertyType::Enumeration =>
                $value->value,
            default =>
                $value
        };
    }

    #endregion private
}
