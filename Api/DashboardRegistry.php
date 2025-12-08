<?php declare(strict_types=1);
/**
 * DashboardRegistry.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api;

use \Harmonia\Patterns\Singleton;

use \Harmonia\Core\CArray;
use \Peneus\Model\Entity;

/**
 * A registry that stores application-specific entity class names and their
 * validation rules, enabling the Management API to operate on them.
 */
class DashboardRegistry extends Singleton
{
    private readonly CArray $registry;

    protected function __construct()
    {
        $this->registry = new CArray();
    }

    #region public -------------------------------------------------------------

    /**
     * Registers an entity class along with its validation rules.
     *
     * @param class-string $entityClass
     *   The fully qualified class name of the entity.
     * @param array<string, mixed> $validationRules
     *   The validation rule set for creating a new record of the entity.
     * @throws \InvalidArgumentException
     *   If the entity class is not a subclass of `Entity`.
     */
    public function Register(string $entityClass, array $validationRules): void
    {
        if (!\is_subclass_of($entityClass, Entity::class)) {
            throw new \InvalidArgumentException(
                "Class must be a subclass of Entity class: $entityClass");
        }
        $this->registry->Set($entityClass::TableName(), [
            'class' => $entityClass,
            'rules' => $validationRules
        ]);
    }

    /**
     * Returns the fully qualified class name of the entity that matches the
     * provided table name.
     *
     * @param string $tableName
     *   The name of the table to resolve to an entity class.
     * @return ?string
     *   Fully qualified entity class name if found, otherwise `null`.
     */
    public function EntityClassFor(string $tableName): ?string
    {
        if (!$this->registry->Has($tableName)) {
            return null;
        }
        return $this->registry->Get($tableName)['class'];
    }

    /**
     * Returns the validation rule set of the entity that matches the provided
     * table name.
     *
     * @param string $tableName
     *   The name of the table to resolve to a validation rule set.
     * @return ?array
     *   The validation rule set if found, otherwise `null`.
     */
    public function ValidationRulesFor(string $tableName): ?array
    {
        if (!$this->registry->Has($tableName)) {
            return null;
        }
        return $this->registry->Get($tableName)['rules'];
    }

    #endregion public
}
