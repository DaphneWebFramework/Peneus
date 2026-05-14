<?php declare(strict_types=1);
/**
 * AccountView.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Model;

class AccountView extends ViewEntity
{
    public string $email;
    public bool $isLocal;
    public string $displayName;
    public \DateTime $timeActivated;
    public ?\DateTime $timeLastLogin;
    public Role $role;

    /** @codeCoverageIgnore */
    public static function ViewDefinition(): string
    {
        $defaultRole = Role::None->value;

        return <<<SQL
        SELECT
            account.id,
            account.email,
            CASE WHEN account.passwordHash = ''
                THEN 0 ELSE 1 END AS isLocal,
            account.displayName,
            account.timeActivated,
            account.timeLastLogin,
            COALESCE(accountrole.role, {$defaultRole}) AS role
        FROM
            account
        LEFT JOIN
            accountrole ON accountrole.accountId = account.id
        SQL;
    }
}
