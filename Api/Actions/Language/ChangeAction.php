<?php declare(strict_types=1);
/**
 * ChangeAction.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Actions\Language;

use \Peneus\Api\Actions\Action;

use \Harmonia\Http\Request;
use \Harmonia\Systems\ValidationSystem\Validator;
use \Peneus\Services\LanguageService;

/**
 * Changes the current language by setting a cookie with the specified language
 * code.
 */
class ChangeAction extends Action
{
    /**
     * Validates the submitted language code and sets it in a cookie.
     *
     * @return mixed
     *   Always returns `null` if the operation is successful.
     * @throws \RuntimeException
     *   If the language code field is missing, or is not supported.
     */
    protected function onExecute(): mixed
    {
        $languageService = LanguageService::Instance();
        $validator = new Validator([
            'languageCode' => [
                'required',
                'string',
                fn(string $value): bool => $languageService->IsSupported($value)
            ]
        ]);
        $dataAccessor = $validator->Validate(Request::Instance()->FormParams());
        $languageCode = $dataAccessor->GetField('languageCode');
        $languageService->WriteToCookie($languageCode, strict: false);
        $languageService->DeleteCsrfCookie();
        return null;
    }
}
