<?php declare(strict_types=1);
/**
 * SecurityService.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Services;

use \Harmonia\Patterns\Singleton;
use \Peneus\Services\Model\CsrfToken;

/**
 * Provides security-related utilities.
 */
class SecurityService extends Singleton
{
    #region public -------------------------------------------------------------

    /**
     * Hashes a password using a secure hashing algorithm.
     *
     * @param string $password
     *   The plaintext password.
     * @return string
     *   The hashed password.
     */
    public function HashPassword(string $password): string
    {
        return \password_hash($password, \PASSWORD_DEFAULT);
    }

    /**
     * Verifies a plaintext password against a hashed password.
     *
     * @param string $password
     *   The plaintext password.
     * @param string $hash
     *   The hashed password for comparison.
     * @return bool
     *   Returns `true` if the password matches the hash, otherwise `false`.
     */
    public function VerifyPassword(string $password, string $hash): bool
    {
        return \password_verify($password, $hash);
    }

    /**
     * Generates a cryptographically secure random token.
     *
     * @return string
     *   A 64-character hexadecimal token.
     */
    public function GenerateToken(): string
    {
        return \bin2hex(\random_bytes(32));
    }

    /**
     * Generates a CSRF token and its hashed cookie value.
     *
     * @return CsrfToken
     *   A `CsrfToken` instance containing the token and its obfuscated hash.
     */
    public function GenerateCsrfToken(): CsrfToken
    {
        $token = $this->GenerateToken();
        $cookieValue = $this->obfuscate(
            $this->HashPassword($token)
        );
        return new CsrfToken($token, $cookieValue);
    }

    /**
     * Verifies whether a CSRF token matches its expected hash.
     *
     * @param CsrfToken $csrfToken
     *   The CSRF token instance to verify.
     * @return bool
     *   Returns `true` if the token is valid, otherwise `false`.
     */
    public function VerifyCsrfToken(CsrfToken $csrfToken): bool
    {
        return $this->VerifyPassword(
            $csrfToken->Token(),
            $this->deobfuscate(
                $csrfToken->CookieValue()
            )
        );
    }

    #endregion public

    #region private ------------------------------------------------------------

    /**
     * Obfuscates a string to prevent direct comparison attacks.
     *
     * @param string $data
     *   The string to obfuscate.
     * @return string
     *   The obfuscated string.
     */
    private function obfuscate(string $data): string
    {
        return \bin2hex(\strrev($data));
    }

    /**
     * Reverses the obfuscation process on a string.
     *
     * @param string $data
     *   The obfuscated string.
     * @return string
     *   The original string if decoding succeeds, otherwise an empty string.
     */
    private function deobfuscate(string $data): string
    {
        // Suppressed errors:
        // - hex2bin(): Hexadecimal input string must have an even length
        // - hex2bin(): Input string must be hexadecimal string
        $decoded = @\hex2bin($data);
        if ($decoded === false) {
            return '';
        }
        return \strrev($decoded);
    }

    #endregion private
}
