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
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\ValidationSystem\Validator;
use \Peneus\Api\Traits\AccountFinder;
use \Peneus\Api\Traits\LoggedInEnsurer;
use \Peneus\Model\Account;
use \Peneus\Services\AccountService;

/**
 * Changes the display name of the currently logged-in account.
 */
class ChangeDisplayNameAction extends Action
{
    use LoggedInEnsurer;
    use AccountFinder;

    private readonly Request $request;
    private readonly Database $database;

    /**
     * Constructs a new instance by initializing dependencies.
     */
    public function __construct()
    {
        parent::__construct();
        $this->request = Request::Instance();
        $this->database = Database::Instance();
    }

    /**
     * @return null
     * @throws \RuntimeException
     */
    protected function onExecute(): mixed
    {
        $accountView = $this->ensureLoggedIn();
        $account = $this->findAccount($accountView->id);
        $payload = $this->validatePayload();
        $this->doTransaction($account, $payload->displayName);
        return null;
    }

    /**
     * @return object{
     *   displayName: string
     * }
     * @throws \RuntimeException
     */
    protected function validatePayload(): \stdClass
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
        $da = $validator->Validate($this->request->FormParams());
        return (object)[
            'displayName' => $da->GetField('displayName')
        ];
    }

    /**
     * @param Account $account
     * @param string $displayName
     * @throws \RuntimeException
     */
    protected function doTransaction(Account $account, string $displayName): void
    {
        $this->database->WithTransaction(function() use($account, $displayName) {
            $account->displayName = $displayName;
            if (!$account->Save()) {
                throw new \RuntimeException("Failed to save account.");
            }
        });
    }
}
