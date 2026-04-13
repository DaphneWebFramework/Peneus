<?php declare(strict_types=1);
/**
 * PendingAccountFinder.php
 *
 * (C) 2026 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Traits;

use \Harmonia\Http\StatusCode;
use \Peneus\Model\PendingAccount;

trait PendingAccountFinder
{
    /**
     * @param string $activationCode
     * @return PendingAccount
     * @throws \RuntimeException
     */
    protected function findPendingAccount(string $activationCode): PendingAccount
    {
        $pendingAccount = PendingAccount::FindFirst(
            condition: 'activationCode = :activationCode',
            bindings: ['activationCode' => $activationCode]
        );
        if ($pendingAccount === null) {
            throw new \RuntimeException(
                "No account is awaiting activation for the given code.",
                StatusCode::NotFound->value
            );
        }
        return $pendingAccount;
    }
}
