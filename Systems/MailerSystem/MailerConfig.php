<?php declare(strict_types=1);
/**
 * MailerConfig.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Systems\MailerSystem;

/**
 * Holds all settings required by a mailer implementation.
 */
class MailerConfig
{
    /**
     * Indicates whether the request is made over HTTPS.
     */
    public bool $isHttps;

    /**
     * The host name or IP address of the SMTP server.
     */
    public string $host;

    /**
     * The port used to connect to the SMTP server.
     */
    public int $port;

    /**
     * The encryption method used ('tls' or 'ssl').
     */
    public string $encryption;

    /**
     * The username used for SMTP authentication.
     */
    public string $username;

    /**
     * The password used for SMTP authentication.
     */
    public string $password;

    /**
     * The email address to appear in the "From" field.
     */
    public string $fromAddress;

    /**
     * The display name to appear in the "From" field.
     */
    public string $fromName;

    /**
     * The log level (0, 1, 2, or 3).
     */
    public int $logLevel;
}
