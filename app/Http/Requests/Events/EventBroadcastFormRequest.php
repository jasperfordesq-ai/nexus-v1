<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Requests\Events;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/** Shared fail-closed JSON boundary for organizer communications. */
abstract class EventBroadcastFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        // EventBroadcastService authorizes against tenant-scoped persisted facts.
        return true;
    }

    protected function failedValidation(Validator $validator): never
    {
        $field = array_key_first($validator->errors()->messages());
        $error = [
            'code' => 'EVENT_BROADCAST_VALIDATION_FAILED',
            'message' => __('api.validation_failed'),
        ];
        if (is_string($field) && $field !== '') {
            $error['field'] = $field;
        }

        throw new HttpResponseException(response()->json(['errors' => [$error]], 422));
    }
}
