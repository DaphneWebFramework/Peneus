<?php declare(strict_types=1);
/**
 * ListEntityMappingsAction.php
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

use \Harmonia\Core\CFileSystem;
use \Harmonia\Core\CPath;
use \Harmonia\Resource;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\DatabaseSystem\Queries\IdentifierEscaper;
use \Harmonia\Systems\DatabaseSystem\Queries\RawQuery;
use \Peneus\Model\Entity;

/**
 * Scans backend modules and lists mappings between entities and database tables.
 */
class ListEntityMappingsAction extends Action
{
    use IdentifierEscaper;

    private readonly CPath $backendPath;

    /**
     * Constructs a new instance by initializing the backend path.
     */
    public function __construct()
    {
        parent::__construct();
        $this->backendPath = Resource::Instance()->AppSubdirectoryPath('backend');
    }

    /**
     * Lists metadata about all entity classes and their table definitions.
     *
     * @return array<string, array<int, object>>
     *   An associative array with key 'data' mapping to a list of objects. Each
     *   object contains the entity class name, table name, table type ('table'
     *   or 'view'), whether the table exists, and whether it is in sync with
     *   the entity definition.
     * @throws \RuntimeException
     *   If the table column metadata could not be retrieved from the database.
     */
    protected function onExecute(): mixed
    {
        $data = [];
        foreach ($this->findModules() as $modulePath) {
            foreach ($this->findEntities($modulePath) as $entityPath) {
                $entityClass = $this->entityClassFrom($entityPath);
                if (!$this->isValidEntity($entityClass)) {
                    continue;
                }
                $tableName = $entityClass::TableName();
                $tableExists = $entityClass::TableExists();
                $isView = $entityClass::IsView();
                if ($tableExists && !$isView) {
                    $entityMetadata = $entityClass::Metadata();
                    $tableMetadata = $this->tableMetadata($tableName);
                    $isSync = $entityMetadata === $tableMetadata;
                } else {
                    $isSync = null;
                }
                $data[] = [
                    'entityClass' => $entityClass,
                    'tableName' => $tableName,
                    'tableType' => $isView ? 'view' : 'table',
                    'tableExists' => $tableExists,
                    'isSync' => $isSync
                ];
            }
        }
        \usort(
            $data,
            fn($a, $b) => \strcmp($a['entityClass'], $b['entityClass'])
        );
        return [
            'data' => $data
        ];
    }

    /**
     * @return CPath[]
     */
    protected function findModules(): array
    {
        $moduleNames = $this->backendPath->Call('\scandir');
        if ($moduleNames === false) {
            return [];
        }
        $result = [];
        foreach ($moduleNames as $moduleName) {
            if (\in_array($moduleName, ['.', '..'])) {
                continue;
            }
            $modulePath = $this->backendPath->Extend($moduleName);
            if (!$modulePath->Call('\is_dir')) {
                continue;
            }
            $result[] = $modulePath;
        }
        return $result;
    }

    /**
     * @param CPath $modulePath
     * @param bool $recursive
     * @return CPath[]
     */
    protected function findEntities(CPath $modulePath, bool $recursive = true): array
    {
        $modelPath = $modulePath->Extend('Model');
        if (!$modelPath->Call('\is_dir')) {
            return [];
        }
        $result = [];
        $entityPaths = CFileSystem::Instance()->FindFiles(
            $modelPath,
            '*.php',
            $recursive
        );
        foreach ($entityPaths as $entityPath) {
            $result[] = new CPath($entityPath);
        }
        return $result;
    }

    /**
     * @param CPath $entityPath
     * @return class-string
     * @throws \InvalidArgumentException
     */
    protected function entityClassFrom(CPath $entityPath): string
    {
        if (!$entityPath->StartsWith($this->backendPath)) {
            throw new \InvalidArgumentException(
                'Entity path must be within the backend directory.');
        }
        $relativePath = $entityPath->Middle($this->backendPath->Length());
        $pathInfo = $relativePath->Call('\pathinfo');
        $namespace = \str_replace('/', '\\', $pathInfo['dirname']);
        return "{$namespace}\\{$pathInfo['filename']}";
    }

    /**
     * @param class-string $entityClass
     * @return bool
     */
    protected function isValidEntity(string $entityClass): bool
    {
        if (!\is_subclass_of($entityClass, Entity::class)) {
            return false;
        }
        $rc = new \ReflectionClass($entityClass);
        if ($rc->isAbstract()) {
            return false;
        }
        return true;
    }

    /**
     * @param string $tableName
     * @return array<int, array<string, mixed>>
     * @throws \RuntimeException
     */
    protected function tableMetadata(string $tableName): array
    {
        // Example: SHOW COLUMNS FROM `account`
        //
        // Field         | Type     | Null | Key | Default | Extra
        // --------------+----------+------+-----+---------+---------------
        // id            | int(11)  | NO   | PRI | NULL    | auto_increment
        // email         | text     | NO   |     | NULL    |
        // passwordHash  | text     | NO   |     | NULL    |
        // displayName   | text     | NO   |     | NULL    |
        // timeActivated | datetime | NO   |     | NULL    |
        // timeLastLogin | datetime | YES  |     | NULL    |

        $query = (new RawQuery)
            ->Sql("SHOW COLUMNS FROM `{$this->escapeIdentifier($tableName)}`");
        $resultSet = Database::Instance()->Execute($query);
        if ($resultSet === null) {
            throw new \RuntimeException(
                "Failed to retrieve columns for: $tableName");
        }
        $result = [];
        while ($row = $resultSet->Row()) {
            $result[] = [
                'name' => $row['Field'],
                'type' => \strtoupper(\preg_replace('/\(.+\)/', '', $row['Type'])),
                'nullable' => $row['Null'] === 'YES'
            ];
        }
        return $result;
    }
}
