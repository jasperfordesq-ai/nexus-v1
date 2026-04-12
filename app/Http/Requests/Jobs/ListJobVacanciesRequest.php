<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Requests\Jobs;

use Illuminate\Foundation\Http\FormRequest;

class ListJobVacanciesRequest extends FormRequest
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
            'status' => ['nullable', 'in:open,closed,filled,draft,expired,pending_review'],
            'type' => ['nullable', 'in:paid,volunteer,internship,timebank'],
            'commitment' => ['nullable', 'in:full_time,part_time,one_off,flexible'],
            'category' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'organization_id' => ['nullable', 'integer', 'min:1'],
            'exclude' => ['nullable', 'integer', 'min:1'],
            'featured' => ['nullable', 'boolean'],
            'is_remote' => ['nullable', 'boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'min:0.1', 'max:500'],
            'sort' => ['nullable', 'in:newest,deadline,salary_desc'],
            'cursor' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
