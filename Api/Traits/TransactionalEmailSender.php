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
use \Harmonia\Core\CUrl;
use \Harmonia\Traits\FileOpener;
use \Peneus\Resource;
use \Peneus\Systems\MailerSystem\Mailer;

/**
 * Sends an email based on the "transactional-email.html" template.
 */
trait TransactionalEmailSender
{
    use FileOpener;

    /**
     * @param string $emailAddress
     *   The recipient's email address.
     * @param string $displayName
     *   The recipient's display name.
     * @param CUrl $actionUrl
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
        CUrl $actionUrl,
        array $substitutions
    ): bool
    {
        $template = $this->readTransactionalEmailTemplate();
        if ($template === null) {
            return false;
        }
        $composed = $this->composeTransactionalEmail(
            $template,
            $displayName,
            $actionUrl,
            $substitutions
        );
        return $this->makeMailer()
            ->SetAddress($emailAddress)
            ->SetSubject($substitutions['heroText'])
            ->SetBody($composed)
            ->Send();
    }

    /**
     * @return string|null
     */
    protected function readTransactionalEmailTemplate(): ?string
    {
        $template = null;
        $resource = $this->resource ?? Resource::Instance();
        $filePath = $resource->TemplateFilePath('transactional-email');
        $file = $this->openFile($filePath);
        if ($file !== null) {
            $template = $file->Read();
            $file->Close();
        }
        return $template;
    }

    /**
     * @param string $template
     * @param string $displayName
     * @param CUrl $actionUrl
     * @param array<string, string> $substitutions
     * @return string
     */
    protected function composeTransactionalEmail(
        string $template,
        string $displayName,
        CUrl $actionUrl,
        array $substitutions
    ): string
    {
        $config = $this->config ?? Config::Instance();
        return \strtr($template, [
            '{{AppName}}'        => $config->Option('AppName'),
            '{{Language}}'       => $config->Option('Language'),
            '{{Title}}'          => $substitutions['heroText'],
            '{{HeroText}}'       => $substitutions['heroText'],
            '{{UserName}}'       => $displayName,
            '{{IntroText}}'      => $substitutions['introText'],
            '{{ActionUrl}}'      => $actionUrl->__toString(),
            '{{ButtonText}}'     => $substitutions['buttonText'],
            '{{DisclaimerText}}' => $substitutions['disclaimerText'],
            '{{SupportEmail}}'   => $config->Option('SupportEmail'),
            '{{CurrentYear}}'    => \date('Y'),
        ]);
    }

    /**
     * @return Mailer
     */
    protected function makeMailer(): Mailer
    {
        return new Mailer();
    }
}
