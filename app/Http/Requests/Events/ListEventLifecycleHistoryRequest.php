<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Requests\Events;

use App\Exceptions\EventLifecycleHistoryException;
use App\Support\Events\EventLifecycleHistoryCursor;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/** Strict private-query contract for Event lifecycle history. */
final class ListEventLifecycleHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Persisted tenant and EventPolicy authorization belongs in the query service.
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'cursor' => [
                'sometimes',
                'string',
                'max:256',
                function (string $attribute, mixed $value, Closure $fail): void {
                    try {
                        EventLifecycleHistoryCursor::decode(
                            is_string($value) ? $value : '',
                            (int) $this->route('id'),
                        );
                    } catch (EventLifecycleHistoryException) {
                        $fail(__('api.invalid_cursor'));
                    }
                },
            ],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        $field = array_key_first($validator->errors()->messages());
        $error = [
            'code' => 'EVENT_LIFECYCLE_HISTORY_VALIDATION_FAILED',
            'message' => __('api.validation_failed'),
        ];
        if (is_string($field) && $field !== '') {
            $error['field'] = $field;
        }

        $response = response()->json([
            'errors' => [$error],
        ], 422);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Pragma', 'no-cache');
        $response->setVary(['Authorization', 'Cookie', 'X-Tenant-ID'], false);

        throw new HttpResponseException($response);
    }
}
