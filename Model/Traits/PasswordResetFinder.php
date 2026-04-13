<?php declare(strict_types=1);
/**
 * PasswordResetFinder.php
 *
 * (C) 2026 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Model\Traits;

use \Peneus\Model\PasswordReset;

trait PasswordResetFinder
{
    /**
     * @param int $accountId
     * @return PasswordReset|null
     */
    protected function tryFindPasswordResetByAccountId(int $accountId): ?PasswordReset
    {
        return PasswordReset::FindFirst(
            condition: 'accountId = :accountId',
            bindings: ['accountId' => $accountId]
        );
    }

    /**
     * @param string $resetCode
     * @return PasswordReset|null
     */
    protected function tryFindPasswordResetByCode(string $resetCode): ?PasswordReset
    {
        return PasswordReset::FindFirst(
            condition: 'resetCode = :resetCode',
            bindings: ['resetCode' => $resetCode]
        );
    }
}
