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
     * Executes the process of listing records from a specified table with
     * support for pagination, filtering, and sorting.
     *
     * Validates and sanitizes incoming query parameters, determines the
     * corresponding entity class, constructs the necessary conditions for
     * search and ordering, and returns a paginated result set along with the
     * total count of matched records.
     *
     * @return array<string, mixed>
     *   An associative array containing two keys: 'data', which holds the array
     *   of matched records for the current page, and 'total', which indicates
     *   the total number of records matching the search criteria.
     * @throws \InvalidArgumentException
     *   If the table name is not among the allowed values, or if the sort key
     *   does not match any of the table's columns.
     * @throws \RuntimeException
     *   If the column metadata cannot be retrieved for the specified table.
     */
    protected function onExecute(): mixed
    {
        // 1
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
        $dataAccessor = $validator->Validate($this->request->QueryParams());
        $table = $dataAccessor->GetField('table');
        $page = (int)$dataAccessor->GetFieldOrDefault('page', 1);
        $pageSize = (int)$dataAccessor->GetFieldOrDefault('pagesize', 10);
        $offset = ($page - 1) * $pageSize;
        $search = $dataAccessor->GetFieldOrDefault('search', null);
        $sortKey = $dataAccessor->GetFieldOrDefault('sortkey', null);
        $sortDir = $dataAccessor->GetFieldOrDefault('sortdir', null);
        // 2
        $entityClass = $this->resolveEntityClass($table);
        $columns = \array_column($entityClass::Metadata(), 'name');
        // 3
        $condition = null;
        $bindings = null;
        if ($search !== null) {
            $search = \strtr($search, [
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
        if ($sortKey !== null) {
            if (!\in_array($sortKey, $columns, true)) {
                throw new \InvalidArgumentException(
                    "Table '{$entityClass::TableName()}' does not have a "
                  . "column named '$sortKey'.");
            }
            $orderBy = "`$sortKey`";
            if ($sortDir !== null) {
                $orderBy .= ' ' . \strtoupper($sortDir);
            }
        }
        // 5
        return [
            'data' => $entityClass::Find(
                condition: $condition,
                bindings: $bindings,
                orderBy: $orderBy,
                limit: $pageSize,
                offset: $offset
            ),
            'total' => $entityClass::Count(
                condition: $condition,
                bindings: $bindings
            )
        ];
    }
}
