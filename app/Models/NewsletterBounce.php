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

class NewsletterBounce extends Model
{
    use HasFactory, HasTenantScope;

    public const BOUNCE_SOFT = 'soft';
    public const BOUNCE_HARD = 'hard';
    public const BOUNCE_COMPLAINT = 'complaint';

    protected $table = 'newsletter_bounces';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'email', 'newsletter_id', 'queue_id',
        'bounce_type', 'bounce_reason', 'bounce_code', 'bounced_at',
    ];

    protected $casts = [
        'newsletter_id' => 'integer',
        'queue_id' => 'integer',
        'bounced_at' => 'datetime',
    ];

    public function newsletter(): BelongsTo
    {
        return $this->belongsTo(Newsletter::class);
    }

    /**
     * Record a bounce event and auto-suppress hard bounces / complaints.
     */
    public static function record(
        int $tenantId,
        string $email,
        ?int $newsletterId,
        ?int $queueId,
        string $bounceType,
        string $reason = '',
        string $code = ''
    ): void {
        DB::table('newsletter_bounces')->insert([
            'tenant_id'    => $tenantId,
            'email'        => $email,
            'newsletter_id' => $newsletterId,
            'queue_id'     => $queueId,
            'bounce_type'  => $bounceType,
            'bounce_reason' => $reason,
            'bounce_code'  => $code,
            'bounced_at'   => now(),
        ]);

        // Auto-suppress on hard bounce or spam complaint
        if ($bounceType === self::BOUNCE_HARD || $bounceType === self::BOUNCE_COMPLAINT) {
            DB::insert(
                "INSERT INTO newsletter_suppression_list (tenant_id, email, reason, bounce_count)
                 VALUES (?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE suppressed_at = NOW(), bounce_count = bounce_count + 1",
                [$tenantId, $email, $bounceType === self::BOUNCE_COMPLAINT ? 'complaint' : 'hard_bounce']
            );
        }
    }
}
