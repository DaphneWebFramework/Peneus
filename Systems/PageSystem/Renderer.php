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
use \Harmonia\Core\CFile;
use \Harmonia\Core\CPath;
use \Harmonia\Core\CSequentialArray;
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
            '{{Language}}'
                => $this->config->OptionOrDefault('Language', ''),
            '{{Title}}'
                => $page->Title(),
            "\t{{LibraryStylesheetLinks}}"
                => $this->libraryStylesheetLinks($libraries),
            "\t{{Content}}"
                => $this->content($page),
            "\t{{LibraryJavascriptLinks}}"
                => $this->libraryJavascriptLinks($libraries),
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
            if (!$masterpagePath->IsFile()) {
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
     * Generates `<link>` tags for all CSS files of the specified frontend
     * libraries.
     *
     * @param CSequentialArray $libraries
     *   The libraries to render.
     * @return string
     *   A newline-separated string of `<link>` tags.
     */
    protected function libraryStylesheetLinks(CSequentialArray $libraries): string
    {
        $result = '';
        $isDebug = $this->config->OptionOrDefault('IsDebug', false);
        foreach ($libraries as $library) {
            foreach ($library->Css() as $path) {
                $url = $this->resolveAssetUrl($path, 'css', $isDebug);
                $result .= "\t<link rel=\"stylesheet\" href=\"{$url}\">\n";
            }
        }
        return \rtrim($result, "\n");
    }

    /**
     * Generates `<script>` tags for all JS files of the specified frontend
     * libraries.
     *
     * @param CSequentialArray $libraries
     *   The libraries to render.
     * @return string
     *   A newline-separated string of `<script>` tags.
     */
    protected function libraryJavascriptLinks(CSequentialArray $libraries): string
    {
        $result = '';
        $isDebug = $this->config->OptionOrDefault('IsDebug', false);
        foreach ($libraries as $library) {
            foreach ($library->Js() as $path) {
                $url = $this->resolveAssetUrl($path, 'js', $isDebug);
                $result .= "\t<script src=\"{$url}\"></script>\n";
            }
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
     * query parameter derived from the file's modification time, if available.
     *
     * @param string $path
     *   A URL or a path relative to the application's `frontend` directory.
     * @param string $extension
     *   The expected file type, either `'css'` or `'js'`. This parameter must
     *   be in lowercase.
     * @param bool $isDebug
     *   Whether the application is in debug mode.
     * @return string
     *   The resolved asset URL.
     */
    protected function resolveAssetUrl(
        string $path,
        string $extension,
        bool $isDebug
    ): string
    {
        if (\preg_match('#^https?://#i', $path)) {
            return $path;
        }
        if ($extension !== \strtolower(\pathinfo($path, \PATHINFO_EXTENSION))) {
            if (!$isDebug) {
                $path .= '.min';
            }
            $path .= ".{$extension}";
        }
        return (string)$this->resource->FrontendLibraryFileUrl($path);
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
