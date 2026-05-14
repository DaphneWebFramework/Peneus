<?php declare(strict_types=1);
/**
 * SignInWithGoogleAction.php
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
use \Harmonia\Http\Client;
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\CookieService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\ValidationSystem\Validator;
use \Peneus\Api\Traits\ActivationHooksTriggerer;
use \Peneus\Api\Traits\NotLoggedInEnsurer;
use \Peneus\Model\Account;
use \Peneus\Model\Traits\AccountFinder;
use \Peneus\Resource;
use \Peneus\Services\AccountService;

/**
 * Authenticates a user with Google Sign-In credentials.
 *
 * If no matching account exists, it first creates a new account and then
 * proceeds with the sign-in. The account is created with an empty password
 * hash. A persistent login session is always established for the user.
 */
class SignInWithGoogleAction extends Action
{
    use AccountFinder;
    use NotLoggedInEnsurer;
    use ActivationHooksTriggerer;

    private const GOOGLE_OAUTH2_CLIENT_ID_PATTERN =
        '/^[0-9a-zA-Z\-]+\.apps\.googleusercontent\.com$/';
    private const GOOGLE_OAUTH2_TOKENINFO_URL =
        'https://oauth2.googleapis.com/tokeninfo?id_token=%s';

    private readonly Client $client;
    private readonly Request $request;
    private readonly Database $database;
    private readonly Config $config;
    private readonly Resource $resource;
    private readonly AccountService $accountService;
    private readonly CookieService $cookieService;

    /**
     * Constructs a new instance by initializing dependencies.
     *
     * @param ?Client $client
     *   (Optional) The HTTP client to use. If not provided, a new default
     *   instance is created.
     */
    public function __construct(?Client $client = null)
    {
        parent::__construct();
        $this->client = $client ?? new Client();
        $this->request = Request::Instance();
        $this->database = Database::Instance();
        $this->config = Config::Instance();
        $this->resource = Resource::Instance();
        $this->accountService = AccountService::Instance();
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
        $this->ensureNotLoggedIn();
        $payload = $this->validatePayload();
        $profile = $this->decodeProfile($payload->credential);
        $account = $this->findOrMakeAccount($profile); // never fails
        $this->doTransaction($account);
        $this->cookieService->DeleteCsrfCookie();
        return $this->composeResult();
    }

    /**
     * @return object{
     *   credential: string
     * }
     * @throws \RuntimeException
     */
    protected function validatePayload(): \stdClass
    {
        $validator = new Validator([
            'credential' => [
                'required',
                'string',
                'minLength:1'
            ]
        ]);
        $da = $validator->Validate($this->request->FormParams());
        return (object)[
            'credential' => $da->GetField('credential')
        ];
    }

    /**
     * @param string $credential
     * @return object{
     *   email       : string,
     *   displayName : string
     * }
     * @throws \RuntimeException
     */
    protected function decodeProfile(string $credential): \stdClass
    {
        $claims = $this->decodeCredential($credential);
        if ($claims === null) {
            throw new \RuntimeException(
                "Invalid credential.",
                StatusCode::Unauthorized->value
            );
        }
        $clientId = $this->validateClientId();
        return $this->validateClaims($claims, $clientId);
    }

    /**
     * @param string $credential
     * @return array<string, mixed>|null
     */
    protected function decodeCredential(string $credential): ?array
    {
        $url = sprintf(self::GOOGLE_OAUTH2_TOKENINFO_URL, $credential);
        if (!$this->client->Url($url)->Send()) {
            return null;
        }
        if (200 !== $this->client->StatusCode()) {
            return null;
        }
        $claims = \json_decode($this->client->Body(), true);
        if (!\is_array($claims)) {
            return null;
        }
        return $claims;
    }

    /**
     * @return string
     * @throws \RuntimeException
     */
    protected function validateClientId(): string
    {
        $clientId = $this->config->Option('Google.OAuth2.ClientID');
        if (!\is_string($clientId) ||
            !\preg_match(self::GOOGLE_OAUTH2_CLIENT_ID_PATTERN, $clientId)
        ) {
            throw new \RuntimeException("Missing or invalid Google OAuth 2.0 client ID.");
        }
        return $clientId;
    }

    /**
     * @param array<string, mixed> $claims
     * @param string $clientId
     * @return object{
     *   email       : string,
     *   displayName : string
     * }
     * @throws \RuntimeException
     */
    protected function validateClaims(array $claims, string $clientId): \stdClass
    {
        $validator = new Validator([
            'iss' => ['required', 'string', fn($value) => \in_array($value, [
                'https://accounts.google.com', 'accounts.google.com'], true)],
            'aud' => ['required', 'string', fn($value) => $value === $clientId],
            'sub' => ['required', 'string', 'minLength:1', 'maxLength:255'],
            'exp' => ['required', 'integer', fn($value) => \time() <= (int)$value],
            'email_verified' => ['required', 'string', fn($value) => $value === 'true'],
            'email' => ['required', 'email'],
            'name' => ['required', 'string']
        ]);
        $da = $validator->Validate($claims);
        $email = $da->GetField('email');
        $displayName = $this->normalizeDisplayName(
            $da->GetField('name'),
            $email,
            $da->GetField('sub')
        );
        return (object)[
            'email'       => $email,
            'displayName' => $displayName
        ];
    }

    /**
     * @param string $name
     * @param string $email
     * @param string $sub
     * @return string
     */
    protected function normalizeDisplayName(
        string $name,
        string $email,
        string $sub
    ): string
    {
        // 1
        $name = \trim($name);
        if (\preg_match(AccountService::DISPLAY_NAME_PATTERN, $name)) {
            return $name;
        }
        // 2
        $name = \strstr($email, '@', true);
        if ($name !== false &&
            \preg_match(AccountService::DISPLAY_NAME_PATTERN, $name)
        ) {
            return $name;
        }
        // 3
        return 'User_' . substr($sub, 0, 45);
    }

    /**
     * @param object{
     *   email       : string,
     *   displayName : string
     * } $profile
     * @return Account
     */
    protected function findOrMakeAccount(\stdClass $profile): Account
    {
        $account = $this->tryFindAccountByEmail($profile->email);
        if ($account === null) {
            $account = $this->makeAccount($profile);
        }
        return $account;
    }

    /**
     * @param object{
     *   email       : string,
     *   displayName : string
     * } $profile
     * @return Account
     */
    protected function makeAccount(\stdClass $profile): Account
    {
        return new Account($profile);
    }

    /**
     * @param Account $account
     * @throws \RuntimeException
     */
    protected function doTransaction(Account $account): void
    {
        try {
            $this->database->WithTransaction(function() use($account) {
                $isRegistering = ($account->id === 0); // before save
                $account->timeLastLogin = new \DateTime(); // now
                if (!$account->Save()) {
                    throw new \RuntimeException("Failed to save account.");
                }
                if ($isRegistering) {
                    $this->triggerActivationHooks($account);
                }
                $this->accountService->CreateSession($account->id, true); // always persistent
            });
        } catch (\Throwable $e) {
            $this->tryLogOut();
            throw $e;
        }
    }

    /**
     */
    protected function tryLogOut(): void
    {
        try {
            $this->accountService->DeleteSession();
        } catch (\Throwable) {
            // Best effort: Suppress exceptions
        }
    }

    /**
     * @return array{
     *   redirectUrl: CUrl
     * }
     */
    protected function composeResult(): array
    {
        return [
            'redirectUrl' => $this->resource->PageUrl('home')
        ];
    }
}
