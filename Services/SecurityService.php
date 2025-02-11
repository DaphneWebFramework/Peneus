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

class SecurityService extends Singleton
{
    private const TOKEN_LENGTH = 64;

    #region public -------------------------------------------------------------

    public function HashPassword(string $password): string
    {
        return \password_hash($password, \PASSWORD_DEFAULT);
    }

    public function VerifyPassword(string $password, string $hash): bool
    {
        return \password_verify($password, $hash);
    }

    public function GenerateToken(): string
    {
        return \bin2hex(\random_bytes(self::TOKEN_LENGTH / 2));
    }

    public function GenerateCsrfToken(): CsrfToken
    {
        $token = $this->GenerateToken();
        $cookieValue = $this->obfuscate(
            $this->HashPassword($token)
        );
        return new CsrfToken($token, $cookieValue);
    }

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

    private function obfuscate(string $data): string
    {
        return \bin2hex(\strrev($data));
    }

    private function deobfuscate(string $data): string
    {
        // Suppress notices:
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
