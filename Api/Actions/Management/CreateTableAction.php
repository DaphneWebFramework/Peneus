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
     * @return null
     * @throws \RuntimeException
     */
    protected function onExecute(): mixed
    {
        // 1
        $payload = $this->validatePayload();
        // 2
        if (!$payload->entityClass::CreateTable()) {
            throw new \RuntimeException(
                "Failed to create table for: {$payload->entityClass}");
        }
        return null;
    }

    /**
     * @return object{
     *   entityClass: class-string
     * }
     * @throws \RuntimeException
     */
    protected function validatePayload(): \stdClass
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
        $da = $validator->Validate($this->request->FormParams());
        return (object)[
            'entityClass' => $da->GetField('entityClass')
        ];
    }
}
