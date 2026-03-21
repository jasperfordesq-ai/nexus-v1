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
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deliverable extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'deliverables';

    protected $fillable = [
        'tenant_id', 'owner_id', 'title', 'description', 'category',
        'priority', 'assigned_to', 'assigned_group_id', 'start_date',
        'due_date', 'status', 'progress_percentage', 'estimated_hours',
        'actual_hours', 'parent_deliverable_id', 'tags', 'custom_fields',
        'delivery_confidence', 'risk_level', 'risk_notes',
        'blocking_deliverable_ids', 'depends_on_deliverable_ids',
        'watchers', 'collaborators', 'attachment_urls', 'external_links',
        'completed_at',
    ];

    protected $casts = [
        'owner_id' => 'integer',
        'assigned_to' => 'integer',
        'assigned_group_id' => 'integer',
        'parent_deliverable_id' => 'integer',
        'progress_percentage' => 'float',
        'estimated_hours' => 'float',
        'actual_hours' => 'float',
        'start_date' => 'date',
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'tags' => 'array',
        'custom_fields' => 'array',
        'blocking_deliverable_ids' => 'array',
        'depends_on_deliverable_ids' => 'array',
        'watchers' => 'array',
        'collaborators' => 'array',
        'attachment_urls' => 'array',
        'external_links' => 'array',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'assigned_group_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_deliverable_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_deliverable_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(DeliverableComment::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(DeliverableMilestone::class);
    }
}
