<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * VolunteerCertificateService - Generate volunteer impact certificates
 *
 * Creates HTML-based certificates showing:
 * - Member name and total hours
 * - Date range
 * - Organization branding/logo
 * - QR verification code linking to /verify/cert/{code}
 *
 * The certificate is generated as HTML which can be printed/saved as PDF
 * by the browser, or rendered on a dedicated verification page.
 */
class VolunteerCertificateService
{
    private static array $errors = [];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Generate a volunteer impact certificate
     *
     * @param int $userId Volunteer user ID
     * @param array $options [start_date, end_date, organization_id]
     * @return array|null Certificate data or null on failure
     */
    public static function generate(int $userId, array $options = []): ?array
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        $startDate = $options['start_date'] ?? date('Y-01-01');
        $endDate = $options['end_date'] ?? date('Y-m-d');
        $orgId = $options['organization_id'] ?? null;

        // Validate date range
        if (strtotime($endDate) < strtotime($startDate)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'End date must be after start date'];
            return null;
        }

        $db = Database::getConnection();

        // Get user info
        $stmt = $db->prepare("SELECT id, name, avatar_url FROM users WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$userId, $tenantId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'User not found'];
            return null;
        }

        // Get verified hours in date range
        $sql = "
            SELECT l.organization_id, org.name as org_name, org.logo_url as org_logo,
                   SUM(l.hours) as total_hours, COUNT(*) as shift_count
            FROM vol_logs l
            JOIN vol_organizations org ON l.organization_id = org.id
            WHERE l.user_id = ? AND l.status = 'approved'
            AND l.date_logged >= ? AND l.date_logged <= ?
            AND l.tenant_id = ?
        ";
        $params = [$userId, $startDate, $endDate, $tenantId];

        if ($orgId) {
            $sql .= " AND l.organization_id = ?";
            $params[] = $orgId;
        }

        $sql .= " GROUP BY l.organization_id ORDER BY total_hours DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $orgHours = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $totalHours = 0;
        $orgsData = [];
        foreach ($orgHours as $oh) {
            $totalHours += (float)$oh['total_hours'];
            $orgsData[] = [
                'name' => $oh['org_name'],
                'logo_url' => $oh['org_logo'],
                'hours' => (float)$oh['total_hours'],
                'shifts' => (int)$oh['shift_count'],
            ];
        }

        if ($totalHours <= 0) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'No verified volunteer hours found for the selected period'];
            return null;
        }

        // Generate verification code
        $verificationCode = self::generateVerificationCode();

        // Get tenant info for branding
        $stmt = $db->prepare("SELECT name, logo_url FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Store certificate record
        try {
            $stmt = $db->prepare("
                INSERT INTO vol_certificates
                (tenant_id, user_id, verification_code, total_hours, date_range_start, date_range_end, organizations, generated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $tenantId,
                $userId,
                $verificationCode,
                $totalHours,
                $startDate,
                $endDate,
                json_encode($orgsData),
            ]);

            $certId = (int)$db->lastInsertId();
        } catch (\Exception $e) {
            error_log("VolunteerCertificateService::generate error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to generate certificate'];
            return null;
        }

        return [
            'id' => $certId,
            'verification_code' => $verificationCode,
            'verification_url' => self::getVerificationUrl($verificationCode),
            'user' => [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'avatar_url' => $user['avatar_url'],
            ],
            'total_hours' => round($totalHours, 1),
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'organizations' => $orgsData,
            'tenant' => [
                'name' => $tenant['name'] ?? 'Project NEXUS',
                'logo_url' => $tenant['logo_url'] ?? null,
            ],
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Verify a certificate by verification code
     *
     * @param string $code Verification code
     * @return array|null Certificate data or null if invalid
     */
    public static function verify(string $code): ?array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT c.*, u.name as user_name, u.avatar_url as user_avatar,
                   t.name as tenant_name, t.logo_url as tenant_logo
            FROM vol_certificates c
            JOIN users u ON c.user_id = u.id
            JOIN tenants t ON c.tenant_id = t.id
            WHERE c.verification_code = ?
        ");
        $stmt->execute([$code]);
        $cert = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$cert) {
            return null;
        }

        return [
            'valid' => true,
            'verification_code' => $cert['verification_code'],
            'user' => [
                'name' => $cert['user_name'],
                'avatar_url' => $cert['user_avatar'],
            ],
            'total_hours' => (float)$cert['total_hours'],
            'date_range' => [
                'start' => $cert['date_range_start'],
                'end' => $cert['date_range_end'],
            ],
            'organizations' => json_decode($cert['organizations'] ?? '[]', true) ?: [],
            'tenant' => [
                'name' => $cert['tenant_name'],
                'logo_url' => $cert['tenant_logo'],
            ],
            'generated_at' => $cert['generated_at'],
        ];
    }

    /**
     * Get certificates for a user
     *
     * @param int $userId User ID
     * @return array List of certificates
     */
    public static function getUserCertificates(int $userId): array
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT * FROM vol_certificates
            WHERE user_id = ? AND tenant_id = ?
            ORDER BY generated_at DESC
            LIMIT 20
        ");
        $stmt->execute([$userId, $tenantId]);
        $certs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(function ($c) {
            return [
                'id' => (int)$c['id'],
                'verification_code' => $c['verification_code'],
                'verification_url' => self::getVerificationUrl($c['verification_code']),
                'total_hours' => (float)$c['total_hours'],
                'date_range' => [
                    'start' => $c['date_range_start'],
                    'end' => $c['date_range_end'],
                ],
                'organizations' => json_decode($c['organizations'] ?? '[]', true) ?: [],
                'generated_at' => $c['generated_at'],
                'downloaded_at' => $c['downloaded_at'],
            ];
        }, $certs);
    }

    /**
     * Generate certificate HTML for printing/PDF
     *
     * @param string $code Verification code
     * @return string|null HTML string
     */
    public static function generateHtml(string $code): ?string
    {
        $cert = self::verify($code);
        if (!$cert) {
            return null;
        }

        $verificationUrl = self::getVerificationUrl($code);
        $orgsHtml = '';
        foreach ($cert['organizations'] as $org) {
            $orgsHtml .= '<tr><td style="padding:8px 16px;border-bottom:1px solid #e5e7eb;">'
                . htmlspecialchars($org['name'])
                . '</td><td style="padding:8px 16px;border-bottom:1px solid #e5e7eb;text-align:right;">'
                . round($org['hours'], 1) . ' hours</td></tr>';
        }

        $startFormatted = date('F j, Y', strtotime($cert['date_range']['start']));
        $endFormatted = date('F j, Y', strtotime($cert['date_range']['end']));
        $generatedFormatted = date('F j, Y', strtotime($cert['generated_at']));
        $tenantName = htmlspecialchars($cert['tenant']['name']);
        $userName = htmlspecialchars($cert['user']['name']);
        $totalHours = $cert['total_hours'];

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Volunteer Impact Certificate - {$userName}</title>
<style>
  @page { size: A4 landscape; margin: 0; }
  body { font-family: 'Georgia', serif; margin: 0; padding: 40px; background: white; color: #1f2937; }
  .certificate { border: 4px double #6366f1; border-radius: 16px; padding: 48px; max-width: 900px; margin: 0 auto; position: relative; }
  .header { text-align: center; margin-bottom: 32px; }
  .header h1 { font-size: 28px; color: #4f46e5; margin: 0 0 8px; letter-spacing: 2px; text-transform: uppercase; }
  .header h2 { font-size: 16px; color: #6b7280; margin: 0; font-weight: normal; }
  .main { text-align: center; margin: 32px 0; }
  .main .name { font-size: 36px; color: #1f2937; margin: 16px 0; font-style: italic; }
  .main .hours { font-size: 48px; font-weight: bold; color: #6366f1; margin: 16px 0; }
  .main .hours-label { font-size: 14px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }
  .main .period { font-size: 14px; color: #6b7280; margin-top: 8px; }
  .orgs { margin: 24px auto; max-width: 500px; }
  .orgs table { width: 100%; border-collapse: collapse; font-size: 14px; }
  .orgs th { padding: 8px 16px; text-align: left; border-bottom: 2px solid #6366f1; color: #4f46e5; }
  .footer { text-align: center; margin-top: 32px; font-size: 12px; color: #9ca3af; }
  .verification { margin-top: 16px; padding: 12px; background: #f9fafb; border-radius: 8px; font-size: 11px; }
  .verification code { font-family: monospace; color: #4f46e5; }
</style>
</head>
<body>
<div class="certificate">
  <div class="header">
    <h1>Volunteer Impact Certificate</h1>
    <h2>{$tenantName}</h2>
  </div>
  <div class="main">
    <p style="font-size:14px;color:#6b7280;">This certifies that</p>
    <p class="name">{$userName}</p>
    <p style="font-size:14px;color:#6b7280;">has completed</p>
    <p class="hours">{$totalHours}</p>
    <p class="hours-label">Verified Volunteer Hours</p>
    <p class="period">{$startFormatted} - {$endFormatted}</p>
  </div>
  <div class="orgs">
    <table>
      <thead><tr><th>Organization</th><th style="text-align:right;">Hours</th></tr></thead>
      <tbody>{$orgsHtml}</tbody>
    </table>
  </div>
  <div class="footer">
    <p>Generated on {$generatedFormatted}</p>
    <div class="verification">
      Verify this certificate at: <code>{$verificationUrl}</code>
    </div>
  </div>
</div>
</body>
</html>
HTML;
    }

    /**
     * Mark certificate as downloaded
     */
    public static function markDownloaded(string $code): void
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();
        try {
            $stmt = $db->prepare("UPDATE vol_certificates SET downloaded_at = NOW() WHERE verification_code = ? AND tenant_id = ?");
            $stmt->execute([$code, $tenantId]);
        } catch (\Throwable $e) {
            // Silent fail
        }
    }

    /**
     * Generate unique verification code
     */
    private static function generateVerificationCode(): string
    {
        return strtoupper(substr(bin2hex(random_bytes(8)), 0, 16));
    }

    /**
     * Get verification URL
     */
    private static function getVerificationUrl(string $code): string
    {
        $baseUrl = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix();
        return $baseUrl . '/verify/cert/' . $code;
    }
}
