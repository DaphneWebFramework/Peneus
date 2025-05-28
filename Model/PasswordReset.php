<?php declare(strict_types=1);
/**
 * PasswordReset.php
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
 * Stores one-time password reset requests initiated by users.
 *
 * ```sql
 * CREATE TABLE `passwordreset` (
 *   `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 *   `accountId` INT NOT NULL,
 *   `resetCode` TEXT NOT NULL,
 *   `timeRequested` DATETIME NOT NULL
 * ) ENGINE = InnoDB;
 * ```
 */
class PasswordReset extends Entity
{
    public int $accountId;
    public string $resetCode;
    public \DateTime $timeRequested;
}
