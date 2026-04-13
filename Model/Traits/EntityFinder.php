<?php declare(strict_types=1);
/**
 * EntityFinder.php
 *
 * (C) 2026 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Model\Traits;

use \Peneus\Model\Entity;

trait EntityFinder
{
    /**
     * @param class-string<Entity> $entityClass
     * @param int $id
     * @return Entity|null
     */
    protected function tryFindEntity(string $entityClass, int $id): ?Entity
    {
        return $entityClass::FindById($id);
    }
}
