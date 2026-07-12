<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventRegistrationSubmissionStatus;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EventRegistrationFormSubmission extends Model
{
    use HasTenantScope;

    protected $table = 'event_registration_form_submissions';

    protected $guarded = ['id'];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'revision' => 'integer',
        'supersedes_submission_id' => 'integer',
        'lineage_root_submission_id' => 'integer',
        'attempt_number' => 'integer',
        'effective_slot' => 'integer',
        'status' => EventRegistrationSubmissionStatus::class,
        'submitted_at' => 'immutable_datetime',
        'withdrawn_at' => 'immutable_datetime',
        'anonymised_at' => 'immutable_datetime',
        'superseded_at' => 'immutable_datetime',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'registration_id');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(EventRegistrationFormVersion::class, 'form_version_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_submission_id');
    }

    public function lineageRoot(): BelongsTo
    {
        return $this->belongsTo(self::class, 'lineage_root_submission_id');
    }

    /** Answers are deliberately never eager-loaded by default. */
    public function answers(): HasMany
    {
        return $this->hasMany(EventRegistrationFormAnswer::class, 'submission_id');
    }
}
