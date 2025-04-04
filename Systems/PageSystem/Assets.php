<?php declare(strict_types=1);
/**
 * Assets.php
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
 * Encapsulates asset file paths such as CSS, JavaScript, and extra resources.
 *
 * This class is used to represent normalized asset definitions for both
 * frontend libraries and individual pages. Paths may be specified as a string,
 * an array of strings, or `null`.
 */
class Assets
{
    private readonly array $css;
    private readonly array $js;
    private readonly array $extras;

    /**
     * Constructs a new instance.
     *
     * @param string|array|null $css
     *   (Optional) One or more relative paths to CSS files, or `null` if none.
     * @param string|array|null $js
     *   (Optional) One or more relative paths to JavaScript files, or `null` if
     *   none.
     * @param string|array|null $extras
     *   (Optional) One or more additional asset paths (e.g., fonts, map files),
     *   or `null` if none.
     */
    public function __construct(
        string|array|null $css = null,
        string|array|null $js = null,
        string|array|null $extras = null
    ) {
        $this->css = $this->normalize($css);
        $this->js = $this->normalize($js);
        $this->extras = $this->normalize($extras);
    }

    /**
     * Returns an array of CSS file paths.
     *
     * @return string[]
     *   The list of CSS file paths (relative or absolute).
     */
    public function Css(): array
    {
        return $this->css;
    }

    /**
     * Returns an array of JavaScript file paths.
     *
     * @return string[]
     *   The list of JavaScript file paths (relative or absolute).
     */
    public function Js(): array
    {
        return $this->js;
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
        return $this->extras;
    }

    #region protected ----------------------------------------------------------

    /**
     * Normalizes the given value into an array of strings.
     *
     * Used internally to support flexible input (string, array, or null) when
     * initializing file path properties.
     *
     * @param string|array|null $value
     *   The raw value from the manifest.
     * @return string[]
     *   A normalized array of strings.
     */
    protected function normalize(string|array|null $value): array
    {
        if (\is_string($value)) {
            return [$value];
        }
        if (\is_array($value)) {
            return $value;
        }
        return [];
    }

    #endregion protected
}
