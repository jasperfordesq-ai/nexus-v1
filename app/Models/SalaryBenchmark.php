<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryBenchmark extends Model
{
    public $timestamps = false;
    protected $table = 'salary_benchmarks';

    protected $fillable = [
        'tenant_id','role_keyword','industry','location',
        'salary_min','salary_max','salary_median','salary_type',
        'currency','year','source',
    ];

    protected $casts = [
        'salary_min'    => 'float',
        'salary_max'    => 'float',
        'salary_median' => 'float',
    ];
}
