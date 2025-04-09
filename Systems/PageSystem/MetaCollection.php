<?php declare(strict_types=1);
/**
 * MetaCollection.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Systems\PageSystem;

use \Harmonia\Config;
use \Harmonia\Core\CArray;

/**
 * Stores and manages page-level meta tags.
 */
class MetaCollection
{
    /**
     * Meta entries organized by type.
     *
     * The outer keys represent the attribute type (e.g., `name`, `property`),
     * and each value is a `CArray` of tag names mapped to their contents.
     *
     * For example:
     * - $items['name']['viewport'] = 'width=device-width...'
     * - $items['property']['og:title'] = 'Home'
     *
     * @var CArray
     */
    private CArray $items;

    /**
     * Constructs a new instance and applies default tags from configuration.
     */
    public function __construct()
    {
        $this->items = new CArray();
        $this->setDefaults();
    }

    /**
     * Determines whether a meta tag with the given name and type exists.
     *
     * @param string $name
     *   The name of the meta tag (e.g., `description`, `og:title`).
     * @param string $type
     *   The meta tag type (e.g., `name`, `property`, `itemprop`).
     * @return bool
     *   Returns `true` if the meta tag exists, `false` otherwise.
     */
    public function Has(string $name, string $type): bool
    {
        if (!$this->items->Has($type)) {
            return false;
        }
        if (!$this->items->Get($type)->Has($name)) {
            return false;
        }
        return true;
    }

    /**
     * Adds or replaces a meta tag.
     *
     * @param string $name
     *   The name of the meta tag (e.g., `description`, `og:title`).
     * @param string $content
     *   The content of the meta tag.
     * @param string $type
     *   (Optional) The attribute type (e.g., `name`, `property`, `itemprop`).
     *   Defaults to `name`.
     */
    public function Set(string $name, string $content, string $type = 'name'): void
    {
        if (!$this->items->Has($type)) {
            $this->items->Set($type, new CArray());
        }
        $group = $this->items->Get($type);
        $group->Set($name, $content);
    }

    /**
     * Removes a specific meta tag.
     *
     * If the tag does not exist, the method does nothing.
     *
     * @param string $name
     *   The name of the tag to remove.
     * @param string $type
     *   The attribute type (e.g., `name`, `property`, `itemprop`).
     */
    public function Remove(string $name, string $type): void
    {
        if ($this->items->Has($type)) {
            $group = $this->items->Get($type);
            $group->Remove($name);
            if ($group->IsEmpty()) {
                $this->items->Remove($type);
            }
        }
    }

    /**
     * Removes all stored meta tags.
     */
    public function RemoveAll(): void
    {
        $this->items->Clear();
    }

    /**
     * Returns all stored meta tags grouped by attribute type.
     *
     * @return CArray
     *   A `CArray` of meta tag groups. Each key is the type (e.g., `name`,
     *   `property`, `itemprop`) and each value is a `CArray` of tag names
     *   mapped to their contents.
     */
    public function Items(): CArray
    {
        return $this->items;
    }

    #region protected ----------------------------------------------------------

    /**
     * Adds default meta tags from configuration.
     *
     * Only adds values that are explicitly set in configuration. In other words,
     * no hardcoded defaults are added.
     */
    protected function setDefaults(): void
    {
        $config = Config::Instance();

        $value = $config->Option('Description');
        if ($value !== null) {
            $this->Set('description', $value, 'name');
            $this->Set('og:description', $value, 'property');
        }

        $value = $config->Option('Viewport');
        if ($value !== null) {
            $this->Set('viewport', $value, 'name');
        }

        $value = $config->Option('Locale');
        if ($value !== null) {
            $this->Set('og:locale', $value, 'property');
        }

        $this->Set('og:type', 'website', 'property');
    }

    #endregion protected
}
