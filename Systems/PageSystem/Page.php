<?php declare(strict_types=1);
/**
 * Page.php
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
use \Harmonia\Core\CSequentialArray;

/**
 * Represents a web page and manages its basic properties and rendering flow.
 *
 * #### Example
 * ```php
 * <?php
 * require '../../autoload.php';
 *
 * use \Peneus\Systems\PageSystem\Page;
 *
 * $page = (new Page)
 *     ->SetTitle('Home')
 *     ->SetMasterPage('basic')
 *     ->AddLibrary('dataTables');
 * ?>
 *
 * <?php $page->Begin()?>
 *     <h1>Welcome to my website</h1>
 *     <p>This is the home page.</p>
 * <?php $page->End()?>
 * ```
 */
class Page
{
    private readonly Renderer $renderer;
    private readonly LibraryManager $libraryManager;

    private string $title = '';
    private string $titleTemplate = '{{Title}} | {{AppName}}';
    private string $masterpage = '';
    private string $contents = '';

    #region public -------------------------------------------------------------

    /**
     * Constructs a new instance.
     *
     * @param ?Renderer $renderer
     *   (Optional) The renderer to use. If not specified, a default instance is
     *   created.
     * @param ?LibraryManager $libraryManager
     *   (Optional) The library manager to use. If not specified, a default
     *   instance is created.
     */
    public function __construct(
        ?Renderer $renderer = null,
        ?LibraryManager $libraryManager = null
    ) {
        $this->renderer = $renderer ?? new Renderer();
        $this->libraryManager = $libraryManager ?? new LibraryManager();
    }

    #region setters ------------------------------------------------------------

    /**
     * Sets the page title.
     *
     * @param string $title
     *   The title of the page. It will be substituted into the title template
     *   when the final page title is generated.
     * @return self
     *   The current instance.
     *
     * @see SetTitleTemplate
     * @see Title
     */
    public function SetTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Sets the template used to generate the final page title.
     *
     * If not specified, the default template `{{Title}} | {{AppName}}` is used.
     *
     * @param string $titleTemplate
     *   A template string that may include placeholders such as `{{Title}}` and
     *   `{{AppName}}`.
     * @return self
     *   The current instance.
     *
     * @see SetTitle
     * @see Title
     */
    public function SetTitleTemplate(string $titleTemplate): self
    {
        $this->titleTemplate = $titleTemplate;
        return $this;
    }

    /**
     * Sets the masterpage used to layout the page content.
     *
     * @param string $masterpage
     *   The file name (without extension) of a masterpage under the
     *   `masterpages` directory (e.g. `basic`, `starter`).
     * @return self
     *   The current instance.
     */
    public function SetMasterpage(string $masterpage): self
    {
        $this->masterpage = $masterpage;
        return $this;
    }

    #endregion setters

    #region getters ------------------------------------------------------------

    /**
     * Returns the generated page title.
     *
     * The returned string is produced by substituting the title (set via
     * `SetTitle`) and the application name (retrieved from configuration)
     * into the title template (set via `SetTitleTemplate`).
     *
     * If the application name is empty, only the title is returned. If the
     * title is empty, only the application name is returned.
     *
     * @return string
     *   The generated page title.
     *
     * @see SetTitle
     * @see SetTitleTemplate
     */
    public function Title(): string
    {
        $appName = Config::Instance()->OptionOrDefault('AppName', '');
        if ($appName === '') {
            return $this->title;
        }
        if ($this->title === '') {
            return $appName;
        }
        return \strtr($this->titleTemplate, [
            '{{Title}}' => $this->title,
            '{{AppName}}' => $appName,
        ]);
    }

    /**
     * Returns the selected masterpage name.
     *
     * @return string
     *   The masterpage name.
     *
     * @see SetMasterpage
     */
    public function Masterpage(): string
    {
        return $this->masterpage;
    }

    /**
     * Returns the captured page content.
     *
     * @return string
     *   The content between calls to `Begin()` and `End()`.
     */
    public function Contents(): string
    {
        return $this->contents;
    }

    #endregion getters

    #region operations ---------------------------------------------------------

    /**
     * Begins capturing the page content using output buffering.
     */
    public function Begin(): void
    {
        $this->contents = '';
        $this->_ob_start();
    }

    /**
     * Ends content capture and renders the page.
     */
    public function End(): void
    {
        $this->contents = $this->_ob_get_clean();
        $this->renderer->Render($this);
    }

    /**
     * Adds a library to the list of libraries to be included in the page.
     *
     * @param string $libraryName
     *   The name of the library to add.
     * @return self
     *   The current instance.
     * @throws \InvalidArgumentException
     *   If the library name does not exist in the manifest.
     */
    public function AddLibrary(string $libraryName): self
    {
        $this->libraryManager->Add($libraryName);
        return $this;
    }

    /**
     * Removes a library from the set of included libraries.
     *
     * This method can be used to exclude libraries that were automatically
     * included by default, or to undo a manual addition. If the library is
     * not currently included, the method does nothing.
     *
     * @param string $libraryName
     *   The name of the library to remove.
     * @return self
     *   The current instance.
     */
    public function RemoveLibrary(string $libraryName): self
    {
        $this->libraryManager->Remove($libraryName);
        return $this;
    }

    /**
     * Removes all included libraries.
     *
     * This method can be used to exclude all libraries that were automatically
     * included by default, as well as any that were added manually.
     *
     * @return self
     *   The current instance.
     */
    public function RemoveAllLibraries(): self
    {
        $this->libraryManager->RemoveAll();
        return $this;
    }

    /**
     * Returns the list of libraries to be included in the page.
     *
     * This list consists of all libraries that were marked as default in the
     * manifest or explicitly added using `AddLibrary`, and not removed using
     * `RemoveLibrary`. The libraries are returned in the order they appear in
     * the manifest.
     *
     * > This method is intended to support the renderer and is typically not
     * required in page-level code.
     *
     * @return CSequentialArray
     *   A list of `LibraryItem` instances.
     */
    public function IncludedLibraries(): CSequentialArray
    {
        return $this->libraryManager->Included();
    }

    #endregion operations

    #endregion public

    #region protected ----------------------------------------------------------

    /** @codeCoverageIgnore */
    protected function _ob_start(): void
    {
        \ob_start();
    }

    /** @codeCoverageIgnore */
    protected function _ob_get_clean(): string
    {
        $contents = \ob_get_clean();
        return $contents === false ? '' : $contents;
    }

    #endregion protected
}
