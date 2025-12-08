<?php declare(strict_types=1);
/**
 * EntityValidationRulesProvider.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Traits;

use \Peneus\Model\Account;
use \Peneus\Model\AccountRole;
use \Peneus\Model\PasswordReset;
use \Peneus\Model\PendingAccount;
use \Peneus\Model\PersistentLogin;

use \Harmonia\Services\SecurityService;
use \Peneus\Api\DashboardRegistry;
use \Peneus\Model\Entity;
use \Peneus\Model\Role;
use \Peneus\Services\AccountService;

/**
 * Provides validation rules for entity-specific create/edit operations.
 */
trait EntityValidationRulesProvider
{
    /**
     * Returns the validation rule set for creating a new record of an entity.
     *
     * The lookup prioritizes the built-in entity classes first, followed by the
     * application-specific entity classes registered via the DashboardRegistry.
     *
     * @param class-string $entityClass
     *   The fully qualified class name of the entity.
     * @return array<string, mixed>
     *   The validation rule set.
     * @throws \InvalidArgumentException
     *   If the entity class is not a subclass of `Entity`, or if no validation
     *   rules are defined for the entity.
     */
    protected function validationRulesForCreate(string $entityClass): array
    {
        // 1
        if (!\is_subclass_of($entityClass, Entity::class)) {
            throw new \InvalidArgumentException(
                "Class must be a subclass of Entity class: $entityClass");
        }
        // 2
        $rules = match ($entityClass) {
            Account::class         => $this->rulesOfAccount(),
            AccountRole::class     => $this->rulesOfAccountRole(),
            PendingAccount::class  => $this->rulesOfPendingAccount(),
            PasswordReset::class   => $this->rulesOfPasswordReset(),
            PersistentLogin::class => $this->rulesOfPersistentLogin(),
            default => null
        };
        if ($rules !== null) {
            return $rules;
        }
        // 3
        $tableName = $entityClass::TableName();
        $rules = DashboardRegistry::Instance()->ValidationRulesFor($tableName);
        if ($rules !== null) {
            return $rules;
        }
        // 4
        throw new \InvalidArgumentException(
            "No validation rules found for entity class: $entityClass");
    }

    /**
     * Returns the validation rule set for deleting a record of any entity.
     *
     * @return array<string, mixed>
     *   The validation rule set.
     */
    protected function validationRulesForDelete(): array
    {
        return [
            'id' => [
                'required',
                'integer:strict',
                'min:1'
            ]
        ];
    }

    /**
     * Returns the validation rule set for updating an existing record of an
     * entity.
     *
     * @param class-string $entityClass
     *   The fully qualified class name of the entity.
     * @return array<string, mixed>
     *   The validation rule set.
     * @throws \InvalidArgumentException
     *   If the entity class is not a subclass of `Entity`, or if no validation
     *   rules are defined for the entity.
     */
    protected function validationRulesForUpdate(string $entityClass): array
    {
        return \array_merge(
            $this->validationRulesForDelete(),
            $this->validationRulesForCreate($entityClass)
        );
    }

    #region private ------------------------------------------------------------

    private function rulesOfAccount(): array
    {
        return [
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
        ];
    }

    private function rulesOfAccountRole(): array
    {
        return [
            'accountId' => [
                'required',
                'integer:strict',
                'min:1'
            ],
            'role' => [
                'required',
                'enum:' . Role::class
            ],
        ];
    }

    private function rulesOfPendingAccount(): array
    {
        return [
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
                'regex:' . SecurityService::TOKEN_DEFAULT_PATTERN
            ],
            'timeRegistered' => [
                'required',
                'datetime:Y-m-d H:i:s'
            ],
        ];
    }

    private function rulesOfPasswordReset(): array
    {
        return [
            'accountId' => [
                'required',
                'integer:strict',
                'min:1'
            ],
            'resetCode' => [
                'required',
                'regex:' . SecurityService::TOKEN_DEFAULT_PATTERN
            ],
            'timeRequested' => [
                'required',
                'datetime:Y-m-d H:i:s'
            ],
        ];
    }

    private function rulesOfPersistentLogin(): array
    {
        return [
            'accountId' => [
                'required',
                'integer:strict',
                'min:1'
            ],
            'clientSignature' => [
                'required',
                'regex:/^[0-9a-zA-Z+\/]{22,24}$/'
            ],
            'lookupKey' => [
                'required',
                'regex:/^[0-9a-fA-F]{16}$/'
            ],
            'tokenHash' => [
                'required',
                'regex:' . SecurityService::PASSWORD_HASH_PATTERN
            ],
            'timeExpires' => [
                'required',
                'datetime:Y-m-d H:i:s'
            ],
        ];
    }

    #endregion private
}
