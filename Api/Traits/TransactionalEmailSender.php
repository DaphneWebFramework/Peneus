<?php declare(strict_types=1);
/**
 * TransactionalEmailSender.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Traits;

use \Harmonia\Config;
use \Harmonia\Core\CFile;
use \Harmonia\Core\CPath;
use \Harmonia\Logger;
use \Peneus\Resource;
use \Peneus\Systems\MailerSystem\Mailer;

/**
 * Sends an email based on the "transactional-email.html" template.
 */
trait TransactionalEmailSender
{
    /**
     * @param string $emailAddress
     *   The recipient's email address.
     * @param string $displayName
     *   The recipient's display name.
     * @param string $actionUrl
     *   The URL for the call-to-action (CTA) button in the email.
     * @param array<string, string> $substitutions
     *   The substitutions to replace placeholders in the email template.
     *   Expected keys are: "heroText", "introText", "buttonText", and
     *   "disclaimerText".
     * @return bool
     *   Returns `true` if the email was sent successfully, `false` otherwise.
     */
    protected function sendTransactionalEmail(
        string $emailAddress,
        string $displayName,
        string $actionUrl,
        array $substitutions
    ): bool
    {
        $resource = Resource::Instance();
        $file = $this->openFile($resource->TemplateFilePath('transactional-email'));
        if ($file === null) {
            Logger::Instance()->Error('Email template not found.');
            return false;
        }
        $template = $file->Read();
        $file->Close();
        if ($template === null) {
            Logger::Instance()->Error('Email template could not be read.');
            return false;
        }
        $config = Config::Instance();
        $html = \strtr($template, [
            '{{AppName}}' => $config->OptionOrDefault('AppName', ''),
            '{{Language}}' => $config->OptionOrDefault('Language', 'en'),
            '{{Title}}' => $substitutions['heroText'],
            '{{HeroText}}' => $substitutions['heroText'],
            '{{UserName}}' => $displayName,
            '{{IntroText}}' => $substitutions['introText'],
            '{{ActionUrl}}' => $actionUrl,
            '{{ButtonText}}' => $substitutions['buttonText'],
            '{{DisclaimerText}}' => $substitutions['disclaimerText'],
            '{{SupportEmail}}' => $config->OptionOrDefault('SupportEmail', ''),
            '{{CurrentYear}}' => $this->currentYear(),
        ]);
        return $this->newMailer()
            ->SetAddress($emailAddress)
            ->SetSubject($substitutions['heroText'])
            ->SetBody($html)
            ->Send();
    }

    /** @codeCoverageIgnore */
    protected function openFile(CPath $filePath): ?CFile
    {
        return CFile::Open($filePath);
    }

    /** @codeCoverageIgnore */
    protected function newMailer(): Mailer
    {
        return new Mailer();
    }

    /** @codeCoverageIgnore */
    protected function currentYear(): string
    {
        return \date('Y');
    }
}
