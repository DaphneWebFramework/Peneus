<?php declare(strict_types=1);
/**
 * Resource.php
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
 * Provides additional resources specific to the Peneus library.
 */
class Resource extends \Harmonia\Resource
{
    /**
     * Returns the absolute path to the specified template file.
     *
     * @param string $templateName
     *   The name of the template file without the extension.
     * @return CPath
     *   The absolute path to the template file.
     */
    public function TemplateFilePath($templateName): CPath
    {
        return CPath::Join(
            $this->appSubdirectoryPath('templates'),
            "{$templateName}.html"
        );
    }

    /**
     * Returns the absolute path to the specified masterpage file.
     *
     * @param string $masterpageName
     *   The name of the masterpage file without the extension.
     * @return CPath
     *   The absolute path to the masterpage file.
     */
    public function MasterpageFilePath($masterpageName): CPath
    {
        return CPath::Join(
            $this->appSubdirectoryPath('masterpages'),
            "{$masterpageName}.php"
        );
    }

    #region protected ----------------------------------------------------------

    /**
     * Returns the absolute path to the specified subdirectory within the app
     * directory.
     *
     * @param string $subdirectory
     *   The name of the subdirectory.
     * @return CPath
     *   The absolute path to the subdirectory.
     */
    protected function appSubdirectoryPath($subdirectory): CPath
    {
        $cacheKey = __FUNCTION__ . "($subdirectory)";
        if ($this->cache->Has($cacheKey)) {
            return $this->cache->Get($cacheKey);
        }
        $result = CPath::Join($this->AppPath(), $subdirectory);
        $this->cache->Set($cacheKey, $result);
        return $result;
    }

    #endregion protected
}
