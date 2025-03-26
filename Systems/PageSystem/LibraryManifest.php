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
            $css = $this->validateAssetField($data, 'css');
            $js = $this->validateAssetField($data, 'js');
            $extras = $this->validateAssetField($data, '*');
            $isDefault = $this->validateBooleanField($data, 'default');
            $items->Set($name, new LibraryItem($name, $css, $js, $extras, $isDefault));
        }
        return $items;
    }

    /**
     * Validates and retrieves a specific asset field from a manifest entry.
     *
     * @param array $data
     *   The associative array representing a single library entry.
     * @param string $key
     *   The field name to validate and retrieve (`css`, `js`, or `*`).
     * @return string|array<int, string>|null
     *   The validated asset value if the key exists, or `null` if it is not set.
     * @throws \RuntimeException
     *   If the field exists but is not a string or an array of strings.
     */
    protected function validateAssetField(array $data, string $key): string|array|null
    {
        if (!\array_key_exists($key, $data)) {
            return null;
        }
        return $this->validateAssetValue($data[$key]);
    }

    /**
     * Validates and retrieves a specific boolean field from a manifest entry.
     *
     * @param array $data
     *   The associative array representing a single library entry.
     * @param string $key
     *   The field name to validate and retrieve, i.e., `default`.
     * @return bool
     *   The validated boolean value or `false` if the key is not set or the
     *   value is not a boolean.
     */
    protected function validateBooleanField(array $data, string $key): bool
    {
        return \filter_var($data[$key] ?? false, \FILTER_VALIDATE_BOOL);
    }

    /**
     * Validates that a value is either a string or an array of strings.
     *
     * @param mixed $value
     *   The value to validate.
     * @return string|array<int, string>
     *   The original value if valid.
     * @throws \RuntimeException
     *   If the value is not a string or an array of strings.
     */
    protected function validateAssetValue(mixed $value): string|array
    {
        if (\is_string($value)) {
            return $value;
        }
        if (\is_array($value)) {
            foreach ($value as $element) {
                if (!\is_string($element)) {
                    throw new \RuntimeException(
                        'Library asset value must be a string.');
                }
            }
            return $value;
        }
        throw new \RuntimeException(
            'Library asset value must be a string or an array of strings.');
    }

    /** @codeCoverageIgnore */
    protected function openFile(CPath $filePath): ?CFile
    {
        return CFile::Open($filePath, CFile::MODE_READ);
    }

    #endregion protected
}
