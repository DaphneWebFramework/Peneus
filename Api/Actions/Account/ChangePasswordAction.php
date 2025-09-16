<?php declare(strict_types=1);
/**
 * ChangePasswordAction.php
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
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\ValidationSystem\Validator;
use \Peneus\Services\AccountService;

/**
 * Changes the password of the currently logged-in account.
 */
class ChangePasswordAction extends Action
{
    /**
     * Executes the password change process by validating the input,
     * verifying the current password, and saving the updated hash.
     *
     * @return mixed
     *   Always returns `null` if the operation is successful.
     * @throws \RuntimeException
     *   If the input is invalid, the user is not logged in, the current
     *   password is incorrect, or the save operation fails.
     *
     * @todo Define custom error messages for each validation rule.
     */
    protected function onExecute(): mixed
    {
        $validator = new Validator([
            'currentPassword' => [
                'required',
                'string',
                'minLength:' . SecurityService::PASSWORD_MIN_LENGTH,
                'maxLength:' . SecurityService::PASSWORD_MAX_LENGTH
            ],
            'newPassword' => [
                'required',
                'string',
                'minLength:' . SecurityService::PASSWORD_MIN_LENGTH,
                'maxLength:' . SecurityService::PASSWORD_MAX_LENGTH
            ]
        ]);
        $dataAccessor = $validator->Validate(Request::Instance()->FormParams());
        $currentPassword = $dataAccessor->GetField('currentPassword');
        $newPassword = $dataAccessor->GetField('newPassword');
        $account = AccountService::Instance()->LoggedInAccount();
        if ($account === null) {
            throw new \RuntimeException(
                "You do not have permission to perform this action.",
                StatusCode::Unauthorized->value
            );
        }
        $securityService = SecurityService::Instance();
        if (!$securityService->VerifyPassword($currentPassword, $account->passwordHash)) {
            throw new \RuntimeException(
                "Current password is incorrect.",
                StatusCode::Forbidden->value
            );
        }
        $account->passwordHash = $securityService->HashPassword($newPassword);
        if (!$account->Save()) {
            throw new \RuntimeException(
                "Password change failed.",
                StatusCode::InternalServerError->value
            );
        }
        return null;
    }
}
