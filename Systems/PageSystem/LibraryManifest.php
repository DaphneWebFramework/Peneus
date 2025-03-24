<?php declare(strict_types=1);
/**
 * LibraryManifest.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Systems\PageSystem;

use \Harmonia\Core\CArray;
use \Harmonia\Core\CFile;
use \Harmonia\Core\CPath;
use \Peneus\Resource;

/**
 * Loads and provides access to metadata for all frontend libraries defined in
 * `manifest.json`.
 *
 * **Example JSON structure:**
 * ```json
 * {
 *   "jquery": {
 *     "default": true,
 *     "css": "jquery-ui-1.12.1.custom/jquery-ui",
 *     "js": [
 *       "jquery-3.5.1/jquery",
 *       "jquery-ui-1.12.1.custom/jquery-ui"
 *     ]
 *   },
 *   "selectize": {
 *     "css": "selectize-0.13.6/css/selectize.bootstrap4.css",
 *     "js": "selectize-0.13.6/js/standalone/selectize"
 *   },
 *   "audiojs": {
 *     "css": "audiojs-1.0.1/audio",
 *     "js": "audiojs-1.0.1/audio",
 *     "*": "audiojs-1.0.1/player-graphics.gif"
 *   }
 * }
 * ```
 *
 * CSS and JS paths listed in the manifest may omit file extensions. If so, the
 * framework will append `.css` / `.js` or their minified equivalents (e.g.,
 * `.min.js`) based on the `IsDebug` configuration option.
 *
 * For example, the entry `"js": "lib/foo"` becomes:
 * - `lib/foo.js` in development
 * - `lib/foo.min.js` in production
 *
 * If a file path already includes a full extension (e.g., `.min.js` or `.css`),
 * the system uses it as-is without modification.
 */
class LibraryManifest
{
    /**
     * Stores metadata for frontend libraries. The keys are the library names
     * and the values are the `LibraryItem` instances.
     *
     * @var CArray
     */
    private readonly CArray $items;

    #region public -------------------------------------------------------------

    /**
     * Constructs a new instance by loading the JSON file.
     *
     * @throws \RuntimeException
     *   If the file cannot be read or contains invalid structure.
     */
    public function __construct()
    {
        $this->items = $this->loadFile();
    }

    /**
     * Retrieves a library item by name.
     *
     * @param string $name
     *   The name of the library.
     * @return ?LibraryItem
     *   The matching `LibraryItem`, or `null` if not found.
     */
    public function Get(string $name): ?LibraryItem
    {
        return $this->items->Get($name);
    }

    /**
     * Retrieves all library items marked as default.
     *
     * @return CArray<string, LibraryItem>
     *   A new `CArray` containing `LibraryItem` instances which are marked as
     *   default in the manifest.
     */
    public function Defaults(): CArray
    {
        return $this->items->Apply(
            '\array_filter',
            function (LibraryItem $item): bool {
                return $item->IsDefault();
            }
        );
    }

    #endregion public

    #region protected ----------------------------------------------------------

    /**
     * Loads and parses the file, returning validated library definitions.
     *
     * This method handles file I/O, JSON decoding, structural validation, and
     * conversion into a `CArray` of `LibraryItem` instances.
     *
     * @return CArray
     *   A `CArray` mapping library names to `LibraryItem` instances.
     * @throws \RuntimeException
     *   If the file cannot be opened, read, decoded, or validated.
     */
    protected function loadFile(): CArray
    {
        $filePath = Resource::Instance()->FrontendManifestFilePath();
        $file = $this->openFile($filePath);
        if ($file === null) {
            throw new \RuntimeException('Manifest file could not be opened.');
        }
        $contents = $file->Read();
        $file->Close();
        if ($contents === null) {
            throw new \RuntimeException('Manifest file could not be read.');
        }
        $decoded = \json_decode($contents, true);
        if ($decoded === null) {
            throw new \RuntimeException('Manifest file contains invalid JSON.');
        }
        $items = new CArray();
        foreach ($decoded as $name => $data) {
            if (!\is_string($name)) {
                throw new \RuntimeException('Library name must be a string.');
            }
            if ($name === '') {
                throw new \RuntimeException('Library name cannot be empty.');
            }
            if (!\is_array($data)) {
                throw new \RuntimeException('Library data must be an object.');
            }
            $css = $data['css'] ?? null;
            $js = $data['js'] ?? null;
            $extras = $data['*'] ?? null;
            $isDefault = \filter_var($data['default'] ?? false, \FILTER_VALIDATE_BOOL);
            $items->Set($name, new LibraryItem($name, $css, $js, $extras, $isDefault));
        }
        return $items;
    }

    /** @codeCoverageIgnore */
    protected function openFile(CPath $filePath): ?CFile
    {
        return CFile::Open($filePath, CFile::MODE_READ);
    }

    #endregion protected
}
