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
use \Harmonia\Services\SecurityService;
use \Harmonia\Session;
use \Harmonia\Systems\DatabaseSystem\Database;
use \Harmonia\Systems\ValidationSystem\DataAccessor;
use \Harmonia\Systems\ValidationSystem\Validator;
use \Peneus\Api\Actions\Account\LogoutAction;
use \Peneus\Model\Account;
use \Peneus\Model\AccountRole;
use \Peneus\Model\Role;
use \Peneus\Resource;
use \Peneus\Services\AccountService;

/**
 * Signs in a user with a Google account.
 */
class SignInWithGoogleAction extends Action
{
    /**
     * Executes the sign-in process by validating and decoding the user's
     * Google account details, checking for existing accounts, and creating
     * a new account if one does not already exist.
     *
     * The newly created account is saved with an empty password hash.
     *
     * @return array<string, string>
     *   An associative array with a 'redirectUrl' key indicating where the user
     *   should be redirected after a successful sign-in.
     * @throws \RuntimeException
     *   If the user is already logged in, if the credential field is missing or
     *   invalid, if the credentials cannot be decoded, if the claims cannot be
     *   validated, if the account cannot be saved, if the session cannot be
     *   established, or if the CSRF cookie cannot be deleted.
     */
    protected function onExecute(): mixed
    {
        // 1
        if ($this->isAccountLoggedIn()) {
            throw new \RuntimeException(
                'You are already logged in.',
                StatusCode::Conflict->value
            );
        }
        // 2
        $dataAccessor = $this->validateRequest();
        $credential = $dataAccessor->GetField('credential');
        // 3
        $claims = $this->decodeCredential($credential);
        if ($claims === null) {
            throw new \RuntimeException(
                'Invalid credential.',
                StatusCode::Unauthorized->value
            );
        }
        if (!$this->validateClaims($claims)) {
            throw new \RuntimeException(
                'Invalid claims.',
                StatusCode::Unauthorized->value
            );
        }
        // 4
        $result = Database::Instance()->WithTransaction(function() use($claims) {
            $account = $this->findOrCreateAccount($claims['email'], $claims['name']);
            if (!$account->Save()) {
                throw new \RuntimeException('Failed to save account.');
            }
            if (!$this->establishSessionIntegrity($account)) {
                throw new \RuntimeException('Failed to establish session integrity.');
            }
            $this->deleteCsrfCookie();
            return true;
        });
        // 5
        if ($result !== true) {
            $this->logOut();
            throw new \RuntimeException(
                'Login failed.',
                StatusCode::InternalServerError->value
            );
        }
        // 6
        return [
            'redirectUrl' => $this->homePageUrl()
        ];
    }

    /**
     * @return bool
     */
    protected function isAccountLoggedIn(): bool
    {
        return AccountService::Instance()->LoggedInAccount() !== null;
    }

    /**
     * @return DataAccessor
     * @throws \RuntimeException
     */
    protected function validateRequest(): DataAccessor
    {
        $validator = new Validator([
            'credential' => ['required', 'string', 'minLength:1']
        ]);
        return $validator->Validate(Request::Instance()->FormParams());
    }

    /**
     * @param string $credential
     * @return array<string, scalar>|null
     *
     * @todo After implementing `Harmonia\Http\Client`, update this code and
     * write proper tests.
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
        $decoded = \json_decode($response, true);
        if (!\is_array($decoded)) {
            return null;
        }
        return $decoded;
    }

    /**
     * @param array<string, scalar> $claims
     * @return bool
     */
    protected function validateClaims(array $claims): bool
    {
        return $this->validateIssuer($claims['iss'])
            && $this->validateAudience($claims['azp'], $claims['aud'])
            && $this->validateTimeWindow($claims['nbf'], $claims['exp'])
            && $this->validateEmailVerified($claims['email_verified']);
    }

    /**
     * @param string $issuer
     * @return bool
     */
    protected function validateIssuer(string $issuer): bool
    {
        return \in_array($issuer, [
            'https://accounts.google.com',
            'accounts.google.com'
        ], true);
    }

    /**
     * @param string $authorizedParty
     * @param string $audience
     * @return bool
     */
    protected function validateAudience(
        string $authorizedParty,
        string $audience
    ): bool
    {
        $clientId = Config::Instance()->Option('Google.Auth.ClientID');
        return $authorizedParty === $clientId && $audience === $clientId;
    }

    /**
     * @param string $notBefore
     * @param string $expiry
     * @param int|null $now
     * @return bool
     */
    protected function validateTimeWindow(
        string $notBefore,
        string $expiry,
        ?int $now = null
    ): bool
    {
        $now = $now ?? \time();
        return (int)$notBefore <= $now && $now <= (int)$expiry;
    }

    /**
     * @param string $emailVerified
     * @return bool
     */
    protected function validateEmailVerified(string $emailVerified): bool
    {
        return $emailVerified === 'true';
    }

    /**
     * @param string $email
     * @param string $displayName
     * @param \DateTime|null $timeLastLogin
     * @return Account
     */
    protected function findOrCreateAccount(
        string $email,
        string $displayName,
        ?\DateTime $timeLastLogin = null
    ): Account
    {
        $account = $this->findAccount($email);
        if ($account === null) {
            $account = $this->createAccount($email, $displayName);
        }
        $account->timeLastLogin = $timeLastLogin ?? new \DateTime();
        return $account;
    }

    /**
     * @param string $email
     * @return bool
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
     * @param \DateTime|null $timeActivated
     * @return Account
     */
    protected function createAccount(
        string $email,
        string $displayName,
        ?\DateTime $timeActivated = null
    ): Account
    {
        $account = new Account();
        $account->email = $email;
        $account->passwordHash = '';
        $account->displayName = $displayName;
        $account->timeActivated = $timeActivated ?? new \DateTime();
        $account->timeLastLogin = null;
        return $account;
    }

    /**
     * @param Account $account
     * @return bool
     *
     * @todo This method is identical to the one in `LoginAction`. Move it to
     * the `AccountService` class and use from both actions.
     *
     * @codeCoverageIgnore
     */
    protected function establishSessionIntegrity(Account $account): bool
    {
        $integrity = SecurityService::Instance()->GenerateCsrfToken();
        try {
            $session = Session::Instance()
                ->Start()
                ->Clear()
                ->RenewId()
                ->Set(AccountService::INTEGRITY_TOKEN_SESSION_KEY, $integrity->Token())
                ->Set(AccountService::ACCOUNT_ID_SESSION_KEY, $account->id);
            $role = $this->findAccountRole($account->id);
            if ($role !== null) {
                $session->Set(AccountService::ACCOUNT_ROLE_SESSION_KEY, $role->value);
            }
            $session->Close();
            CookieService::Instance()->SetCookie(
                AccountService::Instance()->IntegrityCookieName(),
                $integrity->CookieValue()
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param int $accountId
     * @return Role|null
     *
     * @todo This method is called only by `establishSessionIntegrity` and will
     * be moved to `AccountService` in the future.
     *
     * @codeCoverageIgnore
     */
    protected function findAccountRole(int $accountId): ?Role
    {
        $accountRole = AccountRole::FindFirst(
            condition: 'accountId = :accountId',
            bindings: ['accountId' => $accountId]
        );
        if ($accountRole === null) {
            return null;
        }
        return Role::tryFrom($accountRole->role);
    }

    /**
     * @return void
     * @throws \RuntimeException
     *
     * @codeCoverageIgnore
     */
    protected function deleteCsrfCookie(): void
    {
        CookieService::Instance()->DeleteCsrfCookie();
    }

    /**
     * @return void
     *
     * @codeCoverageIgnore
     */
    protected function logOut(): void
    {
        $action = new LogoutAction();
        $action->Execute();
    }

    /**
     * @return string
     *
     * @codeCoverageIgnore
     */
    protected function homePageUrl(): string
    {
        return Resource::Instance()->PageUrl('home');
    }
}
