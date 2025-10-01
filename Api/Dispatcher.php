<?php declare(strict_types=1);
/**
 * Dispatcher.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api;

use \Harmonia\Systems\ShutdownSystem\IShutdownListener;

use \Harmonia\Config;
use \Harmonia\Http\Request;
use \Harmonia\Http\Response;
use \Harmonia\Http\StatusCode;
use \Harmonia\Systems\ShutdownSystem\ShutdownHandler;

/**
 * Dispatches API requests to the appropriate handler.
 */
class Dispatcher implements IShutdownListener
{
    private Response $response;

    #region public -------------------------------------------------------------

    /**
     * Constructs a new instance.
     */
    public function __construct()
    {
        $this->response = new Response();
        ShutdownHandler::Instance()->AddListener($this);
    }

    /**
     * Dispatches the request to the appropriate handler.
     *
     * The request must include `handler` and `action` query parameters. If a
     * handler cannot be found, the action is unknown, or another error occurs
     * during execution, the response body will contain a JSON-formatted string
     * with an `error` property. Otherwise, the response body will contain the
     * action result as a JSON-formatted string.
     *
     * This method does not send the response. The final response is sent
     * in `OnShutdown`.
     *
     * @see OnShutdown
     */
    public function DispatchRequest(): void
    {
        $queryParams = Request::Instance()->QueryParams();

        $handlerName = $queryParams->Get('handler');
        if ($handlerName === null) {
            $this->response
                ->SetStatusCode(StatusCode::BadRequest)
                ->SetBody(self::toMessageJson("Handler not specified."));
            return;
        }

        $actionName = $queryParams->Get('action');
        if ($actionName === null) {
            $this->response
                ->SetStatusCode(StatusCode::BadRequest)
                ->SetBody(self::toMessageJson("Action not specified."));
            return;
        }

        $handler = HandlerRegistry::Instance()->FindHandler($handlerName);
        if ($handler === null) {
            $this->response
                ->SetStatusCode(StatusCode::NotFound)
                ->SetBody(self::toMessageJson("Handler not found: {$handlerName}"));
            return;
        }

        try {
            $result = $handler->HandleAction($actionName);
            if ($result === null) {
                $this->response->SetStatusCode(StatusCode::NoContent);
            } elseif ($result instanceof Response) {
                $this->response = $result;
            } else {
                $this->response
                    ->SetHeader('Content-Type', 'application/json')
                    ->SetBody(self::toJson($result));
            }
        } catch (\Exception $e) {
            $statusCode = StatusCode::tryFrom($e->getCode())
                ?? StatusCode::InternalServerError;
            $this->response
                ->SetStatusCode($statusCode)
                ->SetHeader('Content-Type', 'application/json')
                ->SetBody(self::toMessageJson($e->getMessage()));
        }
    }

    /**
     * Handles system shutdown events and ensures a response is sent.
     *
     * If an error occurs during execution, the response collected by
     * `DispatchRequest` is replaced with an error response. Otherwise,
     * the existing response is sent as is.
     *
     * @param ?string $errorMessage
     *   The error message if an error occurred, or `null` otherwise.
     *
     * @see DispatchRequest
     */
    public function OnShutdown(?string $errorMessage): void
    {
        if ($errorMessage !== null) {
            if (!Config::Instance()->Option('IsDebug')) {
                $errorMessage = "An unexpected error occurred.";
            }
            $this->response
                ->SetStatusCode(StatusCode::InternalServerError)
                ->SetHeader('Content-Type', 'application/json')
                ->SetBody(self::toMessageJson($errorMessage));
        }
        $this->response->Send();
    }

    #endregion public

    #region private ------------------------------------------------------------

    /**
     * Encodes a message into a JSON string using the `message` key.
     *
     * @param string $message
     *   The message to encode.
     * @return string
     *   A JSON string like: `{"message": "<your message>"}`
     */
    private static function toMessageJson(string $message): string
    {
        return self::toJson(['message' => $message]);
    }

    /**
     * Encodes the given data to a JSON string.
     *
     * @param mixed $data
     *   The data to encode as JSON.
     * @return string
     *   A JSON-encoded string.
     */
    private static function toJson(mixed $data): string
    {
        return \json_encode($data, \JSON_UNESCAPED_UNICODE);
    }

    #endregion private
}
