<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models\Concerns;

use App\Scopes\TenantScope;
use App\Core\TenantContext;

/**
 * Trait for Eloquent models that are tenant-scoped.
 * Automatically adds WHERE tenant_id = ? to all queries
 * and sets tenant_id on creation.
 */
trait HasTenantScope
{
    public static function bootHasTenantScope(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            if (!isset($model->tenant_id) && TenantContext::getId()) {
                $model->tenant_id = TenantContext::getId();
            }
        });
    }
}
