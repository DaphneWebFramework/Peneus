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
     * @return array{
     *   id: int
     * }
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function onExecute(): mixed
    {
        // 1
        $payload = $this->validatePayload();
        // 2
        $entity = $this->constructEntity($payload->entityClass, $payload->data);
        if (!$entity->Save()) {
            throw new \RuntimeException("Failed to create record.");
        }
        // 3
        return [
            'id' => $entity->id
        ];
    }

    /**
     * @return object{
     *   entityClass: class-string,
     *   data: array<string, mixed>
     * }
     * @throws \RuntimeException
     */
    protected function validatePayload(): \stdClass
    {
        // 1
        $validator = new Validator([
            'table' => ['required', 'string']
        ]);
        $da = $validator->Validate($this->request->QueryParams());
        $entityClass = $this->resolveEntityClass($da->GetField('table'));
        // 2
        $validator = new Validator(
            $this->validationRulesForCreate($entityClass)
        );
        $da = $validator->Validate($this->request->JsonBody());
        // 3
        return (object)[
            'entityClass' => $entityClass,
            'data'        => $da->Data()
        ];
    }

    /**
     * @param class-string $entityClass
     * @param array<string, mixed> $data
     * @return Entity
     */
    protected function constructEntity(string $entityClass, array $data): Entity
    {
        return new $entityClass($data);
    }
}
