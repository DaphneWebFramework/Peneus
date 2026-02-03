<?php declare(strict_types=1);
/**
 * DropTableAction.php
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
 * Drops the database table associated with the specified entity class.
 */
class DropTableAction extends Action
{
    private readonly Request $request;

    /**
     * Constructs a new instance by initializing dependencies.
     */
    public function __construct()
    {
        parent::__construct();
        $this->request = Request::Instance();
    }

    /**
     * Drops the table in the database corresponding to the given entity class.
     *
     * @return null
     *   Always returns null if the operation is successful.
     * @throws \RuntimeException
     *   If the table could not be dropped for the specified entity class.
     */
    protected function onExecute(): mixed
    {
        // 1
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
        $dataAccessor = $validator->Validate($this->request->FormParams());
        $entityClass = $dataAccessor->GetField('entityClass');
        // 2
        if (!$entityClass::DropTable()) {
            throw new \RuntimeException(
                "Failed to drop table for: $entityClass");
        }
        return null;
    }
}
