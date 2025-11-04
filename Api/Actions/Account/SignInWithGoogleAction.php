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
use \Harmonia\Http\Request;
use \Harmonia\Http\StatusCode;
use \Harmonia\Services\CookieService;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\ValidationSystem\Validator;
use \Peneus\Model\Account;
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
    private const GOOGLE_OAUTH2_CLIENT_ID_PATTERN =
        '/^[0-9a-zA-Z\-]+\.apps\.googleusercontent\.com$/';

    private readonly Request $request;
    private readonly Database $database;
    private readonly Config $config;
    private readonly Resource $resource;
    private readonly AccountService $accountService;
    private readonly CookieService $cookieService;

    /**
     * Constructs a new instance by initializing dependencies.
     */
    public function __construct()
    {
        parent::__construct();
        $this->request = Request::Instance();
        $this->database = Database::Instance();
        $this->config = Config::Instance();
        $this->resource = Resource::Instance();
        $this->accountService = AccountService::Instance();
        $this->cookieService = CookieService::Instance();
    }

    /**
     * @return array{redirectUrl: string}
     * @throws \RuntimeException
     */
    protected function onExecute(): mixed
    {
        // 1
        $this->ensureNotLoggedIn();
        // 2
        $data = $this->validateRequest();
        // 3
        $data = $this->decodeAndValidateCredential($data->credential);
        // 4
        $account = $this->findOrConstructAccount(
            $data->email,
            $data->displayName
        );
        // 5
        try {
            $this->database->WithTransaction(fn() =>
                $this->doLogIn($account)
            );
        } catch (\Throwable $e) {
            $this->logOut();
            throw new \RuntimeException(
                "Login failed.",
                StatusCode::InternalServerError->value,
                $e
            );
        }
        // 6
        $this->cookieService->DeleteCsrfCookie();
        return [
            'redirectUrl' => $this->resource->PageUrl('home')
        ];
    }

    /**
     * @throws \RuntimeException
     */
    protected function ensureNotLoggedIn(): void
    {
        if (null !== $this->accountService->LoggedInAccount()) {
            throw new \RuntimeException(
                "You are already logged in.",
                StatusCode::Conflict->value
            );
        }
    }

    /**
     * @return object{credential: string}
     * @throws \RuntimeException
     */
    protected function validateRequest(): \stdClass
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
     * @return object{email: string, displayName: string}
     * @throws \RuntimeException
     */
    protected function decodeAndValidateCredential(string $credential): \stdClass
    {
        $claims = $this->decodeCredential($credential);
        if ($claims === null) {
            throw new \RuntimeException(
                "Invalid credential.",
                StatusCode::Unauthorized->value
            );
        }
        try {
            $data = $this->validateClaims($claims);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Invalid claims.",
                StatusCode::Unauthorized->value,
                $e
            );
        }
        return $data;
    }

    /**
     * @param string $credential
     * @return ?array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    protected function decodeCredential(string $credential): ?array
    {
        $credential = \urlencode($credential);
        $url = "https://oauth2.googleapis.com/tokeninfo?id_token={$credential}";
        $curl = \curl_init($url);
        if ($curl === false) {
            return null;
        }
        \curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = \curl_exec($curl);
        $statusCode = \curl_getinfo($curl, CURLINFO_HTTP_CODE);
        \curl_close($curl);
        if ($response === false || $statusCode !== 200) {
            return null;
        }
        $claims = \json_decode($response, true);
        if (!\is_array($claims)) {
            return null;
        }
        return $claims;
    }

    /**
     * @param array<string, mixed> $claims
     * @return object{email: string, displayName: string}
     * @throws \RuntimeException
     */
    protected function validateClaims(array $claims): \stdClass
    {
        // 1
        $clientId = $this->config->Option('Google.OAuth2.ClientID');
        if (!\is_string($clientId) ||
            !\preg_match(self::GOOGLE_OAUTH2_CLIENT_ID_PATTERN, $clientId)
        ) {
            throw new \RuntimeException("Missing or invalid Google OAuth 2.0 client ID.");
        }
        // 2
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
        // 3
        $email = $da->GetField('email');
        $displayName = $this->normalizeDisplayName(
            $da->GetField('name'),
            $email,
            $da->GetField('sub')
        );
        // 4
        return (object)[
            'email' => $email,
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
     * @param string $email
     * @param string $displayName
     * @return Account
     */
    protected function findOrConstructAccount(
        string $email,
        string $displayName
    ): Account
    {
        $account = $this->findAccount($email);
        if ($account === null) {
            $account = $this->constructAccount($email, $displayName);
        }
        return $account;
    }

    /**
     * @param string $email
     * @return ?Account
     */
    protected function findAccount(string $email): ?Account
    {
        return Account::FindFirst(
            condition: 'email = :email',
            bindings: ['email' => $email]
        );
    }

    /**
     * @param string $email
     * @param string $displayName
     * @return Account
     */
    protected function constructAccount(
        string $email,
        string $displayName
    ): Account
    {
        $account = new Account();
        $account->email = $email;
        $account->passwordHash = '';
        $account->displayName = $displayName;
        $account->timeActivated = new \DateTime(); // now
        $account->timeLastLogin = null;
        return $account;
    }

    /**
     * @param Account $account
     * @throws \RuntimeException
     */
    protected function doLogIn(Account $account): void
    {
        $account->timeLastLogin = new \DateTime(); // now
        if (!$account->Save()) {
            throw new \RuntimeException("Failed to save account.");
        }
        // Google sign-in is always persistent.
        $this->accountService->CreateSession($account->id, true);
    }

    /**
     * @throws \RuntimeException
     */
    protected function logOut(): void
    {
        $this->accountService->DeleteSession();
    }
}
