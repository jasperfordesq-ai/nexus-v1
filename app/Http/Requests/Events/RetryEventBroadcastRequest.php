<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Requests\Events;

final class RetryEventBroadcastRequest extends EventBroadcastMutationRequest
{
    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            ...$this->idempotencyRules(),
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
