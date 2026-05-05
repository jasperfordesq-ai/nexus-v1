<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\CaringCommunity;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * CareProviderDirectoryService — AG64 Unified Care-Provider Directory
 *
 * Manages the listing of care providers across types: Spitex, Tagesstätten,
 * private services, Vereine, and volunteer groups. Scoped per tenant.
 */
class CareProviderDirectoryService
{
    private const TABLE = 'caring_care_providers';

    private const PER_PAGE = 20;

    // -------------------------------------------------------------------------
    // Guards
    // -------------------------------------------------------------------------

    public function isAvailable(): bool
    {
        return Schema::hasTable(self::TABLE);
    }

    private function assertAvailable(): void
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException(__('api.caring_provider_directory_unavailable'));
        }
    }

    // -------------------------------------------------------------------------
    // Member-facing read methods
    // -------------------------------------------------------------------------

    /**
     * List active providers for a tenant with optional filters.
     *
     * @param  int    $tenantId
     * @param  array  $filters  Supported keys: type (string|null), search (string|null),
     *                          sub_region_id (int|null), verified_only (bool), page (int)
     * @return array{data: array, total: int, per_page: int, current_page: int}
     */
    public function list(int $tenantId, array $filters = []): array
    {
        $this->assertAvailable();

        $type         = isset($filters['type']) && $filters['type'] !== '' ? (string) $filters['type'] : null;
        $search       = isset($filters['search']) && $filters['search'] !== '' ? (string) $filters['search'] : null;
        $subRegionId  = isset($filters['sub_region_id']) && (int) $filters['sub_region_id'] > 0 ? (int) $filters['sub_region_id'] : null;
        $verifiedOnly = !empty($filters['verified_only']);
        $page         = max(1, (int) ($filters['page'] ?? 1));
        $offset       = ($page - 1) * self::PER_PAGE;

        $query = DB::table(self::TABLE)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active');

        if ($type !== null) {
            $query->where('type', $type);
        }

        if ($subRegionId !== null && Schema::hasColumn(self::TABLE, 'sub_region_id')) {
            $query->where('sub_region_id', $subRegionId);
        }

        if ($search !== null) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', '%' . $search . '%')
                  ->orWhere('description', 'LIKE', '%' . $search . '%');
            });
        }

        if ($verifiedOnly) {
            $query->where('is_verified', true);
        }

        $total = (clone $query)->count();

        $rows = $query
            ->orderByDesc('is_verified')
            ->orderBy('name')
            ->offset($offset)
            ->limit(self::PER_PAGE)
            ->get()
            ->map(fn ($row) => $this->castRow((array) $row))
            ->all();

        return [
            'data'         => $rows,
            'total'        => $total,
            'per_page'     => self::PER_PAGE,
            'current_page' => $page,
        ];
    }

    /**
     * Get a single provider by id, scoped to tenant.
     */
    public function get(int $id, int $tenantId): ?array
    {
        $this->assertAvailable();

        $row = DB::table(self::TABLE)
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        return $row ? $this->castRow((array) $row) : null;
    }

    /**
     * Get a member-visible provider by id. Inactive providers stay available
     * to admin tools through get(), but are hidden from public directory views.
     */
    public function getActive(int $id, int $tenantId): ?array
    {
        $this->assertAvailable();

        $row = DB::table(self::TABLE)
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();

        return $row ? $this->castRow((array) $row) : null;
    }

    // -------------------------------------------------------------------------
    // Admin write methods
    // -------------------------------------------------------------------------

    /**
     * Create a new care provider.
     */
    public function create(int $tenantId, array $data, int $adminUserId): array
    {
        $this->assertAvailable();

        $status = (string) ($data['status'] ?? 'active');
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        $payload = [
            'tenant_id'     => $tenantId,
            'name'          => (string) ($data['name'] ?? ''),
            'type'          => (string) ($data['type'] ?? ''),
            'description'   => isset($data['description']) ? (string) $data['description'] : null,
            'categories'    => isset($data['categories']) ? json_encode($data['categories']) : null,
            'address'       => isset($data['address']) ? (string) $data['address'] : null,
            'contact_phone' => isset($data['contact_phone']) ? (string) $data['contact_phone'] : null,
            'contact_email' => isset($data['contact_email']) ? (string) $data['contact_email'] : null,
            'website_url'   => isset($data['website_url']) ? (string) $data['website_url'] : null,
            'opening_hours' => isset($data['opening_hours']) ? json_encode($data['opening_hours']) : null,
            'is_verified'   => false,
            'status'        => $status,
            'created_by'    => $adminUserId,
            'created_at'    => now(),
            'updated_at'    => now(),
        ];

        if (Schema::hasColumn(self::TABLE, 'sub_region_id')) {
            $payload['sub_region_id'] = $this->normaliseSubRegionId($data['sub_region_id'] ?? null, $tenantId);
        }

        $id = DB::table(self::TABLE)->insertGetId($payload);

        return $this->get($id, $tenantId) ?? [];
    }

    /**
     * Update an existing provider. Returns the updated row.
     */
    public function update(int $id, int $tenantId, array $data): array
    {
        $this->assertAvailable();

        $fillable = ['name', 'type', 'description', 'address', 'contact_phone',
                     'contact_email', 'website_url', 'status'];

        $payload = ['updated_at' => now()];

        foreach ($fillable as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field] !== null ? (string) $data[$field] : null;
            }
        }

        if (array_key_exists('categories', $data)) {
            $payload['categories'] = $data['categories'] !== null
                ? json_encode($data['categories'])
                : null;
        }

        if (array_key_exists('sub_region_id', $data) && Schema::hasColumn(self::TABLE, 'sub_region_id')) {
            $payload['sub_region_id'] = $this->normaliseSubRegionId($data['sub_region_id'], $tenantId);
        }

        if (array_key_exists('opening_hours', $data)) {
            $payload['opening_hours'] = $data['opening_hours'] !== null
                ? json_encode($data['opening_hours'])
                : null;
        }

        DB::table(self::TABLE)
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update($payload);

        return $this->get($id, $tenantId) ?? [];
    }

    /**
     * Soft-delete a provider (set status = inactive).
     */
    public function delete(int $id, int $tenantId): void
    {
        $this->assertAvailable();

        DB::table(self::TABLE)
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update(['status' => 'inactive', 'updated_at' => now()]);
    }

    /**
     * Mark a provider as verified.
     */
    public function verify(int $id, int $tenantId): void
    {
        $this->assertAvailable();

        DB::table(self::TABLE)
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update(['is_verified' => true, 'updated_at' => now()]);
    }

    /**
     * AG64 follow-up — Find potential duplicate / overlapping provider rows.
     *
     * Compares every active provider against every other active provider and
     * scores by: name similarity, contact-email match, contact-phone match,
     * website-domain match, address-token overlap. Returns pairs that score
     * above a "likely duplicate" threshold so a coordinator can manually merge
     * or de-duplicate. This is read-only — the actual merge stays admin-driven.
     *
     * @return array{pairs:array<int,array<string,mixed>>,total:int,scanned:int}
     */
    public function findPotentialDuplicates(int $tenantId, float $threshold = 0.65, int $maxPairs = 50): array
    {
        $this->assertAvailable();

        $rows = DB::table(self::TABLE)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('id')
            ->get([
                'id', 'name', 'type', 'contact_email', 'contact_phone',
                'website_url', 'address', 'is_verified',
            ])
            ->all();

        $count = count($rows);
        $pairs = [];

        for ($i = 0; $i < $count; $i++) {
            $a = $rows[$i];
            for ($j = $i + 1; $j < $count; $j++) {
                $b = $rows[$j];

                $signals = $this->compareProviders($a, $b);
                if ($signals['score'] >= $threshold) {
                    $pairs[] = [
                        'provider_a' => [
                            'id'          => (int) $a->id,
                            'name'        => (string) $a->name,
                            'type'        => (string) $a->type,
                            'is_verified' => (bool) $a->is_verified,
                        ],
                        'provider_b' => [
                            'id'          => (int) $b->id,
                            'name'        => (string) $b->name,
                            'type'        => (string) $b->type,
                            'is_verified' => (bool) $b->is_verified,
                        ],
                        'score'   => round($signals['score'], 3),
                        'signals' => $signals['signals'],
                    ];
                }
            }
        }

        usort($pairs, static fn (array $x, array $y) => $y['score'] <=> $x['score']);

        $total = count($pairs);
        if ($total > $maxPairs) {
            $pairs = array_slice($pairs, 0, $maxPairs);
        }

        return [
            'pairs'   => $pairs,
            'total'   => $total,
            'scanned' => $count,
        ];
    }

    /**
     * Score-based comparison of two providers. Each signal contributes a
     * weighted fraction; total score is in [0, 1].
     *
     * @return array{score:float,signals:array<int,string>}
     */
    private function compareProviders(object $a, object $b): array
    {
        $signals = [];
        $score = 0.0;

        // Name similarity (weight 0.45) — case-insensitive Levenshtein-derived ratio.
        $nameA = $this->normaliseName((string) $a->name);
        $nameB = $this->normaliseName((string) $b->name);
        $nameSim = $this->stringSimilarity($nameA, $nameB);
        if ($nameSim >= 0.85) {
            $signals[] = 'name_match';
            $score += 0.45;
        } elseif ($nameSim >= 0.70) {
            $signals[] = 'name_similar';
            $score += 0.25;
        }

        // Contact email exact match (weight 0.30).
        $emailA = strtolower(trim((string) ($a->contact_email ?? '')));
        $emailB = strtolower(trim((string) ($b->contact_email ?? '')));
        if ($emailA !== '' && $emailA === $emailB) {
            $signals[] = 'email_match';
            $score += 0.30;
        }

        // Contact phone — match on digits only (weight 0.25).
        $phoneA = preg_replace('/\D+/', '', (string) ($a->contact_phone ?? '')) ?? '';
        $phoneB = preg_replace('/\D+/', '', (string) ($b->contact_phone ?? '')) ?? '';
        if (strlen($phoneA) >= 7 && $phoneA === $phoneB) {
            $signals[] = 'phone_match';
            $score += 0.25;
        }

        // Website domain match (weight 0.25).
        $domainA = $this->extractDomain((string) ($a->website_url ?? ''));
        $domainB = $this->extractDomain((string) ($b->website_url ?? ''));
        if ($domainA !== '' && $domainA === $domainB) {
            $signals[] = 'website_match';
            $score += 0.25;
        }

        // Address token overlap (weight 0.15) — at least 2 shared 4+-char tokens.
        $tokensA = $this->addressTokens((string) ($a->address ?? ''));
        $tokensB = $this->addressTokens((string) ($b->address ?? ''));
        $shared = array_intersect($tokensA, $tokensB);
        if (count($shared) >= 2) {
            $signals[] = 'address_overlap';
            $score += 0.15;
        }

        // Same type bonus (weight 0.05) — only adds a small lift.
        if ((string) $a->type === (string) $b->type) {
            $score += 0.05;
        }

        if ($score > 1.0) {
            $score = 1.0;
        }

        return ['score' => $score, 'signals' => $signals];
    }

    private function normaliseName(string $name): string
    {
        $name = mb_strtolower($name);
        // Strip common org/legal-form noise so "Spitex AG" and "Spitex" match.
        $name = preg_replace(
            '/\b(ag|gmbh|sa|sàrl|sarl|verein|genossenschaft|cooperative|association|kiss|spitex)\b/u',
            ' ',
            $name
        ) ?? $name;
        $name = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $name) ?? $name;
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        return trim($name);
    }

    /**
     * Levenshtein-based similarity in [0, 1]. Falls back to similar_text if
     * either string exceeds the PHP levenshtein() 255-char limit.
     */
    private function stringSimilarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }

        $maxLen = max(strlen($a), strlen($b));
        if ($maxLen === 0) {
            return 0.0;
        }

        if (strlen($a) <= 255 && strlen($b) <= 255) {
            $distance = levenshtein($a, $b);
            return 1.0 - ($distance / $maxLen);
        }

        $percent = 0.0;
        similar_text($a, $b, $percent);
        return $percent / 100.0;
    }

    private function extractDomain(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'http://' . $url;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host)) {
            return '';
        }
        $host = strtolower($host);
        return preg_replace('/^www\./', '', $host) ?? $host;
    }

    /**
     * @return array<int,string>
     */
    private function addressTokens(string $address): array
    {
        $address = mb_strtolower($address);
        $address = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $address) ?? $address;
        $tokens = preg_split('/\s+/', trim($address)) ?: [];
        return array_values(array_filter($tokens, static fn (string $t) => mb_strlen($t) >= 4));
    }

    /**
     * Admin-only listing — returns all statuses (for the admin panel).
     *
     * @return array{data: array, total: int, per_page: int, current_page: int}
     */
    public function adminList(int $tenantId, array $filters = []): array
    {
        $this->assertAvailable();

        $page   = max(1, (int) ($filters['page'] ?? 1));
        $offset = ($page - 1) * self::PER_PAGE;

        $query = DB::table(self::TABLE)->where('tenant_id', $tenantId);

        $total = (clone $query)->count();

        $rows = $query
            ->orderByDesc('created_at')
            ->offset($offset)
            ->limit(self::PER_PAGE)
            ->get()
            ->map(fn ($row) => $this->castRow((array) $row))
            ->all();

        return [
            'data'         => $rows,
            'total'        => $total,
            'per_page'     => self::PER_PAGE,
            'current_page' => $page,
        ];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function castRow(array $row): array
    {
        return [
            'id'            => (int) $row['id'],
            'tenant_id'     => (int) $row['tenant_id'],
            'name'          => (string) $row['name'],
            'type'          => (string) $row['type'],
            'description'   => isset($row['description']) ? (string) $row['description'] : null,
            'categories'    => isset($row['categories']) ? json_decode((string) $row['categories'], true) : null,
            'address'       => isset($row['address']) ? (string) $row['address'] : null,
            'sub_region_id' => isset($row['sub_region_id']) ? (int) $row['sub_region_id'] : null,
            'sub_region'    => $this->loadSubRegion($row),
            'contact_phone' => isset($row['contact_phone']) ? (string) $row['contact_phone'] : null,
            'contact_email' => isset($row['contact_email']) ? (string) $row['contact_email'] : null,
            'website_url'   => isset($row['website_url']) ? (string) $row['website_url'] : null,
            'opening_hours' => isset($row['opening_hours']) ? json_decode((string) $row['opening_hours'], true) : null,
            'is_verified'   => (bool) $row['is_verified'],
            'status'        => (string) $row['status'],
            'created_by'    => isset($row['created_by']) ? (int) $row['created_by'] : null,
            'created_at'    => $row['created_at'] ?? null,
            'updated_at'    => $row['updated_at'] ?? null,
        ];
    }

    private function normaliseSubRegionId(mixed $value, int $tenantId): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! Schema::hasTable('caring_sub_regions') || ! Schema::hasColumn(self::TABLE, 'sub_region_id')) {
            return null;
        }

        $id = (int) $value;
        if ($id <= 0) {
            return null;
        }

        return DB::table('caring_sub_regions')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->exists()
                ? $id
                : null;
    }

    private function loadSubRegion(array $row): ?array
    {
        if (! isset($row['sub_region_id']) || $row['sub_region_id'] === null || ! Schema::hasTable('caring_sub_regions')) {
            return null;
        }

        $subRegion = DB::table('caring_sub_regions')
            ->where('id', (int) $row['sub_region_id'])
            ->where('tenant_id', (int) $row['tenant_id'])
            ->first();

        if (! $subRegion) {
            return null;
        }

        return [
            'id' => (int) $subRegion->id,
            'name' => (string) $subRegion->name,
            'slug' => (string) $subRegion->slug,
            'type' => (string) $subRegion->type,
        ];
    }
}
