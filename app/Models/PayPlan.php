<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayPlan extends Model
{
    use HasFactory;

    protected $table = 'pay_plans';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'tier_level',
        'price_monthly',
        'price_yearly',
        'features',
        'allowed_layouts',
        'max_menus',
        'max_menu_items',
        'is_active',
        'stripe_product_id',
        'stripe_price_id_monthly',
        'stripe_price_id_yearly',
    ];

    protected $casts = [
        'tier_level' => 'integer',
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'features' => 'array',
        'allowed_layouts' => 'array',
        'max_menus' => 'integer',
        'max_menu_items' => 'integer',
        'is_active' => 'boolean',
    ];
}
