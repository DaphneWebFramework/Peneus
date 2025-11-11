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
    public ?int $role;

    public static function ViewDefinition(): string
    {
        return <<<SQL
        SELECT
            Account.id,
            Account.email,
            CASE WHEN Account.passwordHash = ''
                THEN 0 ELSE 1 END AS isLocal,
            Account.displayName,
            Account.timeActivated,
            Account.timeLastLogin,
            AccountRole.role
        FROM
            Account
        LEFT JOIN
            AccountRole ON AccountRole.accountId = Account.id
        SQL;
    }
}
