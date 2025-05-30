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

namespace Peneus\Api\Actions\Traits;

use \Harmonia\Config;
use \Harmonia\Core\CFile;
use \Harmonia\Core\CPath;
use \Harmonia\Logger;
use \Peneus\Resource;
use \Peneus\Systems\MailerSystem\Mailer;
use \Peneus\Translation;

/**
 * Sends an email based on the "transactional-email.html" template.
 *
 * #### Example
 * ```php
 * <?php declare(strict_types=1);
 *
 * namespace Peneus\Api\Actions;
 *
 * use \Peneus\Api\Actions\Traits\TransactionalEmailSender;
 *
 * class WelcomeAction extends Action
 * {
 *     use TransactionalEmailSender;
 *
 *     protected function onExecute(): mixed
 *     {
 *         return $this->sendTransactionalEmail(
 *             'john@example.com',
 *             'John Doe',
 *             'https://example.com/welcome/',
 *             [
 *                 'masthead' => 'email_welcome_masthead',
 *                 'intro' => 'email_welcome_intro',
 *                 'buttonText' => 'email_welcome_button_text',
 *                 'securityNotice' => 'email_welcome_security_notice'
 *             ]
 *         );
 *     }
 * }
 * ```
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
     * @param array $translationKeys
     *   An associative array of translation keys to use in the email template.
     *   Expected keys include: "masthead", "intro", "buttonText", and
     *   "securityNotice".
     * @return bool
     *   Returns `true` if the email was sent successfully, `false` otherwise.
     */
    protected function sendTransactionalEmail(
        string $emailAddress,
        string $displayName,
        string $actionUrl,
        array $translationKeys
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
        $language = $config->OptionOrDefault('Language', 'en');
        $appName = $config->OptionOrDefault('AppName', '');
        $supportEmail = $config->OptionOrDefault('SupportEmail', '');

        $translation = Translation::Instance();
        $html = \strtr($template, [
            '{{Language}}' =>
                $language,
            '{{Title}}' =>
                $translation->Get($translationKeys['masthead']),
            '{{MastheadText}}' =>
                $translation->Get($translationKeys['masthead']),
            '{{GreetingText}}' =>
                $translation->Get('email_common_greeting', $displayName),
            '{{IntroText}}' =>
                $translation->Get($translationKeys['intro']),
            '{{ActionUrl}}' =>
                $actionUrl,
            '{{ButtonText}}' =>
                $translation->Get($translationKeys['buttonText']),
            '{{SecurityNoticeText}}' =>
                $translation->Get($translationKeys['securityNotice'], $appName),
            '{{ContactUsText}}' =>
                $translation->Get('email_common_contact_us'),
            '{{SupportEmail}}' =>
                $supportEmail,
            '{{CopyrightText}}' =>
                $translation->Get('email_common_copyright', $this->currentYear(), $appName),
        ]);

        return $this->newMailer()
            ->SetAddress($emailAddress)
            ->SetSubject($translation->Get($translationKeys['masthead']))
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
