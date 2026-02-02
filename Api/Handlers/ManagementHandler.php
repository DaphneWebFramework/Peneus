<?php declare(strict_types=1);
/**
 * ManagementHandler.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Handlers;

use \Peneus\Api\Actions\Action;
use \Peneus\Api\Actions\Management\CreateRecordAction;
use \Peneus\Api\Actions\Management\CreateTableAction;
use \Peneus\Api\Actions\Management\DeleteRecordAction;
use \Peneus\Api\Actions\Management\DropTableAction;
use \Peneus\Api\Actions\Management\ListEntityMappingsAction;
use \Peneus\Api\Actions\Management\ListRecordsAction;
use \Peneus\Api\Actions\Management\UpdateRecordAction;
use \Peneus\Api\Guards\SessionGuard;
use \Peneus\Model\Role;

/**
 * Handles management-related API actions.
 */
class ManagementHandler extends Handler
{
    protected function createAction(string $actionName): ?Action
    {
        return match ($actionName) {
            'list-entity-mappings' => (new ListEntityMappingsAction)
                ->AddGuard(new SessionGuard(Role::Admin)),
            'create-table' => (new CreateTableAction)
                ->AddGuard(new SessionGuard(Role::Admin)),
            'drop-table' => (new DropTableAction)
                ->AddGuard(new SessionGuard(Role::Admin)),
            'list-records' => (new ListRecordsAction)
                ->AddGuard(new SessionGuard(Role::Admin)),
            'create-record' => (new CreateRecordAction)
                ->AddGuard(new SessionGuard(Role::Admin)),
            'update-record' => (new UpdateRecordAction)
                ->AddGuard(new SessionGuard(Role::Admin)),
            'delete-record' => (new DeleteRecordAction)
                ->AddGuard(new SessionGuard(Role::Admin)),
            default => null
        };
    }
}
