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
 * Represents a single frontend library's CSS and JavaScript asset references.
 *
 * Used by the library manifest loader to return parsed paths or URLs for each
 * library (e.g., Bootstrap, jQuery) defined in `frontend/manifest.json`.
 */
class LibraryItem
{
    private readonly Assets $assets;
    private readonly bool $isDefault;

    /**
     * Constructs a new instance with CSS and JavaScript inputs.
     *
     * @param string|array|null $css
     *   A string, array of strings, or `null`, representing one or more CSS
     *   paths or URLs.
     * @param string|array|null $js
     *   A string, array of strings, or `null`, representing one or more
     *   JavaScript paths or URLs.
     * @param bool $isDefault
     *   Indicates whether the library is marked as default in the manifest.
     */
    public function __construct(
        string|array|null $css,
        string|array|null $js,
        bool $isDefault
    ) {
        $this->assets = new Assets($css, $js);
        $this->isDefault = $isDefault;
    }

    /**
     * Returns an array of CSS asset references.
     *
     * @return string[]
     *   A list of paths or URLs. Each item is either a relative path (with or
     *   without extension, resolved against the frontend directory) or a URL
     *   (e.g., CDN links).
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
     *   without extension, resolved against the frontend directory) or a URL
     *   (e.g., CDN links).
     */
    public function Js(): array
    {
        return $this->assets->Js();
    }

    /**
     * Indicates whether the library is marked as default in the manifest.
     *
     * @return bool
     *   Returns `true` if the library should be included by default, `false`
     *   otherwise.
     */
    public function IsDefault(): bool
    {
        return $this->isDefault;
    }
}
