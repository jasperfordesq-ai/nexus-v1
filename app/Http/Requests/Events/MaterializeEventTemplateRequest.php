<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Requests\Events;

final class MaterializeEventTemplateRequest extends PreviewEventTemplateMaterializationRequest
{
    protected function prepareForValidation(): void
    {
        $header = $this->header('Idempotency-Key');
        $body = $this->input('idempotency_key');
        $header = is_string($header) ? trim($header) : null;
        $body = is_string($body) ? trim($body) : null;

        $this->merge([
            'idempotency_key' => $header ?? $body,
            '_idempotency_conflict' => $header !== null
                && $body !== null
                && ! hash_equals($header, $body),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'idempotency_key' => ['required', 'string', 'max:512'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            if ($this->boolean('_idempotency_conflict')) {
                $validator->errors()->add('idempotency_key', __('api.validation_failed'));
            }
        });
    }

    public function idempotencyKey(): string
    {
        return trim((string) $this->validated('idempotency_key'));
    }
}
