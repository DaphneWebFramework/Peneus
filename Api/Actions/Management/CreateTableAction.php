<?php declare(strict_types=1);
/**
 * CreateTableAction.php
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
 * Creates a database table for the specified entity class.
 */
class CreateTableAction extends Action
{
    /**
     * Creates a table in the database corresponding to the given entity class.
     *
     * @return null
     *   Always returns `null` if the operation is successful.
     * @throws \RuntimeException
     *   If the table could not be created for the specified entity class.
     */
    protected function onExecute(): mixed
    {
        $validator = new Validator([
            'entityClass' => [
                'required',
                'string',
                function ($value) {
                    if (!\is_subclass_of($value, Entity::class)) {
                        return false;
                    }
                    $rc = new \ReflectionClass($value);
                    return !$rc->isAbstract();
                }
            ]
        ]);
        $dataAccessor = $validator->Validate(Request::Instance()->FormParams());
        $entityClass = $dataAccessor->GetField('entityClass');
        if (!$entityClass::CreateTable()) {
            throw new \RuntimeException(
                "Failed to create table for: $entityClass");
        }
        return null;
    }
}
