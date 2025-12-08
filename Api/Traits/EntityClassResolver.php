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
use \Peneus\Model\PasswordReset;
use \Peneus\Model\PendingAccount;
use \Peneus\Model\PersistentLogin;

use \Peneus\Api\DashboardRegistry;

/**
 * Resolves entity class names based on database table names.
 */
trait EntityClassResolver
{
    /** @var class-string[] */
    private array $builtinEntityClasses = [
        Account::class,
        AccountRole::class,
        PendingAccount::class,
        PasswordReset::class,
        PersistentLogin::class,
    ];

    /**
     * Returns the fully qualified class name of the entity that matches the
     * provided table name.
     *
     * The lookup prioritizes the built-in entity classes first, followed by the
     * application-specific entity classes registered via the DashboardRegistry.
     *
     * @param string $tableName
     *   The name of the table to resolve to an entity class.
     * @return class-string
     *   Fully qualified entity class name.
     * @throws \InvalidArgumentException
     *   If an entity class cannot be resolved for the given table name.
     */
    protected function resolveEntityClass(string $tableName): string
    {
        // 1
        foreach ($this->builtinEntityClasses as $class) {
            if ($tableName === $class::TableName()) {
                return $class;
            }
        }
        // 2
        $class = DashboardRegistry::Instance()->EntityClassFor($tableName);
        if ($class !== null) {
            return $class;
        }
        // 3
        throw new \InvalidArgumentException(
            "Unable to resolve entity class for table: $tableName");
    }
}
