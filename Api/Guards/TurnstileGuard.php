<?php declare(strict_types=1);
/**
 * TurnstileGuard.php
 *
 * (C) 2026 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Guards;

use \Harmonia\Config;
use \Harmonia\Http\Client;
use \Harmonia\Http\Request;
use \Harmonia\Server;
use \Harmonia\Systems\ValidationSystem\Validator;

/**
 * A guard that verifies the Cloudflare Turnstile captcha provided in the
 * request.
 */
class TurnstileGuard implements IGuard
{
    private const CLOUDFLARE_TURNSTILE_VERIFY_URL =
        'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private readonly Client $client;
    private readonly Request $request;
    private readonly Config $config;
    private readonly Server $server;

    /**
     * Constructs a new instance by initializing dependencies.
     *
     * @param ?Client $client
     *   (Optional) The HTTP client to use. If not provided, a new default
     *   instance is created.
     */
    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client();
        $this->request = Request::Instance();
        $this->config = Config::Instance();
        $this->server = Server::Instance();
    }

    /**
     * Verifies the Turnstile captcha token.
     *
     * @return bool
     *   Returns `true` if the verification is successful, otherwise `false`.
     */
    public function Verify(): bool
    {
        $payload = $this->validatePayload();
        if ($payload === null) {
            return false;
        }
        return $this->verifyToken($payload->token);
    }

    #region protected ----------------------------------------------------------

    /**
     * @return object{
     *   token: string
     * } | null
     */
    protected function validatePayload(): ?\stdClass
    {
        $validator = new Validator([
            'cf-turnstile-response' => [
                'required',
                'string',
                'minLength:1',
                'maxLength:2048'
            ]
        ]);
        try {
            $da = $validator->Validate($this->request->FormParams());
        } catch (\Throwable $e) {
            return null;
        }
        return (object)[
            'token' => $da->GetField('cf-turnstile-response')
        ];
    }

    /**
     * @param string $token
     * @return bool
     */
    protected function verifyToken(string $token): bool
    {
        $result = $this->client
            ->Post()
            ->Url(self::CLOUDFLARE_TURNSTILE_VERIFY_URL)
            ->Body(\http_build_query([
                'secret' => $this->config->Option('Cloudflare.Turnstile.SecretKey'),
                'response' => $token,
                'remoteip' => $this->server->ClientAddress()
            ]))
            ->Send();
        if (!$result || 200 !== $this->client->StatusCode()) {
            return false;
        }
        $result = \json_decode($this->client->Body(), true);
        if (!\is_array($result) ||
            !isset($result['success']) ||
            !$result['success']
        ) {
            return false;
        }
        return true;
    }

    #endregion protected
}
