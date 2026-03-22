<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobVacancyTeam extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'job_vacancy_team';
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'vacancy_id', 'user_id', 'role', 'added_by', 'created_at',
    ];

    protected $casts = ['created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
