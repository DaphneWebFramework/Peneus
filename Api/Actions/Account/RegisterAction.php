<?php declare(strict_types=1);
/**
 * RegisterAction.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Actions\Account;

use \Peneus\Api\Actions\Action;

use \Harmonia\Config;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\ValidationSystem\Validator;
use \Peneus\Api\Traits\TransactionalEmailSender;
use \Peneus\Model\Account;
use \Peneus\Model\PendingAccount;
use \Peneus\Resource;
use \Peneus\Services\AccountService;

/**
 * Registers a new user account and sends an activation email.
 */
class RegisterAction extends Action
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
                'regex:' . AccountService::DISPLAY_NAME_PATTERN
            ]
        ], [
            'displayName.regex' => "Display name is invalid. It must start"
                . " with a letter or number and may only contain letters,"
                . " numbers, spaces, dots, hyphens, and apostrophes."
        ]);
        $dataAccessor = $validator->Validate(Request::Instance()->FormParams());
        $email = $dataAccessor->GetField('email');
        $password = $dataAccessor->GetField('password');
        $displayName = $dataAccessor->GetField('displayName');
        if ($this->isEmailAlreadyRegistered($email)) {
            throw new \RuntimeException(
                "This email address is already registered.",
                StatusCode::Conflict->value
            );
        }
        if ($this->isEmailAlreadyPending($email)) {
            throw new \RuntimeException(
                "This email address is already awaiting activation.",
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
                "Account registration failed.",
                StatusCode::InternalServerError->value
            );
        }
        return [
            'message' => "An account activation link has been sent to your email address."
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
        string $activationCode,
        ?\DateTime $timeRegistered = null
    ): bool
    {
        $pendingAccount = new PendingAccount();
        $pendingAccount->email = $email;
        $pendingAccount->passwordHash =
            SecurityService::Instance()->HashPassword($password);
        $pendingAccount->displayName = $displayName;
        $pendingAccount->activationCode = $activationCode;
        $pendingAccount->timeRegistered = $timeRegistered ?? new \DateTime();
        return $pendingAccount->Save();
    }

    protected function sendActivationEmail(
        string $email,
        string $displayName,
        string $activationCode
    ): bool
    {
        $appName = Config::Instance()->OptionOrDefault('AppName', '');
        $actionUrl = Resource::Instance()->PageUrl('activate-account')
            ->Extend($activationCode)->__toString();
        return $this->sendTransactionalEmail(
            $email,
            $displayName,
            $actionUrl,
            [
                'heroText' =>
                    "Welcome to {$appName}!",
                'introText' =>
                    "You're almost there! Just click the button below to"
                  . " activate your account.",
                'buttonText' =>
                    "Activate My Account",
                'disclaimerText' =>
                    "You received this email because your email address was"
                  . " used to register on {$appName}. If this wasn't you, you"
                  . " can safely ignore this email."
            ]
        );
    }
}
