<?php declare(strict_types=1);
/**
 * ActivateAction.php
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
use \Harmonia\Services\CookieService;
use \Harmonia\Services\SecurityService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\ValidationSystem\Validator;
use \Peneus\Api\Traits\ActivationHooksTriggerer;
use \Peneus\Api\Traits\NotRegisteredEnsurer;
use \Peneus\Api\Traits\PendingAccountFinder;
use \Peneus\Model\Account;
use \Peneus\Model\PendingAccount;
use \Peneus\Resource;

/**
 * Handles account activation via activation code.
 */
class ActivateAction extends Action
{
    use PendingAccountFinder;
    use NotRegisteredEnsurer;
    use ActivationHooksTriggerer;

    private readonly Request $request;
    private readonly Database $database;
    private readonly Resource $resource;
    private readonly CookieService $cookieService;

    /**
     * Constructs a new instance by initializing dependencies.
     */
    public function __construct()
    {
        parent::__construct();
        $this->request = Request::Instance();
        $this->database = Database::Instance();
        $this->resource = Resource::Instance();
        $this->cookieService = CookieService::Instance();
    }

    /**
     * @return array{
     *   redirectUrl: CUrl
     * }
     * @throws \RuntimeException
     */
    protected function onExecute(): mixed
    {
        $payload = $this->validatePayload();
        $pendingAccount = $this->findPendingAccount($payload->activationCode);
        $this->ensureNotRegistered($pendingAccount->email);
        $this->doTransaction($pendingAccount);
        $this->cookieService->DeleteCsrfCookie();
        return $this->composeResult();
    }

    /**
     * @return object{
     *   activationCode: string
     * }
     * @throws \RuntimeException
     */
    protected function validatePayload(): \stdClass
    {
        $validator = new Validator([
            'activationCode' => [
                'required',
                'regex:' . SecurityService::TOKEN_DEFAULT_PATTERN
            ]
        ], [
            'activationCode.required' => "Activation code is required.",
            'activationCode.regex'    => "Activation code format is invalid."
        ]);
        $da = $validator->Validate($this->request->FormParams());
        return (object)[
            'activationCode' => $da->GetField('activationCode')
        ];
    }

    /**
     * @param PendingAccount $pendingAccount
     * @throws \RuntimeException
     */
    protected function doTransaction(PendingAccount $pendingAccount): void
    {
        $this->database->WithTransaction(function() use($pendingAccount) {
            $account = $this->makeAccount($pendingAccount);
            if (!$account->Save()) {
                throw new \RuntimeException("Failed to save account.");
            }
            if (!$pendingAccount->Delete()) {
                throw new \RuntimeException("Failed to delete pending account.");
            }
            $this->triggerActivationHooks($account);
        });
    }

    /**
     * @param PendingAccount $pendingAccount
     * @return Account
     */
    protected function makeAccount(PendingAccount $pendingAccount): Account
    {
        return new Account([
            'email'        => $pendingAccount->email,
            'passwordHash' => $pendingAccount->passwordHash,
            'displayName'  => $pendingAccount->displayName
        ]);
    }

    /**
     * @return array{
     *   redirectUrl: CUrl
     * }
     */
    protected function composeResult(): array
    {
        return [
            'redirectUrl' => $this->resource->LoginPageUrl('home')
        ];
    }
}
