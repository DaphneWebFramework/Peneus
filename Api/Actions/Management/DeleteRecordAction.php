<?php declare(strict_types=1);
/**
 * DeleteRecordAction.php
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
 * Deletes a specific record from a specified table.
 */
class DeleteRecordAction extends Action
{
    use ModelClassResolver;
    use ModelValidationRulesProvider;

    /**
     * Executes the process of deleting a record from a specified table.
     *
     * Validates the table name using the query parameters and the record ID
     * using the form parameters. Resolves the model class based on the table
     * name and attempts to locate the target record by ID. If found, the
     * record is deleted; otherwise, an error is thrown.
     *
     * @return mixed
     *   Always returns `null`.
     * @throws \InvalidArgumentException
     *   If the table name is invalid or the form data fails validation.
     * @throws \RuntimeException
     *   If the record cannot be found or deletion fails.
     */
    protected function onExecute(): mixed
    {
        $validator = new Validator([ 'table' => ['required', 'string'] ]);
        $dataAccessor = $validator->Validate(Request::Instance()->QueryParams());
        $table = $dataAccessor->GetField('table');

        $modelClass = $this->resolveModelClass($table);

        $validator = new Validator($this->validationRulesForDelete());
        $dataAccessor = $validator->Validate(Request::Instance()->FormParams());
        $id = (int)$dataAccessor->GetField('id');

        $entity = $this->findEntity($modelClass, $id);
        if ($entity === null) {
            throw new \RuntimeException(
                "Record with ID $id not found in table '$table'.");
        }
        if (!$entity->Delete()) {
            throw new \RuntimeException(
                "Failed to delete record from table '$table'.");
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
