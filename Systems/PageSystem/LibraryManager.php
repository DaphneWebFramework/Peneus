<?php declare(strict_types=1);
/**
 * LibraryManager.php
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
use \Harmonia\Core\CSequentialArray;
use \Peneus\Systems\PageSystem\LibraryItem;
use \Peneus\Systems\PageSystem\LibraryManifest;

/**
 * Manages inclusion and exclusion of frontend libraries.
 *
 * This class maintains a set of library names to be included in a web page and
 * ensures they are returned in the order defined by the manifest. Libraries may
 * be added or removed dynamically. Libraries marked as default in the manifest
 * are included automatically upon instantiation.
 */
class LibraryManager
{
    /**
     * The manifest that defines all known frontend libraries and their order.
     *
     * @var LibraryManifest
     */
    private readonly LibraryManifest $manifest;

    /**
     * A set of library names that are currently included in the page.
     *
     * The keys are the library names, and the values are always `true` and are
     * unused.
     *
     * @var CArray
     */
    private CArray $includedNames;

    #region public -------------------------------------------------------------

    /**
     * Constructs a new instance by loading the manifest and including all
     * libraries marked as default in the manifest.
     *
     * @param ?LibraryManifest $manifest
     *   (Optional) The manifest to use. If not specified, a default instance is
     *   created.
     */
    public function __construct(?LibraryManifest $manifest = null)
    {
        $this->manifest = $manifest ?? new LibraryManifest();
        $this->includedNames = new CArray();
        // Automatically include libraries that are marked as default.
        foreach ($this->manifest->Defaults() as $name) {
            $this->includedNames->Set($name, true);
        }
    }

    /**
     * Adds a library to be included in the page.
     *
     * @param string $name
     *   The name of the library to add.
     * @throws \InvalidArgumentException
     *   If the library name does not exist in the manifest.
     */
    public function Add(string $name): void
    {
        if (!$this->manifest->Items()->Has($name)) {
            throw new \InvalidArgumentException("Unknown library: {$name}");
        }
        $this->includedNames->Set($name, true);
    }

    /**
     * Removes a library from the set of included libraries.
     *
     * This method can be used to exclude libraries that were automatically
     * included by default, or to undo a manual addition. If the library is not
     * currently included, the method does nothing.
     *
     * @param string $name
     *   The name of the library to remove.
     */
    public function Remove(string $name): void
    {
        $this->includedNames->Remove($name);
    }

    /**
     * Clears all libraries from the set of included libraries.
     *
     * This method can be used to exclude all libraries that were automatically
     * included by default, as well as any that were added manually.
     */
    public function Clear(): void
    {
        $this->includedNames->Clear();
    }

    /**
     * Returns the list of libraries to be included in a web page.
     *
     * This list consists of all libraries that were marked as default in the
     * manifest or explicitly added using `Add`, and not removed using `Remove`.
     * The libraries are returned in the order they appear in the manifest.
     *
     * @return CSequentialArray
     *   A list of `LibraryItem` instances to be included in the page, in the same
     *   order as declared in the manifest.
     */
    public function Included(): CSequentialArray
    {
        $result = new CSequentialArray();
        foreach ($this->manifest->Items() as $name => $item) {
            if ($this->includedNames->Has($name)) {
                $result->PushBack($item);
            }
        }
        return $result;
    }

    #endregion public
}
