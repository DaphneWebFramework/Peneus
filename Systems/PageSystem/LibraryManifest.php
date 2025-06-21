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
use \Harmonia\Core\CSequentialArray;
use \Peneus\Resource;

/**
 * Loads and provides access to CSS and JavaScript asset references defined in
 * the `frontend/manifest.json` file.
 *
 * **Example JSON structure:**
 * ```json
 * {
 *   "jquery": {
 *     "css": "jquery-ui-1.12.1.custom/jquery-ui",
 *     "js": [
 *       "jquery-3.5.1/jquery",
 *       "jquery-ui-1.12.1.custom/jquery-ui"
 *     ],
 *     "default": true
 *   },
 *   "selectize": {
 *     "css": "selectize-0.13.6/css/selectize.bootstrap4.css",
 *     "js": "selectize-0.13.6/js/standalone/selectize"
 *   },
 *   "audiojs": {
 *     "css": "audiojs-1.0.1/audio",
 *     "js": "audiojs-1.0.1/audio"
 *   }
 * }
 * ```
 *
 * CSS and JS paths listed in the manifest may omit file extensions. In debug
 * mode (when the `IsDebug` configuration option is enabled), the framework will
 * append `.css` or `.js` automatically if no extension is present. If a file
 * path already includes a full extension (such as `.min.js` or `.css`), the
 * system uses it as-is.
 *
 * In production mode (when `IsDebug` is disabled), if a path has no extension,
 * the framework attempts to resolve it to a `.min.js` or `.min.css` version.
 * If the path already ends with a full extension, it is used as-is.
 */
class LibraryManifest
{
    /**
     * Stores metadata for libraries.
     *
     * The keys are the library names and the values are the `LibraryItem`
     * instances.
     *
     * @var CArray
     */
    private readonly CArray $items;

    #region public -------------------------------------------------------------

    /**
     * Constructs a new instance by loading the JSON file.
     *
     * @throws \RuntimeException
     *   If the file cannot be opened, read, decoded, or validated.
     */
    public function __construct()
    {
        $this->items = $this->loadFile();
    }

    /**
     * Returns all libraries defined in the manifest.
     *
     * @return CArray
     *   An array of library names mapped to `LibraryItem` instances. The items
     *   are ordered according to their declaration in the manifest file.
     */
    public function Items(): CArray
    {
        return $this->items;
    }

    /**
     * Returns the names of libraries marked as default in the manifest.
     *
     * @return CSequentialArray
     *   A sequential array of library names.
     */
    public function Defaults(): CSequentialArray
    {
        $defaults = new CSequentialArray();
        foreach ($this->items as $name => $item) {
            if ($item->IsDefault()) {
                $defaults->PushBack($name);
            }
        }
        return $defaults;
    }

    #endregion public

    #region protected ----------------------------------------------------------

    /**
     * Loads and parses the frontend manifest file, extracting all library
     * entries.
     *
     * @return CArray
     *   A `CArray` mapping library names to `LibraryItem` instances, containing
     *   normalized paths or URLs for CSS and JavaScript.
     * @throws \RuntimeException
     *   If the manifest file cannot be opened, read, decoded, or validated.
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
        if (!\is_array($decoded)) {
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
            $css = $this->parseField($data, 'css');
            $js = $this->parseField($data, 'js');
            $isDefault = $this->parseBooleanField($data, 'default');
            $items->Set($name, new LibraryItem($css, $js, $isDefault));
        }
        return $items;
    }

    /**
     * Parses the value of the CSS or JavaScript entry from the manifest.
     *
     * @param array $data
     *   The associative array from the decoded manifest.
     * @param string $key
     *   The manifest key to parse (`css` or `js`).
     * @return string|array<int, string>|null
     *   A string or list of strings, where each item is a path or a URL.
     *   Returns `null` if the key is missing.
     * @throws \RuntimeException
     *   If the entry exists but is not a string or an array of strings.
     */
    protected function parseField(array $data, string $key): string|array|null
    {
        if (!\array_key_exists($key, $data)) {
            return null;
        }
        return $this->parseValue($data[$key]);
    }

    /**
     * Validates the value of a manifest entry and ensures it is either a string
     * or an array of strings.
     *
     * @param mixed $value
     *   The raw value from the manifest.
     * @return string|array<int, string>
     *   A string or list of strings, where each item is expected to be a path
     *   or a URL.
     * @throws \RuntimeException
     *   If the value is not a string or an array of strings.
     */
    protected function parseValue(mixed $value): string|array
    {
        if (\is_string($value)) {
            return $value;
        }
        if (\is_array($value)) {
            foreach ($value as $element) {
                if (!\is_string($element)) {
                    throw new \RuntimeException(
                        'Manifest entry must be a string.');
                }
            }
            return $value;
        }
        throw new \RuntimeException(
            'Manifest entry must be a string or an array of strings.');
    }

    /**
     * Parses a boolean field from a manifest entry.
     *
     * @param array $data
     *   The associative array from the decoded manifest.
     * @param string $key
     *   The manifest key to parse (e.g., `default`).
     * @return bool
     *   Returns `true` if the value exists and is truthy; `false` otherwise.
     */
    protected function parseBooleanField(array $data, string $key): bool
    {
        return \filter_var($data[$key] ?? false, \FILTER_VALIDATE_BOOL);
    }

    /** @codeCoverageIgnore */
    protected function openFile(CPath $filePath): ?CFile
    {
        return CFile::Open($filePath, CFile::MODE_READ);
    }

    #endregion protected
}
