<?php declare(strict_types=1);
/**
 * ListRecordsAction.php
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

/**
 * Returns a paginated list of records from a specified table.
 */
class ListRecordsAction extends Action
{
    use EntityClassResolver;

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
     *   data: object[],
     *   total: int
     * }
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function onExecute(): mixed
    {
        // 1
        $payload = $this->validatePayload();
        // 2
        $entityClass = $this->resolveEntityClass($payload->table);
        $columns = \array_column($entityClass::Metadata(), 'name');
        // 3
        $condition = null;
        $bindings = null;
        if ($payload->search !== null) {
            $search = \strtr($payload->search, [
                '\\' => '\\\\',
                '%'  => '\%',
                '_'  => '\_'
            ]);
            $conditions = [];
            foreach ($columns as $columnName) {
                $conditions[] = "`$columnName` LIKE :search";
            }
            if (!empty($conditions)) {
                $condition = \implode(' OR ', $conditions);
                $bindings = ['search' => "%{$search}%"];
            }
        }
        // 4
        $orderBy = null;
        if ($payload->sortkey !== null) {
            if (!\in_array($payload->sortkey, $columns, true)) {
                throw new \InvalidArgumentException(
                    "Table '{$entityClass::TableName()}' does not have a "
                  . "column named '{$payload->sortkey}'.");
            }
            $orderBy = "`{$payload->sortkey}`";
            if ($payload->sortdir !== null) {
                $orderBy .= ' ' . \strtoupper($payload->sortdir);
            }
        }
        // 5
        return [
            'data' => $entityClass::Find(
                condition: $condition,
                bindings: $bindings,
                orderBy: $orderBy,
                limit: $payload->limit,
                offset: $payload->offset
            ),
            'total' => $entityClass::Count(
                condition: $condition,
                bindings: $bindings
            )
        ];
    }

    /**
     * @return object{
     *   table: string,
     *   limit: int,
     *   offset: int,
     *   search: ?string,
     *   sortkey: ?string,
     *   sortdir: ?string
     * }
     * @throws \RuntimeException
     */
    protected function validatePayload(): \stdClass
    {
        $validator = new Validator([
            'table' => ['required', 'string'],
            'page' => ['integer', 'min:1'],
            'pagesize' => ['integer', 'min:1', 'max:100'],
            'search' => ['string'],
            'sortkey' => ['string'],
            'sortdir' => ['string', function($value) {
                return \in_array($value, ['asc', 'desc'], true);
            }]
        ]);
        $da = $validator->Validate($this->request->QueryParams());
        $page = (int)$da->GetFieldOrDefault('page', 1);
        $pageSize = (int)$da->GetFieldOrDefault('pagesize', 10);
        return (object)[
            'table'   => $da->GetField('table'),
            'limit'   => $pageSize,
            'offset'  => ($page - 1) * $pageSize,
            'search'  => $da->GetFieldOrDefault('search', null),
            'sortkey' => $da->GetFieldOrDefault('sortkey', null),
            'sortdir' => $da->GetFieldOrDefault('sortdir', null)
        ];
    }
}
