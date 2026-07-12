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

abstract class EventRecurrenceDefinitionBlueprintFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    protected function blueprintRules(): array
    {
        return [
            'effective_from_recurrence_id' => [
                'required', 'string', 'regex:/^\d{8}T\d{6}Z$/',
            ],
            'sections' => [
                'required',
                'array:agenda,ticket_types,registration,safety,staff',
                'min:1',
            ],
            'sections.agenda' => ['sometimes', 'boolean'],
            'sections.ticket_types' => ['sometimes', 'boolean'],
            'sections.registration' => ['sometimes', 'boolean'],
            'sections.safety' => ['sometimes', 'boolean'],
            // Staff propagation exists only behind this explicit opt-in key.
            'sections.staff' => ['sometimes', 'boolean'],
        ];
    }

    public function effectiveFromRecurrenceId(): string
    {
        return (string) $this->validated('effective_from_recurrence_id');
    }

    /** @return array<string,mixed> */
    public function selectedSections(): array
    {
        $sections = $this->validated('sections');

        return is_array($sections) ? $sections : [];
    }

    protected function failedValidation(Validator $validator): never
    {
        $field = array_key_first($validator->errors()->messages());
        $error = [
            'code' => 'EVENT_RECURRENCE_DEFINITION_VALIDATION_FAILED',
            'message' => __('api.validation_failed'),
        ];
        if (is_string($field) && $field !== '') {
            $error['field'] = $field;
        }
        $response = response()->json(['errors' => [$error]], 422);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Pragma', 'no-cache');
        $response->setVary(['Authorization', 'Cookie', 'X-Tenant-ID'], false);

        throw new HttpResponseException($response);
    }
}
