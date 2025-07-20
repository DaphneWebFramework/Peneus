<?php declare(strict_types=1);
/**
 * ModelValidationRulesProvider.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Actions\Management;

use \Harmonia\Services\SecurityService;
use \Peneus\Model\Account;
use \Peneus\Model\AccountRole;
use \Peneus\Model\PasswordReset;
use \Peneus\Model\PendingAccount;
use \Peneus\Model\Role;
use \Peneus\Services\AccountService;

/**
 * Provides validation rules for model-specific create/edit operations.
 */
trait ModelValidationRulesProvider
{
    /**
     * Returns the validation rule set for creating a new record of the
     * given model class.
     *
     * @param class-string $modelClass
     *   Fully qualified model class name.
     * @return array<string, array<int, string>>
     *   Field names mapped to rule arrays.
     * @throws \InvalidArgumentException
     *   If the given model class is not supported.
     */
    protected function validationRulesForAdd(string $modelClass): array
    {
        return match ($modelClass) {
            Account::class => [
                'email' => [
                    'required',
                    'email'
                ],
                'passwordHash' => [
                    'required',
                    'regex:' . SecurityService::PASSWORD_HASH_PATTERN
                ],
                'displayName' => [
                    'required',
                    'regex:' . AccountService::DISPLAY_NAME_PATTERN
                ],
                'timeActivated' => [
                    'required',
                    'datetime:Y-m-d H:i:s'
                ],
                'timeLastLogin' => [
                    'required',
                    'nullable',
                    'datetime:Y-m-d H:i:s'
                ],
            ],
            AccountRole::class => [
                'accountId' => [
                    'required',
                    'integer',
                    'min:1'
                ],
                'role' => [
                    'required',
                    'enum:' . Role::class
                ],
            ],
            PendingAccount::class => [
                'email' => [
                    'required',
                    'email'
                ],
                'passwordHash' => [
                    'required',
                    'regex:' . SecurityService::PASSWORD_HASH_PATTERN
                ],
                'displayName' => [
                    'required',
                    'regex:' . AccountService::DISPLAY_NAME_PATTERN
                ],
                'activationCode' => [
                    'required',
                    'regex:' . SecurityService::TOKEN_PATTERN
                ],
                'timeRegistered' => [
                    'required',
                    'datetime:Y-m-d H:i:s'
                ],
            ],
            PasswordReset::class => [
                'accountId' => [
                    'required',
                    'integer',
                    'min:1'
                ],
                'resetCode' => [
                    'required',
                    'regex:' . SecurityService::TOKEN_PATTERN
                ],
                'timeRequested' => [
                    'required',
                    'datetime:Y-m-d H:i:s'
                ],
            ],
            default => throw new \InvalidArgumentException(
                "Unsupported model class: $modelClass"),
        };
    }

    /**
     * Returns the validation rule set for deleting a record of the given model
     * class.
     *
     * @return array<string, array<int, string>>
     *   The 'id' field name mapped to an array of rules.
     */
    protected function validationRulesForDelete(): array
    {
        return [ 'id' => ['required', 'integer', 'min:1'] ];
    }

    /**
     * Returns the validation rule set for editing an existing record of the
     * given model class.
     *
     * @param class-string $modelClass
     *   Fully qualified model class name.
     * @return array<string, array<int, string>>
     *   Field names mapped to rule arrays.
     * @throws \InvalidArgumentException
     *   If the given model class is not supported.
      */
    protected function validationRulesForEdit(string $modelClass): array
    {
        return array_merge(
            $this->validationRulesForDelete(),
            $this->validationRulesForAdd($modelClass)
        );
    }
}
