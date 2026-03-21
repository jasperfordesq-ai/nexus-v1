<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;

class Notification extends Model
{
    use HasFactory, HasTenantScope;
    use SoftDeletes;

    protected $table = 'notifications';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'user_id', 'type', 'message',
        'link', 'is_read', 'created_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Appended attributes for frontend compatibility.
     *
     * Frontend expects `read_at` (nullable timestamp) and `body`/`title` fields.
     * Backend stores `is_read` (boolean) and `message` (string).
     */
    protected $appends = ['read_at', 'body', 'title'];

    /**
     * read_at accessor — converts boolean `is_read` to nullable timestamp.
     *
     * Frontend checks `!notification.read_at` to determine unread status.
     * When read, returns `created_at` as the approximate "read" timestamp
     * (no separate read timestamp column exists).
     */
    public function getReadAtAttribute(): ?string
    {
        if (! $this->is_read) {
            return null;
        }

        return $this->created_at?->toIso8601String() ?? now()->toIso8601String();
    }

    /**
     * body accessor — alias for `message` field.
     *
     * Frontend Notification type defines `body: string`.
     */
    public function getBodyAttribute(): ?string
    {
        return $this->attributes['message'] ?? null;
    }

    /**
     * title accessor — extracts a title from the notification.
     *
     * Frontend Notification type defines `title: string`.
     * Notifications don't have a separate title column, so derive from type.
     */
    public function getTitleAttribute(): string
    {
        $type = $this->attributes['type'] ?? 'notification';
        return ucfirst(str_replace('_', ' ', $type));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }

    /**
     * Create a notification record.
     * Named createNotification to avoid conflict with Eloquent's create().
     */
    public static function createNotification(
        int $userId,
        string $message,
        ?string $link = null,
        string $type = 'info',
        bool $isImportant = false
    ): int {
        $tenantId = TenantContext::getId();

        $id = DB::table('notifications')->insertGetId([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'message' => $message,
            'link' => $link,
            'type' => $type,
            'is_read' => 0,
            'created_at' => now(),
        ]);

        return (int) $id;
    }

    /**
     * Count unread notifications for a user.
     */
    public static function countUnread(int $userId): int
    {
        return (int) DB::table('notifications')
            ->where('tenant_id', \App\Core\TenantContext::getId())
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->whereNull('deleted_at')
            ->count();
    }
}
