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
use \Harmonia\Core\CArray;
use \Harmonia\Core\CSequentialArray;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Peneus\Api\Guards\FormTokenGuard;
use \Peneus\Model\Account;
use \Peneus\Model\Role;

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
 * $page = (new Page(__DIR__))
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
    private readonly string $id;
    private readonly Renderer $renderer;
    private readonly LibraryManager $libraryManager;
    private readonly PageManifest $pageManifest;
    private readonly MetaCollection $metaCollection;
    private readonly AuthManager $authManager;
    private readonly CArray $properties;

    private string $title = '';
    private string $titleTemplate = '{{Title}} | {{AppName}}';
    private string $masterpage = '';
    private string $content = '';

    #region public -------------------------------------------------------------

    /**
     * Constructs a new instance.
     *
     * @param string $directory
     *   The absolute path to the directory where the page's `index.php` file
     *   is located. Typically, the `__DIR__` constant is used to specify this
     *   path.
     * @param ?Renderer $renderer
     *   (Optional) The renderer to use. If not specified, a default instance is
     *   created.
     * @param ?LibraryManager $libraryManager
     *   (Optional) The library manager to use. If not specified, a default
     *   instance is created.
     * @param ?PageManifest $pageManifest
     *   (Optional) The page manifest to use. If not specified, a default
     *   instance is created.
     * @param ?MetaCollection $metaCollection
     *   (Optional) The meta collection to use. If not specified, a default
     *   instance is created.
     * @param ?AuthManager $authManager
     *   (Optional) The authentication manager to use. If not specified, a
     *   default instance is created.
     */
    public function __construct(
        string $directory,
        ?Renderer $renderer = null,
        ?LibraryManager $libraryManager = null,
        ?PageManifest $pageManifest = null,
        ?MetaCollection $metaCollection = null,
        ?AuthManager $authManager = null
    ) {
        $this->id = \basename($directory);
        $this->renderer = $renderer ?? new Renderer();
        $this->libraryManager = $libraryManager ?? new LibraryManager();
        $this->pageManifest = $pageManifest ?? new PageManifest($this->id);
        $this->metaCollection = $metaCollection ?? new MetaCollection();
        $this->authManager = $authManager ?? new AuthManager();
        $this->properties = new CArray();
    }

    /**
     * Returns the unique identifier of the page.
     *
     * This corresponds to the name of the subdirectory under `pages/` where the
     * page's `index.php`, `manifest.json`, and related files reside.
     *
     * > This method is intended to support the renderer and is typically not
     * required in page-level code.
     *
     * @return string
     *   The page identifier (e.g., `'home'`, `'login'`, `'about'`).
     */
    public function Id(): string
    {
        return $this->id;
    }

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
     * Returns the generated page title.
     *
     * The returned string is produced by substituting the title (set via
     * `SetTitle`) and the application name (retrieved from configuration)
     * into the title template (set via `SetTitleTemplate`).
     *
     * If the application name is empty, only the title is returned. If the
     * title is empty, only the application name is returned.
     *
     * > This method is intended to support the renderer and is typically not
     * required in page-level code.
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

    /**
     * Returns the selected masterpage name.
     *
     * > This method is intended to support the renderer and is typically not
     * required in page-level code.
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
     * > This method is intended to support the renderer and is typically not
     * required in page-level code.
     *
     * @return string
     *   The content between calls to `Begin()` and `End()`.
     */
    public function Content(): string
    {
        return $this->content;
    }

    /**
     * Begins capturing the page content using output buffering.
     */
    public function Begin(): void
    {
        $this->content = '';
        $this->_ob_start();
    }

    /**
     * Ends content capture and renders the page.
     */
    public function End(): void
    {
        $this->content = $this->_ob_get_clean();
        $this->renderer->Render($this);
    }

    #region Library

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

    #endregion Library

    #region Manifest

    /**
     * Returns the page-level manifest.
     *
     * This provides access to any page-specific CSS, JS, or extra assets
     * defined in the page's local `manifest.json` file.
     *
     * > This method is intended to support the renderer and is typically not
     * required in page-level code.
     *
     * @return PageManifest
     *   The associated page manifest instance.
     */
    public function Manifest(): PageManifest
    {
        return $this->pageManifest;
    }

    #endregion Manifest

    #region Meta

    /**
     * Adds or replaces a meta tag.
     *
     * @param string $name
     *   The name of the meta tag (e.g., `description`, `og:title`).
     * @param string|\Stringable $content
     *   The content of the meta tag.
     * @param string $type
     *   (Optional) The attribute type (e.g., `name`, `property`, `itemprop`).
     *   Defaults to `name`.
     * @return self
     *   The current instance.
     */
    public function SetMeta(
        string $name,
        string|\Stringable $content,
        string $type = 'name'
    ): self
    {
        $this->metaCollection->Set($name, $content, $type);
        return $this;
    }

    /**
     * Removes a specific meta tag.
     *
     * If the tag does not exist, the method does nothing.
     *
     * @param string $name
     *   The name of the meta tag to remove (e.g., `description`, `og:title`).
     * @param string $type
     *   The attribute type of the tag (e.g., `name`, `property`, `itemprop`).
     * @return self
     *   The current instance.
     */
    public function RemoveMeta(string $name, string $type): self
    {
        $this->metaCollection->Remove($name, $type);
        return $this;
    }

    /**
     * Removes all meta tags.
     *
     * @return self
     *   The current instance.
     */
    public function RemoveAllMetas(): self
    {
        $this->metaCollection->RemoveAll();
        return $this;
    }

    /**
     * Returns the meta tag definitions.
     *
     * This method guarantees that the `og:title` tag is present based on the
     * page's current title, unless it has been explicitly set elsewhere.
     *
     * > This method is intended to support the renderer and is typically not
     * required in page-level code.
     *
     * @return CArray
     *   A `CArray` of meta tag groups. Each key is the type (e.g., `name`,
     *   `property`, `itemprop`) and each value is a `CArray` of tag names
     *   mapped to their contents.
     */
    public function MetaItems(): CArray
    {
        if (!$this->metaCollection->Has('og:title', 'property')) {
            $this->metaCollection->Set('og:title', $this->Title(), 'property');
        }
        return $this->metaCollection->Items();
    }

    #endregion Meta

    #region Auth

    /**
     * Returns the currently logged-in user's account.
     *
     * The result is cached after the first retrieval.
     *
     * @return ?Account
     *   An `Account` object associated with the logged-in user, or `null` if
     *   no user is logged in.
     */
    public function LoggedInAccount(): ?Account
    {
        return $this->authManager->LoggedInAccount();
    }

    /**
     * Returns the role associated with the currently logged-in user's account.
     *
     * The result is cached after the first retrieval.
     *
     * @return Role
     *   The role of the current user, or `Role::None` if no user is logged in
     *   or a role is not explicitly assigned to the account.
     */
    public function LoggedInAccountRole(): Role
    {
        return $this->authManager->LoggedInAccountRole();
    }

    /**
     * Restricts access to logged-in users.
     *
     * If no user is logged in, the user is redirected to the login page. If a
     * minimum role is specified, the logged-in user's role is checked against
     * it. If the user's role is insufficient, the user is redirected to the
     * error page with an HTTP 401 Unauthorized response.
     *
     * @param Role $minimumRole
     *   (Optional) The minimum role required to access the page. Defaults to
     *   `Role::None`.
     * @return self
     *   The current instance.
     */
    public function RequireLogin(Role $minimumRole = Role::None): self
    {
        $this->authManager->RequireLogin($minimumRole);
        return $this;
    }

    #endregion Auth

    #region Property

    /**
     * Sets the value of a property.
     *
     * This allows the page to pass a value to the masterpage, which can then
     * adjust its layout, structure, or styling accordingly.
     *
     * @param string $key
     *   The name of the property.
     * @param mixed $value
     *   The value of the property.
     * @return self
     *   The current instance.
     *
     * @see Property
     */
    public function SetProperty(string $key, mixed $value): self
    {
        $this->properties->Set($key, $value);
        return $this;
    }

    /**
     * Returns the value of a property.
     *
     * This is typically used in the masterpage to access values set by the
     * page and render content conditionally.
     *
     * @param string $key
     *   The name of the property.
     * @param mixed $default
     *   (Optional) The value to return if the property is not defined.
     * @return mixed
     *   The property value, or the default value if the property is not defined.
     *
     * @see SetProperty
     */
    public function Property(string $key, mixed $default = null): mixed
    {
        return $this->properties->GetOrDefault($key, $default);
    }

    #endregion Property

    #region CSRF

    /**
     * Returns the CSRF token name.
     *
     * This is the name of the form field that will contain the CSRF token
     * when submitted.
     *
     * @return string
     *   The CSRF token name.
     */
    public function CsrfTokenName(): string
    {
        return FormTokenGuard::CSRF_TOKEN_NAME;
    }

    /**
     * Returns a uniquely generated CSRF token value.
     *
     * Aside from token generation, this method also sets the CSRF cookie, which
     * is used to verify the token when the form is submitted.
     *
     * @return string
     *   The CSRF token value.
     */
    public function CsrfTokenValue(): string
    {
        $csrfToken = SecurityService::Instance()->GenerateCsrfToken();
        CookieService::Instance()->SetCsrfCookie($csrfToken->CookieValue());
        return $csrfToken->Token();
    }

    #endregion CSRF

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
        $output = \ob_get_clean();
        return $output === false ? '' : $output;
    }

    #endregion protected
}
