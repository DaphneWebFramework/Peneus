<?php declare(strict_types=1);
/**
 * PageManifest.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Systems\PageSystem;

use \Harmonia\Core\CFile;
use \Harmonia\Core\CPath;
use \Peneus\Resource;

/**
 * Loads and provides access to metadata for assets defined in a page-level
 * `manifest.json`.
 *
 * **Example JSON structure:**
 * ```json
 * {
 *   "css": ["index", "theme"],
 *   "js": ["Model", "View", "Controller"],
 *   "*": "localization.json"
 * }
 * ```
 *
 * CSS and JS paths listed in the manifest may omit file extensions. In debug
 * mode (when the `IsDebug` configuration option is enabled), the framework will
 * append `.css` or `.js` automatically if no extension is present. For example,
 * the entry `"js": "View"` will resolve to `View.js`. If a file path already
 * includes a full extension (such as `.min.js` or `.css`), the system uses it
 * as-is.
 *
 * In production mode (when the `IsDebug` configuration option is disabled),
 * unlike library assets, the page system does not resolve or append `.min`
 * suffixes for specific assets. Instead, it assumes the deployer tool has
 * already minified and combined all CSS and JavaScript files into two optimized
 * bundles named `page.min.css` and `page.min.js`, which are then included in
 * place of individual files.
 */
class PageManifest
{
    private readonly Assets $assets;

    #region public -------------------------------------------------------------

    /**
     * Constructs a new instance by loading the manifest file from the page
     * directory.
     *
     * @param string $pageId
     *   The unique identifier of the page.
     */
    public function __construct(string $pageId)
    {
        $this->assets = $this->loadFile($pageId);
    }

    /**
     * Returns an array of CSS file paths.
     *
     * @return string[]
     *   The list of CSS file paths (relative or absolute).
     */
    public function Css(): array
    {
        return $this->assets->Css();
    }

    /**
     * Returns an array of JavaScript file paths.
     *
     * @return string[]
     *   The list of JavaScript file paths (relative or absolute).
     */
    public function Js(): array
    {
        return $this->assets->Js();
    }

    /**
     * Returns an array of extra resources.
     *
     * These may include fonts, source maps, or other supplementary assets
     * (e.g., `.woff2`, `.min.js.map`, `.min.css.map`, `.json`, `.png`) that
     * are required at runtime by the production version of the application
     * and must be copied alongside the main assets during deployment.
     *
     * File paths may contain wildcard characters (e.g., `*`, `?`), which are
     * matched against the filesystem during deployment.
     *
     * @return string[]
     *   The list of extra asset paths (relative or absolute). Paths may include
     *   wildcard patterns (e.g., `*`, `?`).
     */
    public function Extras(): array
    {
        return $this->assets->Extras();
    }

    #endregion public

    #region protected ----------------------------------------------------------

    /**
     * Loads and parses the file, returning validated asset definitions.
     *
     * This method handles file I/O, JSON decoding, structural validation, and
     * conversion into an `Assets` instance.
     *
     * @param string $pageId
     *   The unique identifier of the page.
     * @return Assets
     *   The parsed asset definitions.
     */
    protected function loadFile(string $pageId): Assets
    {
        $filePath = Resource::Instance()->PageFilePath($pageId, 'manifest.json');
        $file = $this->openFile($filePath);
        if ($file === null) {
            return new Assets();
        }
        $contents = $file->Read();
        $file->Close();
        if ($contents === null) {
            return new Assets();
        }
        $decoded = \json_decode($contents, true);
        if (!\is_array($decoded)) {
            return new Assets();
        }
        $css = $this->validateAssetField($decoded, 'css');
        $js = $this->validateAssetField($decoded, 'js');
        $extras = $this->validateAssetField($decoded, '*');
        return new Assets($css, $js, $extras);
    }

    /**
     * Validates and retrieves a specific asset field from the manifest.
     *
     * @param array $data
     *   The associative array from the decoded manifest.
     * @param string $key
     *   The field name to validate and retrieve (`css`, `js`, or `*`).
     * @return string|array<int, string>|null
     *   The validated asset value or `null` if missing.
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
                        'Page asset value must be a string.');
                }
            }
            return $value;
        }
        throw new \RuntimeException(
            'Page asset value must be a string or an array of strings.');
    }

    /** @codeCoverageIgnore */
    protected function openFile(CPath $filePath): ?CFile
    {
        return CFile::Open($filePath, CFile::MODE_READ);
    }

    #endregion protected
}
