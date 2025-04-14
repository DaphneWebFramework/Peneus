<?php declare(strict_types=1);
/**
 * Resource.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus;

use \Harmonia\Patterns\Singleton;

use \Harmonia\Core\CFileSystem;
use \Harmonia\Core\CPath;
use \Harmonia\Core\CUrl;
use \Harmonia\Server;

/**
 * Provides additional resources specific to the Peneus library.
 *
 * This class uses composition to wrap `Harmonia\Resource`, allowing Peneus to
 * extend resource functionality without inheriting from it. This design avoids
 * the "singleton inheritance trap", which can lead to initialization conflicts
 * when both base and subclass maintain separate singleton instances.
 */
class Resource extends Singleton
{
    /**
     * The underlying Harmonia resource instance.
     *
     * @var \Harmonia\Resource
     */
    private readonly \Harmonia\Resource $base;

    /**
     * Constructs a new instance by initializing the base resource.
     */
    protected function __construct()
    {
        $this->base = \Harmonia\Resource::Instance();
    }

    /**
     * Delegates unknown method calls to the base resource.
     *
     * This enables consumers of `Peneus\Resource` to transparently access all
     * public methods of `Harmonia\Resource`, simulating inheritance through
     * composition without requiring explicit method forwarding.
     *
     * @param string $method
     *   The method name being called.
     * @param array $arguments
     *   The arguments passed to the method.
     * @return mixed
     *   The result of the delegated method call.
     * @throws \Error
     *   If the method does not exist on the base resource.
     */
    public function __call(string $method, array $arguments)
    {
        return $this->base->$method(...$arguments);
    }

    /**
     * Returns the absolute path to the specified template file.
     *
     * @param string $templateName
     *   The name of the template file without the extension.
     * @return CPath
     *   The absolute path to the template file.
     */
    public function TemplateFilePath($templateName): CPath
    {
        return CPath::Join(
            $this->base->AppSubdirectoryPath('templates'),
            "{$templateName}.html"
        );
    }

    /**
     * Returns the absolute path to the specified masterpage file.
     *
     * @param string $masterpageName
     *   The name of the masterpage file without the extension.
     * @return CPath
     *   The absolute path to the masterpage file.
     */
    public function MasterpageFilePath($masterpageName): CPath
    {
        return CPath::Join(
            $this->base->AppSubdirectoryPath('masterpages'),
            "{$masterpageName}.php"
        );
    }

    /**
     * Returns the absolute path to the frontend manifest file.
     *
     * @return CPath
     *   The absolute path to the frontend manifest file.
     */
    public function FrontendManifestFilePath(): CPath
    {
        return CPath::Join(
            $this->base->AppSubdirectoryPath('frontend'),
            'manifest.json'
        );
    }

    /**
     * Returns the URL to a frontend library file, with a cache buster query
     * parameter based on the file's modification time.
     *
     * @param string $relativePath
     *   The path relative to the frontend directory (e.g. 'bootstrap/css/bootstrap').
     * @return string
     *   The URL to the asset, with cache-busting query if the file exists.
     */
    public function FrontendLibraryFileUrl(string $relativePath): CUrl
    {
        $fileUrl = CUrl::Join(
            $this->base->AppSubdirectoryUrl('frontend'),
            $relativePath
        );
        $filePath = CPath::Join(
            $this->base->AppSubdirectoryPath('frontend'),
            $relativePath
        );
        $modTime = CFileSystem::Instance()->ModificationTime($filePath);
        if ($modTime !== 0) {
            $fileUrl->AppendInPlace('?' . $modTime);
        }
        return $fileUrl;
    }

    /**
     * Returns the absolute path to a page directory.
     *
     * @param string $pageId
     *   The identifier (folder name) of the page, e.g. `'home'`.
     * @return CPath
     *   The absolute path to the page directory.
     */
    public function PagePath(string $pageId): CPath
    {
        return CPath::Join(
            $this->base->AppSubdirectoryPath('pages'),
            $pageId
        );
    }

    /**
     * Returns the URL to a page directory.
     *
     * @param string $pageId
     *   The identifier (folder name) of the page, e.g. `'home'`.
     * @return CUrl
     *   The URL to the page directory with a trailing slash.
     */
    public function PageUrl(string $pageId): CUrl
    {
        return CUrl::Join(
            $this->base->AppSubdirectoryUrl('pages'),
            $pageId
        )->EnsureTrailingSlash();
    }

    /**
     * Returns the URL to the login page, including a "redirect" query parameter
     * that points to the current request URI.
     *
     * @return CUrl
     *   The URL to the login page with a "redirect" query parameter.
     */
    public function LoginPageUrl(): CUrl
    {
        $url = $this->PageUrl('login');
        $requestUri = Server::Instance()->RequestUri();
        if ($requestUri !== null) {
            $requestUri->ApplyInPlace('\rawurlencode');
            $url->AppendInPlace('?redirect=' . $requestUri);
        }
        return $url;
    }

    /**
     * Returns the absolute path to a file within a page directory.
     *
     * @param string $pageId
     *   The identifier (folder name) of the page, e.g. `'home'`.
     * @param string $relativePath
     *   The path relative to the page directory, e.g., `'style.css'`.
     * @return CPath
     *   The absolute path to the file.
     */
    public function PageFilePath(string $pageId, string $relativePath): CPath
    {
        return CPath::Join($this->PagePath($pageId), $relativePath);
    }

    /**
     * Returns the URL to a page-specific asset file with a cache buster query
     * parameter based on the file's modification time.
     *
     * @param string $pageId
     *   The identifier (folder name) of the page, e.g. `'home'`.
     * @param string $relativePath
     *   The path relative to the page directory, e.g., `'style.css'`.
     * @return CUrl
     *   The URL to the asset, with cache-busting query if the file exists.
     */
    public function PageFileUrl(string $pageId, string $relativePath): CUrl
    {
        $fileUrl = CUrl::Join($this->PageUrl($pageId), $relativePath);
        $filePath = $this->PageFilePath($pageId, $relativePath);
        $modTime = CFileSystem::Instance()->ModificationTime($filePath);
        if ($modTime !== 0) {
            $fileUrl->AppendInPlace('?' . $modTime);
        }
        return $fileUrl;
    }
}
