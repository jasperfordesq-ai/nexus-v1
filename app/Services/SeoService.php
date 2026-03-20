<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * SeoService — Laravel DI-based service for SEO metadata management.
 *
 * Manages page-level metadata, Open Graph tags, and redirect rules.
 */
class SeoService
{
    /**
     * Get SEO metadata for a specific route/path.
     */
    public function getMetadata(int $tenantId, string $path): array
    {
        $meta = DB::table('seo_metadata')
            ->where('tenant_id', $tenantId)
            ->where('path', $path)
            ->first();

        if (! $meta) {
            return ['title' => null, 'description' => null, 'og_image' => null, 'canonical' => null];
        }

        return (array) $meta;
    }

    /**
     * Update SEO metadata for a specific route/path.
     */
    public function updateMetadata(int $tenantId, string $path, array $data): bool
    {
        $allowed = ['title', 'description', 'og_image', 'canonical', 'robots', 'schema_json'];
        $update = array_intersect_key($data, array_flip($allowed));
        $update['updated_at'] = now();

        return DB::table('seo_metadata')
            ->updateOrInsert(
                ['tenant_id' => $tenantId, 'path' => $path],
                $update
            );
    }

    /**
     * Get all redirect rules for a tenant.
     */
    public function getRedirects(int $tenantId): array
    {
        return DB::table('seo_redirects')
            ->where('tenant_id', $tenantId)
            ->orderBy('from_path')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Create a redirect rule.
     */
    public function createRedirect(int $tenantId, string $from, string $to, int $statusCode = 301): ?int
    {
        if (! in_array($statusCode, [301, 302], true)) {
            $statusCode = 301;
        }

        return DB::table('seo_redirects')->insertGetId([
            'tenant_id'   => $tenantId,
            'from_path'   => $from,
            'to_path'     => $to,
            'status_code' => $statusCode,
            'created_at'  => now(),
        ]);
    }
}
