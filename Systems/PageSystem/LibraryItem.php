<?php declare(strict_types=1);
/**
 * LibraryItem.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Systems\PageSystem;

/**
 * Represents the metadata for a frontend library declared in `manifest.json`.
 *
 * This class holds normalized data for a single frontend library (e.g.,
 * Bootstrap, jQuery), including its associated CSS and JavaScript file paths,
 * any additional supporting assets, and default inclusion status.
 */
class LibraryItem
{
    private readonly Assets $assets;
    private readonly bool $isDefault;

    /**
     * Constructs a new instance.
     *
     * @param string|array|null $css
     *   One or more relative paths to CSS files, or `null` if none.
     * @param string|array|null $js
     *   One or more relative paths to JavaScript files, or `null` if none.
     * @param string|array|null $extras
     *   One or more additional asset paths (e.g., fonts, map files), or `null`
     *   if none.
     * @param bool $isDefault
     *   Indicates whether this library is marked to be included by default.
     */
    public function __construct(
        string|array|null $css,
        string|array|null $js,
        string|array|null $extras,
        bool $isDefault
    ) {
        $this->assets = new Assets($css, $js, $extras);
        $this->isDefault = $isDefault;
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

    /**
     * Indicates whether the library is included by default.
     *
     * @return bool
     *   Returns `true` if the library is marked as default in the manifest,
     *   `false` otherwise.
     */
    public function IsDefault(): bool
    {
        return $this->isDefault;
    }
}
