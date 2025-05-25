<?php declare(strict_types=1);
/**
 * PendingAccount.php
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
 * Temporarily stores user registration data until account activation.
 *
 * When a user registers, their information is stored here until they activate
 * their account via the email verification link. Upon activation, the data is
 * migrated to the `account` table and the pending record is removed.
 *
 * ```sql
 * CREATE TABLE `pendingaccount` (
 *   `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 *   `email` TEXT NOT NULL,
 *   `passwordHash` TEXT NOT NULL,
 *   `displayName` TEXT NOT NULL,
 *   `activationCode` TEXT NOT NULL,
 *   `timeRegistered` DATETIME NOT NULL
 * ) ENGINE = InnoDB;
 * ```
 */
class PendingAccount extends Entity
{
    public string $email;
    public string $passwordHash;
    public string $displayName;
    public string $activationCode;
    public \DateTime $timeRegistered;
}
