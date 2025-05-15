<?php declare(strict_types=1);
/**
 * RegisterAccountAction.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Actions;

use \Harmonia\Config;
use \Harmonia\Core\CFile;
use \Harmonia\Core\CPath;
use \Harmonia\Database\Database;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Logger;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Validation\Validator;
use \Peneus\Model\Account;
use \Peneus\Model\PendingAccount;
use \Peneus\Resource;
use \Peneus\Systems\MailerSystem\Mailer;
use \Peneus\Translation;

/**
 * Registers a new user account and sends an activation email.
 */
class RegisterAccountAction extends Action
{
    /**
     * Executes the account registration process by validating user input,
     * checking for duplicate email addresses, creating a pending account record,
     * and sending an activation email with an activation code.
     *
     * On failure, the database transaction is rolled back and an exception is
     * thrown.
     *
     * @return array<string, string>
     *   An associative array with a 'message' key containing a localized
     *   success message to display to the user.
     * @throws \RuntimeException
     *   If the email address field is missing or invalid, if the password field
     *   is missing or shorter than 8 or longer than 72 characters, if the
     *   display name field is missing or does not match the required pattern,
     *   if the email address is already registered, if the email address is
     *   already awaiting activation, if the pending account cannot be created,
     *   if the activation email cannot be sent, or if the CSRF cookie cannot be
     *   deleted.
     */
    protected function onExecute(): mixed
    {
        $validator = new Validator([
            'email' => [
                'required',
                'email'
            ],
            'password' => [
                'required',
                'string',
                'minLength: 8',
                'maxLength: 72'
            ],
            'displayName' => [
                'required',
                // Matches a 2–50 character display name starting with a letter
                // or number, allowing letters, numbers, spaces, dots, hyphens,
                // and apostrophes, with full Unicode support.
                "regex: /^[\p{L}\p{N}][\p{L}\p{N} .\-']{1,49}$/u"
            ]
        ]);
        $dataAccessor = $validator->Validate(Request::Instance()->FormParams());
        $email = $dataAccessor->GetField('email');
        $password = $dataAccessor->GetField('password');
        $displayName = $dataAccessor->GetField('displayName');
        $translation = Translation::Instance();
        if ($this->isEmailAlreadyRegistered($email)) {
            throw new \RuntimeException(
                $translation->Get('error_email_already_registered'),
                StatusCode::Conflict->value
            );
        }
        if ($this->isEmailAlreadyPending($email)) {
            throw new \RuntimeException(
                $translation->Get('error_email_already_pending'),
                StatusCode::Conflict->value
            );
        }
        $result = Database::Instance()->WithTransaction(function()
            use($email, $password, $displayName)
        {
            $activationCode = SecurityService::Instance()->GenerateToken();
            if (!$this->createPendingAccount($email, $password, $displayName, $activationCode)) {
                throw new \RuntimeException('Failed to create pending account.');
            }
            if (!$this->sendActivationEmail($email, $displayName, $activationCode)) {
                throw new \RuntimeException('Failed to send activation email.');
            }
            CookieService::Instance()->DeleteCsrfCookie();
            return true;
        });
        if ($result !== true) {
            throw new \RuntimeException(
                $translation->Get('error_register_account_failed'),
                StatusCode::InternalServerError->value
            );
        }
        return [
            'message' => $translation->Get('success_account_activation_link_sent')
        ];
    }

    #region protected ----------------------------------------------------------

    protected function isEmailAlreadyRegistered(string $email): bool
    {
        return 0 !== Account::Count(
            condition: 'email = :email',
            bindings: ['email' => $email]
        );
    }

    protected function isEmailAlreadyPending(string $email): bool
    {
        return 0 !== PendingAccount::Count(
            condition: 'email = :email',
            bindings: ['email' => $email]
        );
    }

    protected function createPendingAccount(
        string $email,
        string $password,
        string $displayName,
        string $activationCode
    ): bool
    {
        $pendingAccount = new PendingAccount();
        $pendingAccount->email = $email;
        $pendingAccount->passwordHash = SecurityService::Instance()->HashPassword($password);
        $pendingAccount->displayName = $displayName;
        $pendingAccount->activationCode = $activationCode;
        $pendingAccount->timeRegistered = new \DateTime(); // now
        return $pendingAccount->Save();
    }

    protected function sendActivationEmail(
        string $email,
        string $displayName,
        string $activationCode
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
            '{{Language}}'           => $language,
            '{{Title}}'              => $translation->Get('email_activate_account_masthead'),
            '{{MastheadText}}'       => $translation->Get('email_activate_account_masthead'),
            '{{GreetingText}}'       => $translation->Get('email_common_greeting', $displayName),
            '{{IntroText}}'          => $translation->Get('email_activate_account_intro'),
            '{{ActionUrl}}'          => $resource->PageUrl('activate-account') . $activationCode,
            '{{ButtonText}}'         => $translation->Get('email_activate_account_button_text'),
            '{{SecurityNoticeText}}' => $translation->Get('email_activate_account_security_notice', $appName),
            '{{ContactUsText}}'      => $translation->Get('email_common_contact_us'),
            '{{SupportEmail}}'       => $supportEmail,
            '{{CopyrightText}}'      => $translation->Get('email_common_copyright', \date('Y'), $appName)
        ]);

        return $this->newMailer()
            ->SetAddress($email)
            ->SetSubject($translation->Get('email_activate_account_masthead'))
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

    #endregion protected
}
