<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Newsletter extends Model
{
    use HasTenantScope;

    protected $table = 'newsletters';

    protected $fillable = [
        'tenant_id', 'subject', 'preview_text', 'content', 'status',
        'scheduled_at', 'sent_at', 'created_by', 'total_recipients',
        'total_sent', 'total_failed', 'total_opens', 'unique_opens',
        'total_clicks', 'unique_clicks', 'target_audience', 'segment_id',
        'is_recurring', 'recurring_frequency', 'recurring_day',
        'recurring_day_of_month', 'recurring_time', 'recurring_end_date',
        'last_recurring_sent', 'template_id', 'ab_test_enabled',
        'subject_b', 'ab_split_percentage', 'ab_winner', 'ab_winner_metric',
        'ab_auto_select_winner', 'ab_auto_select_after_hours',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'last_recurring_sent' => 'datetime',
        'recurring_end_date' => 'date',
        'is_recurring' => 'boolean',
        'ab_test_enabled' => 'boolean',
        'ab_auto_select_winner' => 'boolean',
        'total_recipients' => 'integer',
        'total_sent' => 'integer',
        'total_failed' => 'integer',
        'total_opens' => 'integer',
        'unique_opens' => 'integer',
        'total_clicks' => 'integer',
        'unique_clicks' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
