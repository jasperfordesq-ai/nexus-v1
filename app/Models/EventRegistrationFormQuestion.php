<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventRegistrationDataClassification;
use App\Enums\EventRegistrationQuestionType;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventRegistrationFormQuestion extends Model
{
    use HasTenantScope;

    protected $table = 'event_registration_form_questions';

    protected $guarded = ['id'];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'position' => 'integer',
        'question_type' => EventRegistrationQuestionType::class,
        'is_required' => 'boolean',
        'data_classification' => EventRegistrationDataClassification::class,
        'retention_days' => 'integer',
        'choice_options' => 'array',
        'validation_rules' => 'array',
        'visibility_rules' => 'array',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(EventRegistrationFormVersion::class, 'form_version_id');
    }
}
