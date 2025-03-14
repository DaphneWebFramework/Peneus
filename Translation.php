<?php declare(strict_types=1);
/**
 * Translation.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus;

use \Harmonia\Core\CPath;

/**
 * Manages translations for the Peneus library.
 */
class Translation extends \Harmonia\Translation
{
    /**
     * Specifies the JSON file containing translations.
     *
     * @return array<CPath>
     *   A single-element array with the path to the JSON file containing
     *   translations.
     */
    protected function filePaths(): array
    {
        return [CPath::Join(__DIR__, 'translations.json')];
    }
}
