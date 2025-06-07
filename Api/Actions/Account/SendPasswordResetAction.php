<?php declare(strict_types=1);
/**
 * SendPasswordResetAction.php
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

use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\ValidationSystem\Validator;
use \Peneus\Api\Traits\TransactionalEmailSender;
use \Peneus\Model\Account;
use \Peneus\Model\PasswordReset;
use \Peneus\Resource;
use \Peneus\Translation;

/**
 * Handles password reset requests for accounts.
 */
class SendPasswordResetAction extends Action
{
    use TransactionalEmailSender;

    /**
     * Executes the password reset request process by validating the email
     * address, locating the associated account, creating a password reset
     * record, and sending a reset email with a reset code.
     *
     * If the email is not registered, the process silently succeeds without
     * revealing that information to the user.
     *
     * On failure, the database transaction is rolled back and an exception is
     * thrown.
     *
     * @return array<string, string>
     *   An associative array with a 'message' key containing a localized
     *   success message to display to the user.
     * @throws \RuntimeException
     *   If the email address field is missing or invalid, if the password reset
     *   record cannot be created, if the reset email cannot be sent, or if the
     *   CSRF cookie cannot be deleted.
     *
     * @todo Define custom error messages for each validation rule.
     */
    protected function onExecute(): mixed
    {
        $translation = Translation::Instance();
        $validator = new Validator([
            'email' => ['required', 'email']
        ]);
        $dataAccessor = $validator->Validate(Request::Instance()->FormParams());
        $email = $dataAccessor->GetField('email');
        $account = $this->findAccount($email);
        if ($account === null) {
            goto Success; // Silently succeed; do not reveal account existence.
        }
        $result = Database::Instance()->WithTransaction(function() use($account) {
            $resetCode = SecurityService::Instance()->GenerateToken();
            if (!$this->createPasswordReset($account->id, $resetCode)) {
                throw new \RuntimeException('Failed to save password reset record.');
            }
            if (!$this->sendPasswordResetEmail($account->email, $account->displayName, $resetCode)) {
                throw new \RuntimeException('Failed to send password reset email.');
            }
            CookieService::Instance()->DeleteCsrfCookie();
            return true;
        });
        if ($result !== true) {
            throw new \RuntimeException(
                $translation->Get('error_send_password_reset_failed'),
                StatusCode::InternalServerError->value
            );
        }
    Success:
        return [
            'message' => $translation->Get('success_password_reset_link_sent')
        ];
    }

    protected function findAccount(string $email): ?Account
    {
        return Account::FindFirst(
            condition: 'email = :email',
            bindings: ['email' => $email]
        );
    }

    protected function createPasswordReset(
        int $accountId,
        string $resetCode,
        \DateTime $timeRequested = null
    ): bool
    {
        $passwordReset = PasswordReset::FindFirst(
            condition: 'accountId = :accountId',
            bindings: ['accountId' => $accountId]
        );
        if ($passwordReset === null) {
            $passwordReset = new PasswordReset();
            $passwordReset->accountId = $accountId;
        }
        $passwordReset->resetCode = $resetCode;
        $passwordReset->timeRequested = $timeRequested ?? new \DateTime();
        return $passwordReset->Save();
    }

    protected function sendPasswordResetEmail(
        string $email,
        string $displayName,
        string $resetCode
    ): bool
    {
        return $this->sendTransactionalEmail(
            $email,
            $displayName,
            Resource::Instance()->PageUrl('reset-password') . $resetCode,
            [
                'masthead' => 'email_reset_password_masthead',
                'intro' => 'email_reset_password_intro',
                'buttonText' => 'email_reset_password_button_text',
                'securityNotice' => 'email_reset_password_security_notice'
            ]
        );
    }
}
