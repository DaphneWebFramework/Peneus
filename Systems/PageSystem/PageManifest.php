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
 * Loads and provides access to CSS and JavaScript asset references defined in a
 * page-level `manifest.json`.
 *
 * **Example JSON structure:**
 * ```json
 * {
 *   "css": ["index", "theme"],
 *   "js": ["Model", "View", "Controller"]
 * }
 * ```
 *
 * CSS and JS paths listed in the manifest may omit file extensions. In debug
 * mode (when the `IsDebug` configuration option is enabled), the framework will
 * append `.css` or `.js` automatically if no extension is present. If a file
 * path already includes a full extension (such as `.min.js` or `.css`), the
 * system uses it as-is.
 *
 * In production mode (when `IsDebug` is disabled), it is assumed that the
 * deployer has already combined and minified all assets into `page.min.css`
 * and `page.min.js`, which are included instead of individual files.
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
     * Returns an array of CSS asset references.
     *
     * @return string[]
     *   A list of paths or URLs. Each item is either a relative path (with or
     *   without extension, resolved against the page directory) or a URL (e.g.,
     *   CDN links).
     */
    public function Css(): array
    {
        return $this->assets->Css();
    }

    /**
     * Returns an array of JavaScript asset references.
     *
     * @return string[]
     *   A list of paths or URLs. Each item is either a relative path (with or
     *   without extension, resolved against the page directory) or a URL (e.g.,
     *   CDN links).
     */
    public function Js(): array
    {
        return $this->assets->Js();
    }

    #endregion public

    #region protected ----------------------------------------------------------

    /**
     * Loads and parses the manifest file for the given page, extracting asset
     * entries.
     *
     * @param string $pageId
     *   The unique identifier of the page.
     * @return Assets
     *   An `Assets` instance containing normalized paths or URLs for CSS and
     *   JavaScript. An empty `Assets` instance is returned if the file is
     *   missing, invalid, or if the manifest does not contain any entries.
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
        $css = $this->parseField($decoded, 'css');
        $js = $this->parseField($decoded, 'js');
        return new Assets($css, $js);
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

    /** @codeCoverageIgnore */
    protected function openFile(CPath $filePath): ?CFile
    {
        return CFile::Open($filePath, CFile::MODE_READ);
    }

    #endregion protected
}
