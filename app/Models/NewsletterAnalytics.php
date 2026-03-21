<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class NewsletterAnalytics extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'newsletter_engagement_patterns';

    protected $fillable = [
        'tenant_id', 'email', 'opens_by_hour', 'clicks_by_hour',
        'total_opens', 'total_clicks', 'best_hour', 'last_updated',
    ];

    protected $casts = [
        'opens_by_hour' => 'array',
        'clicks_by_hour' => 'array',
        'total_opens' => 'integer',
        'total_clicks' => 'integer',
        'best_hour' => 'integer',
        'last_updated' => 'datetime',
    ];

    /**
     * Record an email open.
     *
     */
    public static function recordOpen(
        int $newsletterId,
        ?string $trackingToken,
        string $email,
        ?string $userAgent = null,
        ?string $ipAddress = null
    ): bool {
        $tenantId = self::getNewsletterTenantId($newsletterId)
            ?? \App\Core\TenantContext::getId();

        // Find the queue entry by tracking token
        $queueId = null;
        if ($trackingToken) {
            $queue = DB::table('newsletter_queue')
                ->where('newsletter_id', $newsletterId)
                ->where('tracking_token', $trackingToken)
                ->first();
            $queueId = $queue->id ?? null;
        }

        DB::table('newsletter_opens')->insert([
            'tenant_id'     => $tenantId,
            'newsletter_id' => $newsletterId,
            'queue_id'      => $queueId,
            'email'         => $email,
            'user_agent'    => $userAgent,
            'ip_address'    => $ipAddress,
        ]);

        self::updateOpenStats($newsletterId, $tenantId);

        return true;
    }

    /**
     * Record a link click.
     *
     */
    public static function recordClick(
        int $newsletterId,
        ?string $trackingToken,
        string $email,
        string $url,
        string $linkId,
        ?string $userAgent = null,
        ?string $ipAddress = null
    ): bool {
        $tenantId = self::getNewsletterTenantId($newsletterId)
            ?? \App\Core\TenantContext::getId();

        // Find the queue entry by tracking token
        $queueId = null;
        if ($trackingToken) {
            $queue = DB::table('newsletter_queue')
                ->where('newsletter_id', $newsletterId)
                ->where('tracking_token', $trackingToken)
                ->first();
            $queueId = $queue->id ?? null;
        }

        DB::table('newsletter_clicks')->insert([
            'tenant_id'     => $tenantId,
            'newsletter_id' => $newsletterId,
            'queue_id'      => $queueId,
            'email'         => $email,
            'url'           => $url,
            'link_id'       => $linkId,
            'user_agent'    => $userAgent,
            'ip_address'    => $ipAddress,
        ]);

        self::updateClickStats($newsletterId, $tenantId);

        return true;
    }

    /**
     * Update open stats on the newsletter record.
     */
    private static function updateOpenStats(int $newsletterId, int $tenantId): void
    {
        $total = DB::table('newsletter_opens')
            ->where('tenant_id', $tenantId)
            ->where('newsletter_id', $newsletterId)
            ->count();

        $unique = DB::table('newsletter_opens')
            ->where('tenant_id', $tenantId)
            ->where('newsletter_id', $newsletterId)
            ->distinct('email')
            ->count('email');

        DB::table('newsletters')
            ->where('id', $newsletterId)
            ->where('tenant_id', $tenantId)
            ->update(['total_opens' => $total, 'unique_opens' => $unique]);
    }

    /**
     * Update click stats on the newsletter record.
     */
    private static function updateClickStats(int $newsletterId, int $tenantId): void
    {
        $total = DB::table('newsletter_clicks')
            ->where('tenant_id', $tenantId)
            ->where('newsletter_id', $newsletterId)
            ->count();

        $unique = DB::table('newsletter_clicks')
            ->where('tenant_id', $tenantId)
            ->where('newsletter_id', $newsletterId)
            ->distinct('email')
            ->count('email');

        DB::table('newsletters')
            ->where('id', $newsletterId)
            ->where('tenant_id', $tenantId)
            ->update(['total_clicks' => $total, 'unique_clicks' => $unique]);
    }

    /**
     * Get the tenant_id for a newsletter by its ID.
     */
    private static function getNewsletterTenantId(int $newsletterId): ?int
    {
        $result = DB::table('newsletters')
            ->where('id', $newsletterId)
            ->value('tenant_id');

        return $result !== null ? (int) $result : null;
    }
}
