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

class SecurityService
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

    public function GenerateCsrfToken(): \stdClass
    {
        $csrfToken = new \stdClass();
        $csrfToken->token = $this->GenerateToken();
        $csrfToken->cookieValue = $this->obfuscate(
            $this->HashPassword(
                $csrfToken->token
            )
        );
        return $csrfToken;
    }

    public function VerifyCsrfToken(\stdClass $csrfToken): bool
    {
        return $this->VerifyPassword(
            $csrfToken->token,
            $this->deobfuscate(
                $csrfToken->cookieValue
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
        $decoded = @\hex2bin($data);
        if ($decoded === false) {
            return '';
        }
        return \strrev($decoded);
    }

    #endregion private
}
