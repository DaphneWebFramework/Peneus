<?php declare(strict_types=1);
/**
 * Renderer.php
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
use \Harmonia\Core\CFile;
use \Harmonia\Core\CPath;
use \Harmonia\Core\CSequentialArray;
use \Harmonia\Core\CUrl;
use \Harmonia\Logger;
use \Peneus\Resource;

/**
 * Handles the rendering of a web page using a template and an optional
 * masterpage.
 */
class Renderer
{
    private readonly Config $config;
    private readonly Resource $resource;
    private readonly Logger $logger;

    /**
     * Constructs a new instance by initializing the configuration, resource,
     * and logger.
     */
    public function __construct()
    {
        $this->config = Config::Instance();
        $this->resource = Resource::Instance();
        $this->logger = Logger::Instance();
    }

    /**
     * Renders the complete page output.
     *
     * Loads the page template, replaces placeholders, and includes the
     * masterpage if one is configured. The final result is sent to output.
     *
     * @param Page $page
     *   The page instance to render.
     */
    public function Render(Page $page): void
    {
        $file = $this->openFile($this->resource->TemplateFilePath('page'));
        if ($file === null) {
            $this->logger->Error('Page template not found.');
            return;
        }
        $template = $file->Read();
        $file->Close();
        if ($template === null) {
            $this->logger->Error('Page template could not be read.');
            return;
        }
        $libraries = $page->IncludedLibraries();
        $html = \strtr($template, [
            "{{AppUrl}}" =>
                $this->appUrl(),
            '{{Language}}' =>
                $this->config->OptionOrDefault('Language', ''),
            '{{Title}}' =>
                $page->Title(),
            "\t{{MetaTags}}" =>
                $this->metaTags($page->MetaItems()),
            "\t{{LibraryStylesheetLinks}}" =>
                $this->libraryStylesheetLinks($libraries),
            "\t{{PageStylesheetLinks}}" =>
                $this->pageStylesheetLinks($page),
            "{{BodyClass}}" =>
                $page->Property('bodyClass', ''),
            "\t{{Content}}" =>
                $this->content($page),
            "\t{{LibraryJavascriptLinks}}" =>
                $this->libraryJavascriptLinks($libraries),
            "\t{{PageJavascriptLinks}}" =>
                $this->pageJavascriptLinks($page),
        ]);
        $this->_echo($html);
    }

    #region protected ----------------------------------------------------------

    /**
     * Generates the content section by executing the selected masterpage.
     *
     * If a masterpage is defined, it is included with `$this` bound to the
     * page instance, allowing access to its methods and properties. Otherwise,
     * the raw page content is returned as-is.
     *
     * @param Page $page
     *   The page instance providing the content and masterpage name.
     * @return string
     *   The generated content section to be inserted into the template.
     */
    protected function content(Page $page): string
    {
        $this->_ob_start();
        $masterpage = $page->Masterpage();
        if ($masterpage === '') {
            $this->_echo($page->Content());
        } else {
            $masterpagePath = $this->resource->MasterpageFilePath($masterpage);
            if (!$masterpagePath->Call('\is_file')) {
                $this->logger->Error("Masterpage not found: {$masterpage}");
            } else {
                (function() use($masterpagePath) {
                    include $masterpagePath;
                })->call($page);
            }
        }
        return $this->_ob_get_clean();
    }

    /**
     * Retrieves the application URL without a trailing slash.
     *
     * @return string
     *   The application URL.
     */
    protected function appUrl(): string
    {
        return $this->resource->AppUrl()->TrimTrailingSlashes()->__toString();
    }

    /**
     * Generates <meta> tags.
     *
     * @param CArray $metaItems
     *   A `CArray` of meta tag groups. Each key is the type (e.g., `name`,
     *   `property`, `itemprop`) and each value is a `CArray` of tag names
     *   mapped to their contents.
     * @return string
     *   A newline-separated string of <meta> tags.
     */
    protected function metaTags(CArray $metaItems): string
    {
        $result = '';
        foreach ($metaItems as $type => $group) {
            foreach ($group as $name => $content) {
                $_type = \htmlspecialchars($type, \ENT_QUOTES);
                $_name = \htmlspecialchars($name, \ENT_QUOTES);
                $_content = \htmlspecialchars($content, \ENT_QUOTES);
                $result .= "\t<meta {$_type}=\"{$_name}\" content=\"{$_content}\">\n";
            }
        }
        return \rtrim($result, "\n");
    }

    /**
     * Generates `<link>` tags for the libraries' stylesheets.
     *
     * @param CSequentialArray $libraries
     *   The libraries whose stylesheets will be rendered.
     * @return string
     *   A newline-separated string of `<link>` tags.
     */
    protected function libraryStylesheetLinks(CSequentialArray $libraries): string
    {
        $result = '';
        foreach ($libraries as $library) {
            foreach ($library->Css() as $path) {
                $url = $this->resolveLibraryAssetUrl($path, 'css');
                $result .= "\t<link rel=\"stylesheet\" href=\"{$url}\">\n";
            }
        }
        return \rtrim($result, "\n");
    }

    /**
     * Generates `<script>` tags for the libraries' scripts.
     *
     * @param CSequentialArray $libraries
     *   The libraries whose scripts will be rendered.
     * @return string
     *   A newline-separated string of `<script>` tags.
     */
    protected function libraryJavascriptLinks(CSequentialArray $libraries): string
    {
        $result = '';
        foreach ($libraries as $library) {
            foreach ($library->Js() as $path) {
                $url = $this->resolveLibraryAssetUrl($path, 'js');
                $result .= "\t<script src=\"{$url}\"></script>\n";
            }
        }
        return \rtrim($result, "\n");
    }

    /**
     * Generates `<link>` tags for the page's stylesheets.
     *
     * @param Page $page
     *   The page whose stylesheets will be rendered.
     * @return string
     *   A newline-separated string of `<link>` tags.
     */
    protected function pageStylesheetLinks(Page $page): string
    {
        $result = '';
        $pageId = $page->Id();
        foreach ($page->Manifest()->Css() as $path) {
            $url = $this->resolvePageAssetUrl($pageId, $path, 'css');
            $result .= "\t<link rel=\"stylesheet\" href=\"{$url}\">\n";
        }
        return \rtrim($result, "\n");
    }

    /**
     * Generates `<script>` tags for the page's scripts.
     *
     * @param Page $page
     *   The page whose scripts will be rendered.
     * @return string
     *   A newline-separated string of `<script>` tags.
     */
    protected function pageJavascriptLinks(Page $page): string
    {
        $result = '';
        $pageId = $page->Id();
        foreach ($page->Manifest()->Js() as $path) {
            $url = $this->resolvePageAssetUrl($pageId, $path, 'js');
            $result .= "\t<script src=\"{$url}\"></script>\n";
        }
        return \rtrim($result, "\n");
    }

    /**
     * Resolves the URL for a given frontend asset path.
     *
     * If the path is a URL (beginning with `http://` or `https://`), it is
     * returned as-is.
     *
     * For paths relative to the `frontend` directory, if a valid extension is
     * not present, the appropriate file extension is appended. In production
     * mode, a `.min` suffix is applied before the extension to target minified
     * versions.
     *
     * The resulting path is then converted into a URL including a cache-busting
     * query parameter derived from the file's modification time.
     *
     * @param string $path
     *   A path relative to the application's `frontend` directory, or a URL.
     * @param string $extension
     *   The expected file type, either `'css'` or `'js'`. This parameter must
     *   be in lowercase.
     * @return string
     *   The resolved asset URL.
     */
    protected function resolveLibraryAssetUrl(
        string $path,
        string $extension,
    ): CUrl
    {
        if ($this->isRemoteAsset($path)) {
            return new CUrl($path);
        }
        if ($extension !== $this->lowercaseExtension($path)) {
            if (!$this->config->OptionOrDefault('IsDebug', false)) {
                $path .= '.min';
            }
            $path .= ".{$extension}";
        }
        return $this->resource->FrontendLibraryFileUrl($path);
    }

    /**
     * Resolves the URL for a given page-level asset path.
     *
     * If the path is a URL (beginning with `http://` or `https://`), it is
     * returned as-is.
     *
     * For paths relative to a page directory, if a valid extension is not
     * present, the appropriate file extension is appended. Unlike library
     * assets, no `.min` suffix is added in production mode.
     *
     * The resulting path is then converted into a URL using `PageFileUrl`,
     * which includes a cache-busting query parameter derived from the file's
     * modification time, if available.
     *
     * @param string $pageId
     *   The identifier (folder name) of the page (e.g., `'home'`).
     * @param string $path
     *   A path relative to the page's directory, or a URL (e.g., `'index'`,
     *   `'theme.css'`, `'https://cdn.example.com/style.css'`).
     * @param string $extension
     *   The expected file type, either `'css'` or `'js'`. This parameter must
     *   be in lowercase.
     * @return string
     *   The resolved asset URL.
     */
    protected function resolvePageAssetUrl(
        string $pageId,
        string $path,
        string $extension
    ): CUrl
    {
        if ($this->isRemoteAsset($path)) {
            return new CUrl($path);
        }
        if ($extension !== $this->lowercaseExtension($path)) {
            $path .= ".{$extension}";
        }
        return $this->resource->PageFileUrl($pageId, $path);
    }

    /**
     * Determines whether a given asset path refers to a remote resource.
     *
     * A remote resource is identified by a URI that begins with `http://` or
     * `https://`, case-insensitively.
     *
     * @param string $path
     *   The asset path to check.
     * @return bool
     *   Returns `true` if the path refers to a remote URL, `false` otherwise.
     */
    protected function isRemoteAsset(string $path): bool
    {
        return \preg_match('#^https?://#i', $path) === 1;
    }

    /**
     * Extracts the file extension from a given path and converts it to
     * lowercase.
     *
     * @param string $path
     *   The file path from which to extract the extension.
     * @return string
     *   The lowercase file extension, or an empty string if no extension is
     *   found.
     */
    protected function lowercaseExtension(string $path): string
    {
        return \strtolower(\pathinfo($path, \PATHINFO_EXTENSION));
    }

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

    /** @codeCoverageIgnore */
    protected function _echo(string $string): void
    {
        echo $string;
    }

    /** @codeCoverageIgnore */
    protected function openFile(CPath $filePath): ?CFile
    {
        return CFile::Open($filePath);
    }

    #endregion protected
}
