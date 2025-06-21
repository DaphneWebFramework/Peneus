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
 * Represents normalized CSS and JavaScript asset references.
 *
 * Used by both page-level and library-level manifest loaders to store and
 * return parsed paths or URLs. Input values may be strings, arrays of strings,
 * or `null`, and are normalized internally to consistent arrays.
 */
class Assets
{
    private readonly array $css;
    private readonly array $js;

    /**
     * Constructs a new instance with CSS and JavaScript inputs.
     *
     * @param string|array|null $css
     *   (Optional) A string, array of strings, or `null`, representing one or
     *   more CSS paths or URLs. Defaults to `null`.
     * @param string|array|null $js
     *   (Optional) A string, array of strings, or `null`, representing one or
     *   more JavaScript paths or URLs. Defaults to `null`.
     */
    public function __construct(
        string|array|null $css = null,
        string|array|null $js = null
    ) {
        $this->css = $this->normalize($css);
        $this->js = $this->normalize($js);
    }

    /**
     * Returns an array of CSS asset references.
     *
     * @return string[]
     *   A list of paths or URLs. Each item is either a relative path (with or
     *   without extension, resolved relative to its context) or a URL (e.g.,
     *   CDN links).
     */
    public function Css(): array
    {
        return $this->css;
    }

    /**
     * Returns an array of JavaScript asset references.
     *
     * @return string[]
     *   A list of paths or URLs. Each item is either a relative path (with or
     *   without extension, resolved relative to its context) or a URL (e.g.,
     *   CDN links).
     */
    public function Js(): array
    {
        return $this->js;
    }

    #region protected ----------------------------------------------------------

    /**
     * Normalizes a manifest value into an array of strings.
     *
     * Used internally to accept flexible input formats (string, array, or null)
     * and convert them to a consistent array representation.
     *
     * @param string|array|null $value
     *   A raw manifest value representing one or more paths or URLs.
     * @return string[]
     *   An array of strings. Returns an empty array if the input is `null`.
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
