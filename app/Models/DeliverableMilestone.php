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

class DeliverableMilestone extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'deliverable_milestones';

    protected $fillable = [
        'tenant_id', 'deliverable_id', 'title', 'description',
        'order_position', 'status', 'due_date', 'estimated_hours',
        'completed_at', 'completed_by', 'depends_on_milestone_ids',
    ];

    protected $casts = [
        'deliverable_id' => 'integer',
        'order_position' => 'integer',
        'estimated_hours' => 'float',
        'completed_by' => 'integer',
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'depends_on_milestone_ids' => 'array',
    ];

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class);
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
