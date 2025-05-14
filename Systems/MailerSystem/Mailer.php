<?php declare(strict_types=1);
/**
 * Mailer.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Systems\MailerSystem;

use \Harmonia\Config;
use \Harmonia\Server;

/**
 * Provides a unified mailer facade that selects and configures the appropriate
 * mailer implementation.
 *
 * In development mode, this class delegates to a fake mailer. Otherwise, it
 * uses application settings to configure transport, authentication, sender, and
 * logging parameters, and delegates to a real SMTP-backed implementation.
 */
class Mailer
{
    /**
     * The actual mailer implementation used.
     *
     * @var IMailerImpl
     */
    protected IMailerImpl $impl;

    /**
     * Constructs a new instance using application configuration.
     */
    public function __construct()
    {
        $config = Config::Instance();
        if ($config->Option('IsDebug')) {
            $this->impl = new FakeMailerImpl();
            return;
        }
        $mailerConfig = new MailerConfig();
        $mailerConfig->isHttps = Server::Instance()->IsSecure();
        $mailerConfig->host = $config->OptionOrDefault('MailerHost', 'localhost');
        $mailerConfig->port = $config->OptionOrDefault('MailerPort', 587);
        $mailerConfig->encryption = $config->OptionOrDefault('MailerEncryption', 'tls');
        $mailerConfig->username = $config->OptionOrDefault('MailerUsername', '');
        $mailerConfig->password = $config->OptionOrDefault('MailerPassword', '');
        $mailerConfig->fromAddress = $config->OptionOrDefault('MailerUsername', '');
        $mailerConfig->fromName = $config->OptionOrDefault('AppName', '');
        $mailerConfig->logLevel = $config->OptionOrDefault('LogLevel', 0);
        $this->impl = new MailerImpl($mailerConfig);
    }

    public function SetAddress(string $email): static
    {
        $this->impl->SetAddress($email);
        return $this;
    }

    public function SetSubject(string $subject): static
    {
        $this->impl->SetSubject($subject);
        return $this;
    }

    public function SetBody(string $body): static
    {
        $this->impl->SetBody($body);
        return $this;
    }

    public function Send(): bool
    {
        return $this->impl->Send();
    }
}
