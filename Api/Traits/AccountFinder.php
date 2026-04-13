<?php declare(strict_types=1);
/**
 * AccountFinder.php
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
use \Peneus\Model\Account;

trait AccountFinder
{
    use \Peneus\Model\Traits\AccountFinder;

    /**
     * @param int $id
     * @return Account
     * @throws \RuntimeException
     */
    protected function findAccount(int $id): Account
    {
        $account = $this->tryFindAccountById($id);
        if ($account === null) {
            throw new \RuntimeException(
                "Account not found.",
                StatusCode::NotFound->value
            );
        }
        return $account;
    }
}
