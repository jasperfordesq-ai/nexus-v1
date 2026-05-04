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
use Illuminate\Support\Facades\DB;

class Newsletter extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'newsletters';

    protected $fillable = [
        'tenant_id', 'name', 'subject', 'preview_text', 'content', 'status',
        'scheduled_at', 'sent_at', 'created_by', 'total_recipients',
        'total_sent', 'total_failed', 'total_opens', 'unique_opens',
        'total_clicks', 'unique_clicks', 'target_audience', 'segment_id',
        'target_counties', 'target_towns', 'target_groups',
        'is_recurring', 'recurring_frequency', 'recurring_day',
        'recurring_day_of_week',
        'recurring_day_of_month', 'recurring_time', 'recurring_end_date',
        'recurring_timezone', 'recurring_next_send', 'recurring_last_sent',
        'last_recurring_sent', 'parent_newsletter_id', 'template_id', 'ab_test_enabled',
        'subject_b', 'ab_split_percentage', 'ab_winner', 'ab_winner_metric',
        'ab_auto_select_winner', 'ab_auto_select_after_hours',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'recurring_next_send' => 'datetime',
        'recurring_last_sent' => 'datetime',
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

    /**
     * Legacy cron compatibility helper.
     */
    public static function findById(int $id): ?array
    {
        $row = DB::table('newsletters')->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    /**
     * Legacy cron compatibility helper.
     */
    public static function getQueueStats(int $newsletterId): array
    {
        $rows = DB::table('newsletter_queue')
            ->selectRaw('status, COUNT(*) as count')
            ->where('newsletter_id', $newsletterId)
            ->groupBy('status')
            ->get();

        $stats = [
            'pending' => 0,
            'processing' => 0,
            'sent' => 0,
            'failed' => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) ($row->status ?? '');
            if (array_key_exists($status, $stats)) {
                $stats[$status] = (int) $row->count;
            }
        }

        return $stats;
    }
}
