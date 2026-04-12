<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// INTENTIONAL: No tenant scope — underlying table `notification_settings` has no tenant_id column.
// Rows are user-scoped (user_id + context_type + context_id); tenant isolation comes from the
// parent group/discussion referenced by context_id.
class GroupDiscussionSubscriber extends Model
{
    use HasFactory;

    protected $table = 'notification_settings';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'context_type', 'context_id', 'frequency',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'context_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
