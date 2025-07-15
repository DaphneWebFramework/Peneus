<?php declare(strict_types=1);
/**
 * ModelClassResolver.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Actions\Management;

use \Peneus\Model\Account;
use \Peneus\Model\AccountRole;
use \Peneus\Model\PasswordReset;
use \Peneus\Model\PendingAccount;

/**
 * Provides model resolution logic based on a table name string.
 */
trait ModelClassResolver
{
    /** @var class-string[] */
    private array $allowedModelClasses = [
        Account::class,
        AccountRole::class,
        PasswordReset::class,
        PendingAccount::class,
    ];

    /**
     * Returns the fully qualified class name of the model that matches the
     * provided table name.
     *
     * @param string $tableName
     *   The name of the table to resolve to a model class.
     * @return class-string
     *   Fully qualified model class name.
     * @throws \InvalidArgumentException
     *   If no allowed model matches the given table name.
     */
    protected function resolveModelClass(string $tableName): string
    {
        foreach ($this->allowedModelClasses as $class) {
            if ($tableName === $class::TableName()) {
                return $class;
            }
        }
        throw new \InvalidArgumentException("Table '$tableName' is not allowed.");
    }
}
