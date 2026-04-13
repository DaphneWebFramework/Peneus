<?php declare(strict_types=1);
/**
 * NotRegisteredEnsurer.php
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

trait NotRegisteredEnsurer
{
    /**
     * @param string $email
     * @throws \RuntimeException
     */
    protected function ensureNotRegistered(string $email): void
    {
        if (0 !== Account::Count(
            condition: 'email = :email',
            bindings: ['email' => $email]
        )) {
            throw new \RuntimeException(
                "This account is already registered.",
                StatusCode::Conflict->value
            );
        }
    }
}
