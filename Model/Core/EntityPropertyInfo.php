<?php declare(strict_types=1);
/**
 * EntityPropertyInfo.php
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
 * Provides information about an entity property.
 */
class EntityPropertyInfo
{
    private readonly EntityPropertyType $type;
    private readonly string $class;
    private readonly bool $isNullable;

    private function __construct(
        EntityPropertyType $type,
        string $class,
        bool $isNullable
    ) {
        $this->type = $type;
        $this->class = $class;
        $this->isNullable = $isNullable;
    }

    public static function From(\ReflectionType $reflectionType): ?self
    {
        if (!$reflectionType instanceof \ReflectionNamedType) {
            return null;
        }
        $class = $reflectionType->getName();
        $type = self::resolveType($class)
             ?? self::resolveEnum($class);
        if ($type === null) {
            return null;
        }
        return new self($type, $class, $reflectionType->allowsNull());
    }

    public function Type(): EntityPropertyType
    {
        return $this->type;
    }

    public function Class(): string
    {
        return $this->class;
    }

    public function IsNullable(): bool
    {
        return $this->isNullable;
    }

    public function DefaultValue(): mixed
    {
        return match ($this->type) {
            EntityPropertyType::Boolean     => false,
            EntityPropertyType::Integer     => 0,
            EntityPropertyType::Float       => 0.0,
            EntityPropertyType::String      => '',
            EntityPropertyType::DateTime    => new \DateTime(),
            EntityPropertyType::Enumeration => $this->class::cases()[0]
        };
    }

    public function EnumBackingType(): ?string
    {
        if ($this->type !== EntityPropertyType::Enumeration) {
            return null;
        }
        $reflectionEnum = new \ReflectionEnum($this->class);
        return $reflectionEnum->getBackingType()->getName();
    }

    #region private ------------------------------------------------------------

    private static function resolveType(string $class): ?EntityPropertyType
    {
        return match ($class) {
            'bool'     => EntityPropertyType::Boolean,
            'int'      => EntityPropertyType::Integer,
            'float'    => EntityPropertyType::Float,
            'string'   => EntityPropertyType::String,
            'DateTime' => EntityPropertyType::DateTime,
            default    => null
        };
    }

    private static function resolveEnum(string $class): ?EntityPropertyType
    {
        if (\is_subclass_of($class, \BackedEnum::class) && !empty($class::cases())) {
            return EntityPropertyType::Enumeration;
        }
        return null;
    }

    #endregion private
}
