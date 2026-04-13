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

namespace Peneus\Model\Traits;

use \Peneus\Model\Account;

trait AccountFinder
{
    /**
     * @param int $id
     * @return Account|null
     */
    protected function tryFindAccountById(int $id): ?Account
    {
        return Account::FindById($id);
    }

    /**
     * @param string $email
     * @return Account|null
     */
    protected function tryFindAccountByEmail(string $email): ?Account
    {
        return Account::FindFirst(
            condition: 'email = :email',
            bindings: ['email' => $email]
        );
    }
}
