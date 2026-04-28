<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

class MunicipalVerificationService
{
    public function current(int $tenantId): array
    {
        $this->assertAvailable();

        $rows = DB::table('municipal_verifications')
            ->where('tenant_id', $tenantId)
            ->orderByRaw("FIELD(status, 'verified', 'pending', 'revoked')")
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $items = $rows->map(fn (object $row): array => $this->format($row))->all();
        $active = null;
        foreach ($items as $item) {
            if ($item['status'] === 'verified') {
                $active = $item;
                break;
            }
        }

        return [
            'verified' => $active !== null,
            'active' => $active,
            'items' => $items,
        ];
    }

    public function startDnsVerification(int $tenantId, int $adminId, string $domain): array
    {
        $this->assertAvailable();
        $domain = $this->normaliseDomain($domain);
        $token = 'nexus-municipal-verify=' . bin2hex(random_bytes(24));
        $recordName = '_nexus-municipal.' . $domain;

        DB::table('municipal_verifications')->updateOrInsert(
            ['tenant_id' => $tenantId, 'domain' => $domain],
            [
                'method' => 'dns_txt',
                'status' => 'pending',
                'dns_record_name' => $recordName,
                'dns_record_value' => $token,
                'requested_by' => $adminId,
                'verified_by' => null,
                'verified_at' => null,
                'revoked_at' => null,
                'attestation_note' => null,
                'metadata' => json_encode(['instructions_key' => 'municipal_verification_dns_txt']),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return $this->getByDomain($tenantId, $domain);
    }

    public function attest(int $tenantId, int $adminId, string $domain, ?string $note = null): array
    {
        $this->assertAvailable();
        $domain = $this->normaliseDomain($domain);
        $cleanNote = trim((string) $note);

        DB::table('municipal_verifications')->updateOrInsert(
            ['tenant_id' => $tenantId, 'domain' => $domain],
            [
                'method' => 'admin_attestation',
                'status' => 'verified',
                'dns_record_name' => null,
                'dns_record_value' => null,
                'requested_by' => $adminId,
                'verified_by' => $adminId,
                'verified_at' => now(),
                'revoked_at' => null,
                'attestation_note' => $cleanNote !== '' ? mb_substr($cleanNote, 0, 1000) : null,
                'metadata' => json_encode(['attested_by_admin' => true]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return $this->getByDomain($tenantId, $domain);
    }

    public function revoke(int $tenantId, int $id): bool
    {
        $this->assertAvailable();

        return DB::table('municipal_verifications')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->update([
                'status' => 'revoked',
                'revoked_at' => now(),
                'updated_at' => now(),
            ]) > 0;
    }

    public function isVerified(int $tenantId): bool
    {
        if (!Schema::hasTable('municipal_verifications')) {
            return false;
        }

        return DB::table('municipal_verifications')
            ->where('tenant_id', $tenantId)
            ->where('status', 'verified')
            ->exists();
    }

    private function getByDomain(int $tenantId, string $domain): array
    {
        $row = DB::table('municipal_verifications')
            ->where('tenant_id', $tenantId)
            ->where('domain', $domain)
            ->first();

        if (!$row) {
            throw new RuntimeException(__('api.municipal_verification_not_found'));
        }

        return $this->format($row);
    }

    private function assertAvailable(): void
    {
        if (!Schema::hasTable('municipal_verifications')) {
            throw new RuntimeException(__('api.municipal_verification_unavailable'));
        }
    }

    private function normaliseDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = trim(explode('/', $domain)[0] ?? $domain);
        $domain = rtrim($domain, '.');

        if (
            $domain === ''
            || strlen($domain) > 253
            || !preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain)
        ) {
            throw new InvalidArgumentException(__('api.domain_format_invalid'));
        }

        return $domain;
    }

    private function format(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'tenant_id' => (int) $row->tenant_id,
            'domain' => (string) $row->domain,
            'method' => (string) $row->method,
            'status' => (string) $row->status,
            'dns_record_name' => $row->dns_record_name,
            'dns_record_value' => $row->dns_record_value,
            'requested_by' => $row->requested_by !== null ? (int) $row->requested_by : null,
            'verified_by' => $row->verified_by !== null ? (int) $row->verified_by : null,
            'verified_at' => $row->verified_at,
            'revoked_at' => $row->revoked_at,
            'attestation_note' => $row->attestation_note,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }
}
