<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Requests\Events;

use App\Exceptions\EventRecurrenceRevisionException;

final class CommitEventRecurrenceRevisionRequest extends EventRecurrenceRevisionFormRequest
{
    /** @return array<string,mixed> */
    public function rules(): array
    {
        return array_merge($this->patchRules(), [
            'preview_token' => ['required', 'string', 'max:8192'],
        ]);
    }

    public function previewToken(): string
    {
        return (string) $this->validated('preview_token');
    }

    public function idempotencyKey(): string
    {
        $key = $this->header('Idempotency-Key');
        if (! is_string($key)
            || trim($key) === ''
            || mb_strlen(trim($key)) > 191) {
            throw new EventRecurrenceRevisionException(
                'event_recurrence_revision_idempotency_invalid',
            );
        }

        return trim($key);
    }
}
