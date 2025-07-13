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
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Queries\RawQuery;
use \Harmonia\Systems\ValidationSystem\Validator;
use \Peneus\Model\Account;
use \Peneus\Model\AccountRole;
use \Peneus\Model\PasswordReset;
use \Peneus\Model\PendingAccount;

/**
 * Returns a paginated list of records from a specified table.
 */
class ListRecordsAction extends Action
{
    /**
     * Executes the process of listing records from a specified table with
     * support for pagination, filtering, and sorting.
     *
     * Validates and sanitizes incoming query parameters, determines the
     * appropriate model class, constructs the necessary conditions for search
     * and ordering, and returns a paginated result set along with the total
     * count of matched records.
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
        $validator = new Validator([
            'table' => ['required', 'string', function($value) {
                return \in_array($value, [
                    'account',
                    'accountrole',
                    'passwordreset',
                    'pendingaccount',
                ], true);
            }],
            'page' => ['integer', 'min:1'],
            'pagesize' => ['integer', 'min:1', 'max:100'],
            'search' => ['string'],
            'sortkey' => ['string'],
            'sortdir' => ['string', function($value) {
                return \in_array($value, ['asc', 'desc'], true);
            }]
        ]);
        $dataAccessor = $validator->Validate(Request::Instance()->QueryParams());
        $table = $dataAccessor->GetField('table');
        $page = (int)$dataAccessor->GetFieldOrDefault('page', 1);
        $pageSize = (int)$dataAccessor->GetFieldOrDefault('pagesize', 10);
        $offset = ($page - 1) * $pageSize;
        $search = $dataAccessor->GetFieldOrDefault('search', null);
        $sortKey = $dataAccessor->GetFieldOrDefault('sortkey', null);
        $sortDir = $dataAccessor->GetFieldOrDefault('sortdir', null);
        $modelClass = match ($table) {
            'account'        => Account::class,
            'accountrole'    => AccountRole::class,
            'passwordreset'  => PasswordReset::class,
            'pendingaccount' => PendingAccount::class,
        };

        $condition = null;
        $bindings = null;
        if ($search !== null) {
            $search = \strtr($search, [
                '\\' => '\\\\',
                '%'  => '\%',
                '_'  => '\_'
            ]);
            $conditions = [];
            foreach ($this->columnNames($table) as $columnName) {
                $conditions[] = "`$columnName` LIKE :search";
            }
            if (!empty($conditions)) {
                $condition = \implode(' OR ', $conditions);
                $bindings = ['search' => "%{$search}%"];
            }
        }

        $orderBy = null;
        if ($sortKey !== null) {
            if (!\in_array($sortKey, $this->columnNames($table), true)) {
                throw new \InvalidArgumentException(
                    "Table `$table` does not have a column named `$sortKey`.");
            }
            $orderBy = "`$sortKey`";
            if ($sortDir !== null) {
                $orderBy .= ' ' . \strtoupper($sortDir);
            }
        }

        return [
            'data' => $modelClass::Find(
                condition: $condition,
                bindings: $bindings,
                orderBy: $orderBy,
                limit: $pageSize,
                offset: $offset
            ),
            'total' => $modelClass::Count(
                condition: $condition,
                bindings: $bindings
            )
        ];
    }

    protected function columnNames(string $tableName): array
    {
        static $cache = [];
        if (isset($cache[$tableName])) {
            return $cache[$tableName];
        }
        $query = (new RawQuery)
            ->Sql("SHOW COLUMNS FROM `$tableName`");
        $resultSet = Database::Instance()->Execute($query);
        if ($resultSet === null) {
            throw new \RuntimeException(
                "Failed to retrieve columns for table: $tableName");
        }
        $columnNames = [];
        while ($row = $resultSet->Row()) {
            $columnNames[] = $row['Field'];
        }
        return $cache[$tableName] = $columnNames;
    }
}
