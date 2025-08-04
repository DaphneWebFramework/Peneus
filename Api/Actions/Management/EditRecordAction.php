<?php declare(strict_types=1);
/**
 * EditRecordAction.php
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
 * Updates an existing record in a specified table.
 */
class EditRecordAction extends Action
{
    use ModelClassResolver;
    use ModelValidationRulesProvider;

    /**
     * Executes the process of editing an existing record in a specified table.
     *
     * Validates the table name from the query parameters and determines
     * the corresponding model class. Then validates the request body
     * against model-specific edit rules, including the record ID.
     * If the record is found, its fields are updated and the changes
     * are persisted to the data store.
     *
     * @return mixed
     *   Always returns `null` if the operation is successful.
     * @throws \InvalidArgumentException
     *   If the table name is not recognized or the request body fails
     *   validation.
     * @throws \RuntimeException
     *   If the record cannot be found or saved to the data store.
     */
    protected function onExecute(): mixed
    {
        $validator = new Validator([ 'table' => ['required', 'string'] ]);
        $dataAccessor = $validator->Validate(Request::Instance()->QueryParams());
        $table = $dataAccessor->GetField('table');

        $modelClass = $this->resolveModelClass($table);

        $validator = new Validator($this->validationRulesForEdit($modelClass));
        $dataAccessor = $validator->Validate(Request::Instance()->JsonBody());
        $id = (int)$dataAccessor->GetField('id');

        $entity = $this->findEntity($modelClass, $id);
        if ($entity === null) {
            throw new \RuntimeException(
                "Record with ID $id not found in table '$table'.");
        }
        $entity->Populate($dataAccessor->Data());
        if (!$entity->Save()) {
            throw new \RuntimeException(
                "Failed to edit record with ID $id in table '$table'.");
        }
        return null;
    }

    /**
     * @param class-string $modelClass
     * @param int $id
     * @return ?Entity
     */
    protected function findEntity(string $modelClass, int $id): ?Entity
    {
        return $modelClass::FindById($id);
    }
}
