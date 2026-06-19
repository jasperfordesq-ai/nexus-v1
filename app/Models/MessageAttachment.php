<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file/image attachment on a direct message. A message may have many.
 * Tenant-scoped via HasTenantScope (matches Message), so reads and writes are
 * automatically constrained to the current tenant.
 */
class MessageAttachment extends Model
{
    use HasTenantScope;

    protected $table = 'message_attachments';

    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'file_url',
        'file_name',
        'file_size',
        'mime_type',
        'created_at',
    ];

    protected $casts = [
        'message_id' => 'integer',
        'file_size' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * React's MessageAttachment type expects { id, url, type, name, size }; the
     * accessible Blade reads the raw columns (file_url/file_name/mime_type). We
     * append the React-friendly aliases so a single serialized payload satisfies
     * both frontends without changing either.
     *
     * @var list<string>
     */
    protected $appends = ['url', 'type', 'name', 'size'];

    public function getUrlAttribute(): string
    {
        return (string) ($this->attributes['file_url'] ?? '');
    }

    public function getNameAttribute(): string
    {
        return (string) ($this->attributes['file_name'] ?? '');
    }

    public function getSizeAttribute(): int
    {
        return (int) ($this->attributes['file_size'] ?? 0);
    }

    /** 'image' for image/* MIME types, otherwise 'file' (matches the React union). */
    public function getTypeAttribute(): string
    {
        $mime = (string) ($this->attributes['mime_type'] ?? '');
        return str_starts_with($mime, 'image/') ? 'image' : 'file';
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }
}
