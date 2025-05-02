<?php declare(strict_types=1);
/**
 * MailerImpl.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Systems\MailerSystem;

use \Harmonia\Logger;
use \PHPMailer\PHPMailer\PHPMailer;
use \PHPMailer\PHPMailer\SMTP;

/**
 * Sends SMTP emails using PHPMailer (>= 6.10.0).
 */
class MailerImpl implements IMailerImpl
{
    protected readonly PHPMailer $phpMailer;
    protected readonly Logger $logger;

    /**
     * Constructs a new instance by initializing PHPMailer with the provided
     * configuration values.
     *
     * @param MailerConfig $mailerConfig
     *   The configuration values used to initialize the PHPMailer instance.
     * @param ?PHPMailer $phpMailer
     *   (Optional) The PHPMailer instance to use. If not specified, a default
     *   instance is created.
     */
    public function __construct(
        MailerConfig $mailerConfig,
        ?PHPMailer $phpMailer = null
    ) {
        $this->phpMailer = $phpMailer ?? new PHPMailer(true);
        $this->logger = Logger::Instance();

        $this->phpMailer->isSMTP();
        $this->phpMailer->CharSet = PHPMailer::CHARSET_UTF8;
        if (!$mailerConfig->isHttps) {
            $this->phpMailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ];
        }
        $this->phpMailer->Host = $mailerConfig->host;
        $this->phpMailer->Port = $mailerConfig->port;
        $this->phpMailer->SMTPSecure = $mailerConfig->encryption;
        $this->phpMailer->SMTPAuth = true;
        $this->phpMailer->Username = $mailerConfig->username;
        $this->phpMailer->Password = $mailerConfig->password;
        $this->phpMailer->setFrom($mailerConfig->fromAddress, $mailerConfig->fromName);
        $this->phpMailer->isHTML(true);
        if ($mailerConfig->logLevel === 0) {
            $this->phpMailer->SMTPDebug = SMTP::DEBUG_OFF;
        } else {
            $this->phpMailer->SMTPDebug = SMTP::DEBUG_SERVER;
            $this->phpMailer->Debugoutput = [$this, 'LoggerCallback'];
        }
    }

    public function SetAddress(string $email): static
    {
        $this->phpMailer->clearAddresses();
        $this->phpMailer->addAddress($email);
        return $this;
    }

    public function SetSubject(string $subject): static
    {
        $this->phpMailer->Subject = $subject;
        return $this;
    }

    public function SetBody(string $body): static
    {
        $this->phpMailer->Body = $body;
        return $this;
    }

    public function Send(): bool
    {
        try {
            return $this->phpMailer->send();
        } catch (\Exception $e) {
            $this->logger->Error("Mailer: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Internal PHPMailer debug output handler.
     *
     * @param string $str
     *   The debug message.
     * @param int $level
     *   The debug level.
     */
    public function LoggerCallback(string $str, int $level): void
    {
        $this->logger->Info("Mailer (level $level): $str");
    }
}
