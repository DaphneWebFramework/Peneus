<?php declare(strict_types=1);
/**
 * Account.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Model;

/**
 * Represents a user account.
 *
 * ```sql
 * CREATE TABLE `account` (
 *   `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 *   `email` TEXT,
 *   `passwordHash` TEXT,
 *   `displayName` TEXT,
 *   `timeActivated` DATETIME,
 *   `timeLastLogin` DATETIME
 * ) ENGINE = InnoDB;
 * ```
 */
class Account extends Entity
{
    public string $email;
    public string $passwordHash;
    public string $displayName;
    public \DateTime $timeActivated;
    public \DateTime $timeLastLogin;
}
