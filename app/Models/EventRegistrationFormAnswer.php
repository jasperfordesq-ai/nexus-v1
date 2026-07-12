<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventRegistrationDataClassification;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventRegistrationFormAnswer extends Model
{
    use HasTenantScope;

    protected $table = 'event_registration_form_answers';

    protected $guarded = ['id'];

    protected $hidden = [
        'tenant_id',
        'answer_ciphertext',
        'displayed_text_hash',
    ];

    protected $casts = [
        'data_classification' => EventRegistrationDataClassification::class,
        'retention_due_at' => 'immutable_datetime',
        'consented_at' => 'immutable_datetime',
        'purged_at' => 'immutable_datetime',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(EventRegistrationFormSubmission::class, 'submission_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(EventRegistrationFormQuestion::class, 'question_id');
    }
}
