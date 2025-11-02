<?php declare(strict_types=1);
/**
 * EntityClassResolver.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Traits;

use \Peneus\Model\Account;
use \Peneus\Model\AccountRole;
use \Peneus\Model\AccountView;
use \Peneus\Model\PasswordReset;
use \Peneus\Model\PendingAccount;
use \Peneus\Model\PersistentLogin;

/**
 * Provides entity class resolution logic based on a table name string.
 */
trait EntityClassResolver
{
    /** @var class-string[] */
    private array $allowedEntityClasses = [
        Account::class,
        AccountRole::class,
        AccountView::class,
        PasswordReset::class,
        PendingAccount::class,
        PersistentLogin::class,
    ];

    /**
     * Returns the fully qualified class name of the entity that matches the
     * provided table name.
     *
     * @param string $tableName
     *   The name of the table to resolve to an entity class.
     * @return class-string
     *   Fully qualified entity class name.
     * @throws \InvalidArgumentException
     *   If no allowed entity matches the given table name.
     */
    protected function resolveEntityClass(string $tableName): string
    {
        foreach ($this->allowedEntityClasses as $class) {
            if ($tableName === $class::TableName()) {
                return $class;
            }
        }
        throw new \InvalidArgumentException("Table '$tableName' is not allowed.");
    }
}
