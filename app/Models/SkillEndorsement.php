<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillEndorsement extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'skill_endorsements';

    // The skill_endorsements table has only created_at (see the creating
    // migration + schema dump), so Eloquent must not manage updated_at — writing
    // it 500s every endorsement. Matches the created_at-only pattern used by many
    // models here (Category, ActivityLog, GoalCheckin, …).
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'endorser_id',
        'endorsed_id',
        'skill_id',
        'skill_name',
        'comment',
    ];

    protected $casts = [
        'endorser_id' => 'integer',
        'endorsed_id' => 'integer',
        'skill_id' => 'integer',
    ];

    public function endorser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'endorser_id');
    }

    public function endorsed(): BelongsTo
    {
        return $this->belongsTo(User::class, 'endorsed_id');
    }
}
