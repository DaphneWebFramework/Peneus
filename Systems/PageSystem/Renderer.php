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
use \Harmonia\Logger;
use \Peneus\Resource;

/**
 * Handles the rendering of a web page using a template and an optional
 * masterpage.
 */
class Renderer
{
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
        $file = $this->openFile(Resource::Instance()->TemplateFilePath('page'));
        if ($file === null) {
            Logger::Instance()->Error('Page template not found.');
            return;
        }
        $template = $file->Read();
        $file->Close();
        if ($template === null) {
            Logger::Instance()->Error('Page template could not be read.');
            return;
        }
        $html = \strtr($template, [
            '{{Language}}' => Config::Instance()->OptionOrDefault('Language', ''),
            '{{Title}}' => $page->Title(),
            "\t{{Contents}}" => $this->contents($page),
        ]);
        $this->_echo($html);
    }

    #region protected ----------------------------------------------------------

    /**
     * Generates the contents section by executing the selected masterpage.
     *
     * If a masterpage is defined, it is included with `$this` bound to the
     * page instance, allowing access to its methods and properties. Otherwise,
     * the raw page content is returned as-is.
     *
     * @param Page $page
     *   The page instance providing the content and masterpage name.
     * @return string
     *   The generated contents section to be inserted into the template.
     */
    protected function contents(Page $page): string
    {
        $this->_ob_start();
        $masterpage = $page->Masterpage();
        if ($masterpage === '') {
            $this->_echo($page->Contents());
        } else {
            $masterpagePath = Resource::Instance()->MasterpageFilePath($masterpage);
            if (!$masterpagePath->IsFile()) {
                Logger::Instance()->Error("Masterpage not found: {$masterpage}");
            } else {
                (function() use($masterpagePath) {
                    include $masterpagePath;
                })->call($page);
            }
        }
        return $this->_ob_get_clean();
    }

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
