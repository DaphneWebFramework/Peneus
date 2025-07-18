<?php declare(strict_types=1);
/**
 * AddRecordAction.php
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
use \Peneus\Model\Entity;

/**
 * Creates and persists a new record into a specified table.
 */
class AddRecordAction extends Action
{
    use ModelClassResolver;
    use ModelValidationRulesProvider;

    /**
     * Executes the process of adding a new record to a specified table.
     *
     * Validates the table name from the query parameters and determines
     * the corresponding model class. Then validates the request body
     * according to model-specific rules, constructs a new model instance
     * from the validated fields, persists it, and returns the identifier
     * of the newly inserted record.
     *
     * @return array<string, int>
     *   An associative array containing the key 'id' mapped to the primary
     *   key of the newly inserted record.
     * @throws \InvalidArgumentException
     *   If the table name is not recognized or the request body fails
     *   validation.
     * @throws \RuntimeException
     *   If the record cannot be saved to the data store.
     */
    protected function onExecute(): mixed
    {
        $validator = new Validator([ 'table' => ['required', 'string'] ]);
        $dataAccessor = $validator->Validate(Request::Instance()->QueryParams());
        $table = $dataAccessor->GetField('table');

        $modelClass = $this->resolveModelClass($table);

        $validator = new Validator($this->validationRulesForAdd($modelClass));
        $dataAccessor = $validator->Validate(Request::Instance()->JsonBody());

        $entity = $this->createEntity($modelClass, $dataAccessor->Data());
        if (!$entity->Save()) {
            throw new \RuntimeException("Failed to add record to table '$table'.");
        }
        return [ 'id' => $entity->id ];
    }

    /**
     * @param class-string $modelClass
     * @param array<string, mixed> $data
     * @return Entity
     *
     * @codeCoverageIgnore
     */
    protected function createEntity(string $modelClass, array $data): Entity
    {
        return new $modelClass($data);
    }
}
