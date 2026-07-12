<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Requests\Events;

class PreviewEventBroadcastRequest extends EventBroadcastFormRequest
{
    /** @return array<string,mixed> */
    public function rules(): array
    {
        return self::audienceRules();
    }

    /** @return array<string,mixed> */
    public static function audienceRules(): array
    {
        return [
            'variant' => ['required', 'string', 'in:announcement,follow_up,review_request'],
            'segments' => ['required', 'array', 'min:1', 'max:4'],
            'segments.*' => ['required', 'string', 'distinct', 'in:registration_confirmed,waitlist_active,attendance_attended,attendance_no_show'],
            'channels' => ['required', 'array', 'min:1', 'max:3'],
            'channels.*' => ['required', 'string', 'distinct', 'in:email,in_app,push'],
        ];
    }
}
