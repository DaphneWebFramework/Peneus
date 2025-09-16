<?php declare(strict_types=1);
/**
 * ChangeDisplayNameAction.php
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
use \Harmonia\Systems\ValidationSystem\Validator;
use \Peneus\Services\AccountService;

/**
 * Changes the display name of the currently logged-in account.
 */
class ChangeDisplayNameAction extends Action
{
    /**
     * Executes the display name change process by validating the new display
     * name, retrieving the currently logged-in account, and saving the updated
     * value.
     *
     * @return mixed
     *   Always returns `null` if the operation is successful.
     * @throws \RuntimeException
     *   If the display name field is missing or does not match the required
     *   pattern, if the user is not logged in, or if the account cannot be
     *   saved.
     *
     * @todo Define custom error messages for each validation rule.
     */
    protected function onExecute(): mixed
    {
        $validator = new Validator([
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
        $displayName = $dataAccessor->GetField('displayName');
        $account = AccountService::Instance()->LoggedInAccount();
        if ($account === null) {
            throw new \RuntimeException(
                "You do not have permission to perform this action.",
                StatusCode::Unauthorized->value
            );
        }
        $account->displayName = $displayName;
        if (!$account->Save()) {
            throw new \RuntimeException(
                "Display name change failed.",
                StatusCode::InternalServerError->value
            );
        }
        return null;
    }
}
