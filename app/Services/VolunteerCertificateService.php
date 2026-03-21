<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * VolunteerCertificateService — generates and verifies volunteer impact certificates.
 *
 * Backed by the `vol_certificates` table. All queries are tenant-scoped.
 */
class VolunteerCertificateService
{
    /** @var array Validation/business errors from the last operation */
    private static array $errors = [];

    public function __construct()
    {
    }

    /**
     * Get errors from the last operation.
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Generate a volunteer impact certificate for a user.
     *
     * Summarises their approved volunteer hours across all organisations
     * within the tenant. Returns null with errors if the user has no approved hours.
     *
     * @param int   $userId
     * @param array $options  Optional filters: 'organization_id', 'date_from', 'date_to'
     * @return array|null  Certificate data or null on failure
     */
    public static function generate(int $userId, array $options = []): ?array
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        // Build the hours query with optional filters
        $query = DB::table('vol_logs')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved');

        if (!empty($options['organization_id'])) {
            $query->where('organization_id', (int) $options['organization_id']);
        }

        if (!empty($options['date_from'])) {
            $query->where('date_logged', '>=', $options['date_from']);
        }

        if (!empty($options['date_to'])) {
            $query->where('date_logged', '<=', $options['date_to']);
        }

        $totalHours = (float) $query->sum('hours');

        if ($totalHours <= 0) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'No approved volunteer hours found for this user'];
            return null;
        }

        // Determine date range from actual logged hours
        $dateRange = (clone $query)->selectRaw('MIN(date_logged) as start_date, MAX(date_logged) as end_date')->first();
        $dateRangeStart = $dateRange->start_date ?? now()->format('Y-m-d');
        $dateRangeEnd = $dateRange->end_date ?? now()->format('Y-m-d');

        // Aggregate hours per organisation
        $orgQuery = DB::table('vol_logs as vl')
            ->leftJoin('vol_organizations as vo', 'vl.organization_id', '=', 'vo.id')
            ->where('vl.user_id', $userId)
            ->where('vl.tenant_id', $tenantId)
            ->where('vl.status', 'approved');

        if (!empty($options['organization_id'])) {
            $orgQuery->where('vl.organization_id', (int) $options['organization_id']);
        }
        if (!empty($options['date_from'])) {
            $orgQuery->where('vl.date_logged', '>=', $options['date_from']);
        }
        if (!empty($options['date_to'])) {
            $orgQuery->where('vl.date_logged', '<=', $options['date_to']);
        }

        $orgs = $orgQuery
            ->selectRaw('vo.name as org_name, COALESCE(SUM(vl.hours), 0) as total_hours')
            ->groupBy('vl.organization_id', 'vo.name')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->org_name ?? 'Independent',
                'hours' => round((float) $row->total_hours, 2),
            ])
            ->all();

        // Generate unique verification code
        $verificationCode = strtoupper(Str::random(12));

        // Prevent duplicate — ensure code is unique
        $attempts = 0;
        while (DB::table('vol_certificates')->where('verification_code', $verificationCode)->exists() && $attempts < 5) {
            $verificationCode = strtoupper(Str::random(12));
            $attempts++;
        }

        try {
            $id = DB::table('vol_certificates')->insertGetId([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'verification_code' => $verificationCode,
                'total_hours' => round($totalHours, 2),
                'date_range_start' => $dateRangeStart,
                'date_range_end' => $dateRangeEnd,
                'organizations' => json_encode($orgs),
                'generated_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            error_log("VolunteerCertificateService::generate error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to generate certificate'];
            return null;
        }

        // Fetch user name for the response
        $user = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->select('first_name', 'last_name')
            ->first();

        return [
            'id' => (int) $id,
            'verification_code' => $verificationCode,
            'total_hours' => round($totalHours, 2),
            'date_range_start' => $dateRangeStart,
            'date_range_end' => $dateRangeEnd,
            'organizations' => $orgs,
            'user_name' => $user ? trim($user->first_name . ' ' . $user->last_name) : 'Volunteer',
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Verify a certificate by its unique verification code.
     *
     * @return array|null  Certificate data or null if not found
     */
    public static function verify(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $cert = DB::table('vol_certificates as vc')
            ->join('users as u', function ($join) {
                $join->on('vc.user_id', '=', 'u.id')
                     ->on('vc.tenant_id', '=', 'u.tenant_id');
            })
            ->where('vc.verification_code', $code)
            ->select(
                'vc.id',
                'vc.verification_code',
                'vc.total_hours',
                'vc.date_range_start',
                'vc.date_range_end',
                'vc.organizations',
                'vc.generated_at',
                'u.first_name',
                'u.last_name'
            )
            ->first();

        if (!$cert) {
            return null;
        }

        return [
            'id' => (int) $cert->id,
            'verification_code' => $cert->verification_code,
            'total_hours' => round((float) $cert->total_hours, 2),
            'date_range_start' => $cert->date_range_start,
            'date_range_end' => $cert->date_range_end,
            'organizations' => json_decode($cert->organizations, true) ?? [],
            'user_name' => trim($cert->first_name . ' ' . $cert->last_name),
            'generated_at' => $cert->generated_at,
            'verified' => true,
        ];
    }

    /**
     * Get all certificates for a user.
     */
    public static function getUserCertificates(int $userId): array
    {
        $tenantId = TenantContext::getId();

        return DB::table('vol_certificates')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->orderByDesc('generated_at')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'verification_code' => $row->verification_code,
                'total_hours' => round((float) $row->total_hours, 2),
                'date_range_start' => $row->date_range_start,
                'date_range_end' => $row->date_range_end,
                'organizations' => json_decode($row->organizations, true) ?? [],
                'generated_at' => $row->generated_at,
                'downloaded_at' => $row->downloaded_at,
            ])
            ->all();
    }

    /**
     * Generate printable HTML for a certificate.
     *
     * @return string|null  HTML string or null if certificate not found
     */
    public static function generateHtml(string $code): ?string
    {
        $cert = self::verify($code);
        if (!$cert) {
            return null;
        }

        $userName = htmlspecialchars($cert['user_name'], ENT_QUOTES, 'UTF-8');
        $hours = $cert['total_hours'];
        $dateFrom = htmlspecialchars($cert['date_range_start'], ENT_QUOTES, 'UTF-8');
        $dateTo = htmlspecialchars($cert['date_range_end'], ENT_QUOTES, 'UTF-8');
        $verifyCode = htmlspecialchars($cert['verification_code'], ENT_QUOTES, 'UTF-8');

        $orgRows = '';
        foreach ($cert['organizations'] as $org) {
            $orgName = htmlspecialchars($org['name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
            $orgHours = round((float) ($org['hours'] ?? 0), 2);
            $orgRows .= "<tr><td style=\"padding:8px;border:1px solid #ddd;\">{$orgName}</td><td style=\"padding:8px;border:1px solid #ddd;text-align:center;\">{$orgHours}</td></tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Volunteer Impact Certificate</title>
<style>
  body { font-family: Georgia, 'Times New Roman', serif; max-width: 800px; margin: 40px auto; padding: 40px; border: 3px double #2563eb; }
  h1 { text-align: center; color: #1e40af; margin-bottom: 8px; }
  .subtitle { text-align: center; color: #6b7280; font-size: 14px; margin-bottom: 32px; }
  .name { text-align: center; font-size: 28px; font-weight: bold; margin: 24px 0; color: #111827; }
  .hours { text-align: center; font-size: 20px; color: #059669; margin-bottom: 16px; }
  .period { text-align: center; color: #6b7280; margin-bottom: 24px; }
  table { width: 100%; border-collapse: collapse; margin: 24px 0; }
  th { background: #eff6ff; padding: 8px; border: 1px solid #ddd; text-align: left; }
  .verify { text-align: center; margin-top: 32px; padding: 16px; background: #f9fafb; border-radius: 8px; }
  .verify code { font-size: 18px; font-weight: bold; color: #1e40af; }
  @media print { body { border: none; } }
</style>
</head>
<body>
  <h1>Volunteer Impact Certificate</h1>
  <p class="subtitle">Project NEXUS Community Platform</p>
  <p class="name">{$userName}</p>
  <p class="hours">{$hours} Total Volunteer Hours</p>
  <p class="period">Period: {$dateFrom} to {$dateTo}</p>
  <table>
    <thead><tr><th>Organisation</th><th style="text-align:center;">Hours</th></tr></thead>
    <tbody>{$orgRows}</tbody>
  </table>
  <div class="verify">
    <p>Verification Code: <code>{$verifyCode}</code></p>
    <p style="font-size:12px;color:#9ca3af;">This certificate can be verified online.</p>
  </div>
</body>
</html>
HTML;
    }

    /**
     * Mark a certificate as downloaded.
     */
    public static function markDownloaded(string $code): void
    {
        $code = trim($code);
        if ($code === '') {
            return;
        }

        try {
            DB::table('vol_certificates')
                ->where('verification_code', $code)
                ->whereNull('downloaded_at')
                ->update(['downloaded_at' => now()]);
        } catch (\Throwable $e) {
            error_log("VolunteerCertificateService::markDownloaded error: " . $e->getMessage());
        }
    }
}
