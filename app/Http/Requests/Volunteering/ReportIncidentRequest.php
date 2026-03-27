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
            'incident_type' => ['required', 'string', 'in:safety,harassment,injury,misconduct,safeguarding,discrimination,property_damage,other'],
            'description' => ['required', 'string', 'min:20'],
            'severity' => ['required', 'in:low,medium,high,critical'],
            'location' => ['nullable', 'string'],
        ];
    }
}
