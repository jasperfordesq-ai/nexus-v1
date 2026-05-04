<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Requests\Volunteering;

use Illuminate\Foundation\Http\FormRequest;

class ReportIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'incident_type' => ['nullable', 'string', 'in:concern,allegation,disclosure,near_miss,other'],
            'description' => ['required', 'string', 'min:20'],
            'severity' => ['required', 'in:low,medium,high,critical'],
            'category' => ['nullable', 'string', 'max:100'],
            'organization_id' => ['nullable', 'integer', 'min:1'],
            'opportunity_id' => ['nullable', 'integer', 'min:1'],
            'shift_id' => ['nullable', 'integer', 'min:1'],
            'involved_user_id' => ['nullable', 'integer', 'min:1'],
            'subject_user_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
