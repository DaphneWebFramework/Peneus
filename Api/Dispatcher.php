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

use \Harmonia\Shutdown\IShutdownListener;

use \Harmonia\Config;
use \Harmonia\Http\Request;
use \Harmonia\Http\Response;
use \Harmonia\Http\StatusCode;
use \Harmonia\Shutdown\ShutdownHandler;

class Dispatcher implements IShutdownListener
{
    private Response $response;

    #region public -------------------------------------------------------------

    public function __construct()
    {
        $this->response = new Response();
        ShutdownHandler::Instance()->AddListener($this);
    }

    public function DispatchRequest(): void
    {
        $request = Request::Instance();
        $handlerName = $request->QueryParams()->Get('handler');
        if ($handlerName === null) {
            $this->response->SetStatusCode(StatusCode::BadRequest)
                           ->SetBody(self::errorJson('Handler not specified.'));
            return;
        }
        $actionName = $request->QueryParams()->Get('action');
        if ($actionName === null) {
            $this->response->SetStatusCode(StatusCode::BadRequest)
                           ->SetBody(self::errorJson('Action not specified.'));
            return;
        }
        $handler = HandlerRegistry::Instance()->FindHandler($handlerName);
        if ($handler === null) {
            $this->response->SetStatusCode(StatusCode::NotFound)
                           ->SetBody(self::errorJson("Handler not found: $handlerName"));
            return;
        }
        try {
            $result = $handler->HandleAction($actionName);
            if ($result === null) {
                $this->response->SetStatusCode(StatusCode::NoContent);
            } elseif ($result instanceof Response) {
                $this->response = $result;
            } else {
                $this->response->SetHeader('Content-Type', 'application/json')
                               ->SetBody(\json_encode($result));
            }
        } catch (\Exception $e) {
            $statusCode = StatusCode::tryFrom($e->getCode())
                          ?? StatusCode::InternalServerError;
            $this->response->SetStatusCode($statusCode)
                           ->SetHeader('Content-Type', 'application/json')
                           ->SetBody(self::errorJson($e->getMessage()));
        }
    }

    public function OnShutdown(?string $errorMessage): void
    {
        if ($errorMessage !== null) {
            if (!Config::Instance()->Option('IsDebug')) {
                $errorMessage = 'An unexpected error occurred.';
            }
            $this->response->SetStatusCode(StatusCode::InternalServerError)
                           ->SetHeader('Content-Type', 'application/json')
                           ->SetBody(self::errorJson($errorMessage));
        }
        $this->response->Send();
    }

    #endregion public

    #region private ------------------------------------------------------------

    private static function errorJson(string $errorMessage): string
    {
        return \json_encode(['error' => $errorMessage]);
    }

    #endregion private
}
