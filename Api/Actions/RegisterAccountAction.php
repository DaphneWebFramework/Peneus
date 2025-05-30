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

use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\ValidationSystem\Validator;
use \Peneus\Api\Actions\Traits\TransactionalEmailSender;
use \Peneus\Model\Account;
use \Peneus\Model\PendingAccount;
use \Peneus\Resource;
use \Peneus\Translation;

/**
 * Registers a new user account and sends an activation email.
 */
class RegisterAccountAction extends Action
{
    use TransactionalEmailSender;

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
     *   is missing or invalid due to length limits, if the display name field
     *   is missing or does not match the required pattern, if the email address
     *   is already registered, if the email address is already awaiting activation,
     *   if the pending account cannot be created, if the activation email cannot
     *   be sent, or if the CSRF cookie cannot be deleted.
     *
     * @todo Define custom error messages for each validation rule.
     */
    protected function onExecute(): mixed
    {
        $translation = Translation::Instance();
        $validator = new Validator([
            'email' => [
                'required',
                'email'
            ],
            'password' => [
                'required',
                'string',
                'minLength:' . SecurityService::PASSWORD_MIN_LENGTH,
                'maxLength:' . SecurityService::PASSWORD_MAX_LENGTH
            ],
            'displayName' => [
                'required',
                // Matches a 2–50 character display name starting with a letter
                // or number, allowing letters, numbers, spaces, dots, hyphens,
                // and apostrophes, with full Unicode support.
                "regex: /^[\p{L}\p{N}][\p{L}\p{N} .\-']{1,49}$/u"
            ]
        ], [
            'displayName.regex' => $translation->Get('error_display_name_invalid')
        ]);
        $dataAccessor = $validator->Validate(Request::Instance()->FormParams());
        $email = $dataAccessor->GetField('email');
        $password = $dataAccessor->GetField('password');
        $displayName = $dataAccessor->GetField('displayName');
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
        return $this->sendTransactionalEmail(
            $email,
            $displayName,
            Resource::Instance()->PageUrl('activate-account') . $activationCode,
            [
                'masthead' => 'email_activate_account_masthead',
                'intro' => 'email_activate_account_intro',
                'buttonText' => 'email_activate_account_button_text',
                'securityNotice' => 'email_activate_account_security_notice'
            ]
        );
    }
}
