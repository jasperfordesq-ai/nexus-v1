<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class AiUsage extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'ai_usage';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'provider',
        'feature',
        'tokens_input',
        'tokens_output',
        'cost_usd',
    ];

    protected $casts = [
        'tokens_input' => 'integer',
        'tokens_output' => 'integer',
        'cost_usd' => 'decimal:6',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log an AI usage record.
     */
    public static function log(
        int $userId,
        int $tenantId,
        string $model,
        int $inputTokens,
        int $outputTokens,
        float $cost
    ): int {
        $id = DB::table('ai_usage')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'provider' => 'openai', // default provider
            'feature' => $model,
            'tokens_input' => $inputTokens,
            'tokens_output' => $outputTokens,
            'cost_usd' => $cost,
            'created_at' => now(),
        ]);

        return (int) $id;
    }
}
