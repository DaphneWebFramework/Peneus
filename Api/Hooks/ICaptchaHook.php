<?php declare(strict_types=1);
/**
 * ICaptchaHook.php
 *
 * (C) 2026 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Hooks;

/**
 * Interface for verifying a captcha provided by the client.
 */
interface ICaptchaHook
{
    /**
     * Verifies the captcha associated with the current request.
     *
     * @throws \RuntimeException
     *   If the verification fails.
     */
    public function OnVerifyCaptcha(): void;
}
