<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Requests\Events;

class PreviewEventTemplateMaterializationRequest extends EventTemplateFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'template_version' => ['required', 'integer', 'min:1'],
            'start_time' => ['required', 'string', 'max:64'],
            'end_time' => ['nullable', 'string', 'max:64'],
            'overrides' => [
                'sometimes',
                'array:title,description,category_id,group_id,location,latitude,longitude,max_attendees,is_online,allow_remote_attendance,timezone,all_day',
            ],
            'overrides.title' => ['sometimes', 'string', 'max:255'],
            'overrides.description' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'overrides.category_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'overrides.group_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'overrides.location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'overrides.latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'overrides.longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'overrides.max_attendees' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'overrides.is_online' => ['sometimes', 'boolean'],
            'overrides.allow_remote_attendance' => ['sometimes', 'boolean'],
            'overrides.timezone' => ['sometimes', 'string', 'max:64'],
            'overrides.all_day' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function materializationInput(): array
    {
        $validated = $this->validated();

        return [
            'template_version' => (int) $validated['template_version'],
            'start_time' => (string) $validated['start_time'],
            'end_time' => isset($validated['end_time'])
                ? (string) $validated['end_time']
                : null,
            'overrides' => is_array($validated['overrides'] ?? null)
                ? $validated['overrides']
                : [],
        ];
    }
}
