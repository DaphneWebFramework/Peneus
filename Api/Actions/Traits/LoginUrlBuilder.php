<?php declare(strict_types=1);
/**
 * LoginUrlBuilder.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Actions\Traits;

use \Peneus\Resource;

/**
 * Builds the login URL.
 */
trait LoginUrlBuilder
{
    /**
     * @return string
     *   The login URL.
     */
    protected function buildLoginUrl(): string
    {
        $resource = Resource::Instance();

        // e.g. "https://example.com/pages/home/" → "%2Fpages%2Fhome%2F"
        $homePageUri = $resource->PageUrl('home')
            ->ApplyInPlace('\parse_url', \PHP_URL_PATH)
            ->ApplyInPlace('\rawurlencode');

        // e.g. "https://example.com/pages/login/?redirect=%2Fpages%2Fhome%2F"
        return (string)$resource->PageUrl('login')
            ->AppendInPlace("?redirect={$homePageUri}");
    }
}
