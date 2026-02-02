<?php declare(strict_types=1);
/**
 * CreateRecordAction.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Actions\Management;

use \Peneus\Api\Actions\Action;

use \Harmonia\Http\Request;
use \Harmonia\Systems\ValidationSystem\Validator;
use \Peneus\Api\Traits\EntityClassResolver;
use \Peneus\Api\Traits\EntityValidationRulesProvider;
use \Peneus\Model\Entity;

/**
 * Creates a new record in a specified table.
 */
class CreateRecordAction extends Action
{
    use EntityClassResolver;
    use EntityValidationRulesProvider;

    /**
     * Executes the process of adding a new record to a specified table.
     *
     * Validates the table name from the query parameters and determines
     * the corresponding entity class. Then validates the request body against
     * entity-specific rules. If the record is created successfully, the
     * identifier of the newly inserted record is returned.
     *
     * @return array<string, int>
     *   An associative array with the key 'id', containing the primary key of
     *   the newly created record.
     * @throws \InvalidArgumentException
     *   If the table name is not recognized or the request body fails
     *   validation.
     * @throws \RuntimeException
     *   If the record cannot be created in the data store.
     */
    protected function onExecute(): mixed
    {
        // 1
        $validator = new Validator([ 'table' => ['required', 'string'] ]);
        $dataAccessor = $validator->Validate(Request::Instance()->QueryParams());
        $table = $dataAccessor->GetField('table');
        // 2
        $entityClass = $this->resolveEntityClass($table);
        // 3
        $validator = new Validator($this->validationRulesForCreate($entityClass));
        $dataAccessor = $validator->Validate(Request::Instance()->JsonBody());
        // 4
        $entity = $this->createEntity($entityClass, $dataAccessor->Data());
        if (!$entity->Save()) {
            throw new \RuntimeException("Failed to add record to table '$table'.");
        }
        return [ 'id' => $entity->id ];
    }

    /**
     * @param class-string $entityClass
     * @param array<string, mixed> $data
     * @return Entity
     *
     * @codeCoverageIgnore
     */
    protected function createEntity(string $entityClass, array $data): Entity
    {
        return new $entityClass($data);
    }
}
