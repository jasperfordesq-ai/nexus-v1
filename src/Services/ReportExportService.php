<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;

/**
 * ReportExportService
 *
 * Generic CSV export service for all admin report types.
 * Supports: member list, transaction history, event attendance,
 * volunteer hours, skill inventory, hours by category, inactive members.
 *
 * Each export method returns a standardized array:
 * ['success' => bool, 'csv' => string, 'filename' => string, 'count' => int]
 *
 * All exports are tenant-scoped.
 */
class ReportExportService
{
    /**
     * Export a report by type
     *
     * @param string $type Report type
     * @param int $tenantId
     * @param array $filters Optional filters (date_from, date_to, etc.)
     * @return array ['success' => bool, 'csv' => string|null, 'filename' => string|null, 'count' => int, 'message' => string|null]
     */
    public static function export(string $type, int $tenantId, array $filters = []): array
    {
        return match ($type) {
            'members' => self::exportMembers($tenantId, $filters),
            'transactions' => self::exportTransactions($tenantId, $filters),
            'events' => self::exportEventAttendance($tenantId, $filters),
            'volunteer_hours' => self::exportVolunteerHours($tenantId, $filters),
            'skills' => self::exportSkillInventory($tenantId, $filters),
            'hours_by_category' => self::exportHoursByCategory($tenantId, $filters),
            'inactive_members' => self::exportInactiveMembers($tenantId, $filters),
            'social_value' => self::exportSocialValue($tenantId, $filters),
            default => ['success' => false, 'csv' => null, 'filename' => null, 'count' => 0, 'message' => "Unknown report type: {$type}"],
        };
    }

    /**
     * Export member list
     */
    private static function exportMembers(int $tenantId, array $filters): array
    {
        $sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.role,
                       u.status, u.created_at, u.last_login_at,
                       u.city, u.country
                FROM users u
                WHERE u.tenant_id = ?";
        $params = [$tenantId];

        if (!empty($filters['status'])) {
            $sql .= " AND u.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND u.created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND u.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $sql .= " ORDER BY u.created_at DESC";

        $rows = Database::query($sql, $params)->fetchAll();

        if (empty($rows)) {
            return self::emptyResult('No members found');
        }

        $headers = ['ID', 'First Name', 'Last Name', 'Email', 'Role', 'Status', 'Registered', 'Last Login', 'City', 'Country'];
        $data = array_map(function ($r) {
            return [
                $r['id'],
                $r['first_name'],
                $r['last_name'],
                $r['email'],
                ucfirst($r['role'] ?? 'member'),
                ucfirst($r['status'] ?? 'active'),
                $r['created_at'],
                $r['last_login_at'] ?? 'Never',
                $r['city'] ?? '',
                $r['country'] ?? '',
            ];
        }, $rows);

        return self::buildResult($headers, $data, 'members', $filters);
    }

    /**
     * Export transaction history
     */
    private static function exportTransactions(int $tenantId, array $filters): array
    {
        $sql = "SELECT t.id, t.created_at, t.amount, t.description, t.transaction_type, t.status,
                       CONCAT(s.first_name, ' ', s.last_name) as sender_name,
                       CONCAT(r.first_name, ' ', r.last_name) as receiver_name,
                       tc.name as category_name
                FROM transactions t
                LEFT JOIN users s ON t.sender_id = s.id
                LEFT JOIN users r ON t.receiver_id = r.id
                LEFT JOIN transaction_categories tc ON t.category_id = tc.id
                WHERE t.tenant_id = ?";
        $params = [$tenantId];

        if (!empty($filters['date_from'])) {
            $sql .= " AND t.created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND t.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        if (!empty($filters['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filters['status'];
        }

        $sql .= " ORDER BY t.created_at DESC";

        try {
            $rows = Database::query($sql, $params)->fetchAll();
        } catch (\Exception $e) {
            // Fallback without category join
            $sql = "SELECT t.id, t.created_at, t.amount, t.description, t.transaction_type, t.status,
                           CONCAT(s.first_name, ' ', s.last_name) as sender_name,
                           CONCAT(r.first_name, ' ', r.last_name) as receiver_name
                    FROM transactions t
                    LEFT JOIN users s ON t.sender_id = s.id
                    LEFT JOIN users r ON t.receiver_id = r.id
                    WHERE t.tenant_id = ?";
            $params2 = [$tenantId];

            if (!empty($filters['date_from'])) {
                $sql .= " AND t.created_at >= ?";
                $params2[] = $filters['date_from'] . ' 00:00:00';
            }
            if (!empty($filters['date_to'])) {
                $sql .= " AND t.created_at <= ?";
                $params2[] = $filters['date_to'] . ' 23:59:59';
            }

            $sql .= " ORDER BY t.created_at DESC";
            $rows = Database::query($sql, $params2)->fetchAll();
        }

        if (empty($rows)) {
            return self::emptyResult('No transactions found');
        }

        $headers = ['ID', 'Date', 'Amount (Hours)', 'From', 'To', 'Category', 'Type', 'Status', 'Description'];
        $data = array_map(function ($r) {
            return [
                $r['id'],
                $r['created_at'],
                number_format((float) $r['amount'], 2),
                $r['sender_name'],
                $r['receiver_name'],
                $r['category_name'] ?? ucfirst($r['transaction_type'] ?? 'transfer'),
                ucfirst($r['transaction_type'] ?? 'transfer'),
                ucfirst($r['status'] ?? 'completed'),
                $r['description'] ?? '',
            ];
        }, $rows);

        return self::buildResult($headers, $data, 'transactions', $filters);
    }

    /**
     * Export event attendance
     */
    private static function exportEventAttendance(int $tenantId, array $filters): array
    {
        try {
            $sql = "SELECT e.id as event_id, e.title, e.start_time, e.end_time, e.location,
                           CONCAT(organizer.first_name, ' ', organizer.last_name) as organizer_name,
                           er.status as rsvp_status,
                           CONCAT(attendee.first_name, ' ', attendee.last_name) as attendee_name,
                           attendee.email as attendee_email,
                           er.created_at as rsvp_date
                    FROM events e
                    LEFT JOIN event_rsvps er ON e.id = er.event_id AND er.tenant_id = ?
                    LEFT JOIN users attendee ON er.user_id = attendee.id
                    LEFT JOIN users organizer ON e.user_id = organizer.id
                    WHERE e.tenant_id = ?";
            $params = [$tenantId, $tenantId];

            if (!empty($filters['date_from'])) {
                $sql .= " AND e.start_time >= ?";
                $params[] = $filters['date_from'] . ' 00:00:00';
            }
            if (!empty($filters['date_to'])) {
                $sql .= " AND e.start_time <= ?";
                $params[] = $filters['date_to'] . ' 23:59:59';
            }

            $sql .= " ORDER BY e.start_time DESC, attendee_name ASC";

            $rows = Database::query($sql, $params)->fetchAll();
        } catch (\Exception $e) {
            return self::emptyResult('Events table not available');
        }

        if (empty($rows)) {
            return self::emptyResult('No event attendance data found');
        }

        $headers = ['Event ID', 'Event Title', 'Start Time', 'End Time', 'Location', 'Organizer', 'Attendee', 'Attendee Email', 'RSVP Status', 'RSVP Date'];
        $data = array_map(function ($r) {
            return [
                $r['event_id'],
                $r['title'],
                $r['start_time'],
                $r['end_time'] ?? '',
                $r['location'] ?? '',
                $r['organizer_name'] ?? '',
                $r['attendee_name'] ?? '',
                $r['attendee_email'] ?? '',
                ucfirst($r['rsvp_status'] ?? ''),
                $r['rsvp_date'] ?? '',
            ];
        }, $rows);

        return self::buildResult($headers, $data, 'event_attendance', $filters);
    }

    /**
     * Export volunteer hours summary per member
     */
    private static function exportVolunteerHours(int $tenantId, array $filters): array
    {
        $sql = "SELECT u.id, u.first_name, u.last_name, u.email,
                       COALESCE(SUM(CASE WHEN t.sender_id = u.id THEN t.amount ELSE 0 END), 0) as hours_given,
                       COALESCE(SUM(CASE WHEN t.receiver_id = u.id THEN t.amount ELSE 0 END), 0) as hours_received,
                       COUNT(DISTINCT t.id) as transaction_count
                FROM users u
                LEFT JOIN transactions t ON (t.sender_id = u.id OR t.receiver_id = u.id)
                    AND t.tenant_id = ? AND t.status = 'completed'";
        $params = [$tenantId];

        $dateConditions = [];
        if (!empty($filters['date_from'])) {
            $dateConditions[] = "t.created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $dateConditions[] = "t.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($dateConditions)) {
            $sql .= " AND " . implode(' AND ', $dateConditions);
        }

        $sql .= " WHERE u.tenant_id = ? AND u.status = 'active'
                  GROUP BY u.id, u.first_name, u.last_name, u.email
                  HAVING transaction_count > 0
                  ORDER BY (hours_given + hours_received) DESC";
        $params[] = $tenantId;

        $rows = Database::query($sql, $params)->fetchAll();

        if (empty($rows)) {
            return self::emptyResult('No volunteer hours data found');
        }

        $headers = ['ID', 'First Name', 'Last Name', 'Email', 'Hours Given', 'Hours Received', 'Net Balance', 'Total Transactions'];
        $data = array_map(function ($r) {
            $given = round((float) $r['hours_given'], 2);
            $received = round((float) $r['hours_received'], 2);
            return [
                $r['id'],
                $r['first_name'],
                $r['last_name'],
                $r['email'],
                number_format($given, 2),
                number_format($received, 2),
                number_format($received - $given, 2),
                $r['transaction_count'],
            ];
        }, $rows);

        return self::buildResult($headers, $data, 'volunteer_hours', $filters);
    }

    /**
     * Export skill inventory
     */
    private static function exportSkillInventory(int $tenantId, array $filters): array
    {
        try {
            $rows = Database::query(
                "SELECT us.skill_name,
                        CONCAT(u.first_name, ' ', u.last_name) as member_name,
                        u.email,
                        us.is_offering, us.is_requesting, us.proficiency,
                        (SELECT COUNT(*) FROM skill_endorsements se
                         WHERE se.endorsed_id = u.id AND se.skill_name = us.skill_name AND se.tenant_id = ?) as endorsements
                 FROM user_skills us
                 JOIN users u ON us.user_id = u.id
                 WHERE us.tenant_id = ?
                 ORDER BY us.skill_name ASC, member_name ASC",
                [$tenantId, $tenantId]
            )->fetchAll();
        } catch (\Exception $e) {
            // Fallback: use skills column from users table
            $rows = Database::query(
                "SELECT u.id, u.first_name, u.last_name, u.email, u.skills
                 FROM users u
                 WHERE u.tenant_id = ? AND u.status = 'active' AND u.skills IS NOT NULL AND u.skills != ''
                 ORDER BY u.first_name ASC",
                [$tenantId]
            )->fetchAll();

            if (empty($rows)) {
                return self::emptyResult('No skill data found');
            }

            // Flatten skills CSV into individual rows
            $headers = ['Member', 'Email', 'Skill'];
            $data = [];
            foreach ($rows as $r) {
                $skills = array_map('trim', explode(',', $r['skills']));
                foreach ($skills as $skill) {
                    if (!empty($skill)) {
                        $data[] = [
                            trim($r['first_name'] . ' ' . $r['last_name']),
                            $r['email'],
                            $skill,
                        ];
                    }
                }
            }

            return self::buildResult($headers, $data, 'skill_inventory', $filters);
        }

        if (empty($rows)) {
            return self::emptyResult('No skill data found');
        }

        $headers = ['Skill', 'Member', 'Email', 'Offering', 'Requesting', 'Proficiency', 'Endorsements'];
        $data = array_map(function ($r) {
            return [
                $r['skill_name'],
                $r['member_name'],
                $r['email'],
                $r['is_offering'] ? 'Yes' : 'No',
                $r['is_requesting'] ? 'Yes' : 'No',
                ucfirst($r['proficiency'] ?? 'Unknown'),
                $r['endorsements'],
            ];
        }, $rows);

        return self::buildResult($headers, $data, 'skill_inventory', $filters);
    }

    /**
     * Export hours by category
     */
    private static function exportHoursByCategory(int $tenantId, array $filters): array
    {
        $dateRange = [
            'from' => $filters['date_from'] ?? date('Y-m-d', strtotime('-12 months')),
            'to' => $filters['date_to'] ?? date('Y-m-d'),
        ];

        $data = HoursReportService::getHoursByCategory($tenantId, $dateRange);

        if (empty($data)) {
            return self::emptyResult('No hours data found');
        }

        $headers = ['Category', 'Total Hours', 'Transactions', 'Unique Givers', 'Unique Receivers'];
        $rows = array_map(function ($r) {
            return [
                $r['category'],
                number_format($r['total_hours'], 1),
                $r['transaction_count'],
                $r['unique_givers'],
                $r['unique_receivers'],
            ];
        }, $data);

        return self::buildResult($headers, $rows, 'hours_by_category', $filters);
    }

    /**
     * Export inactive members
     */
    private static function exportInactiveMembers(int $tenantId, array $filters): array
    {
        $days = (int) ($filters['days'] ?? 90);
        $result = InactiveMemberService::getInactiveMembers($tenantId, $days, null, 10000, 0);

        if (empty($result['members'])) {
            return self::emptyResult('No inactive members found');
        }

        $headers = ['ID', 'Name', 'Email', 'Flag Type', 'Days Inactive', 'Last Activity', 'Last Login', 'Member Since'];
        $data = array_map(function ($m) {
            return [
                $m['id'],
                $m['name'],
                $m['email'],
                ucfirst($m['flag_type']),
                $m['days_inactive'] ?? 'Unknown',
                $m['last_activity_at'] ?? 'Never',
                $m['last_login_at'] ?? 'Never',
                $m['member_since'],
            ];
        }, $result['members']);

        return self::buildResult($headers, $data, 'inactive_members', $filters);
    }

    /**
     * Export social value/SROI report
     */
    private static function exportSocialValue(int $tenantId, array $filters): array
    {
        $dateRange = [
            'from' => $filters['date_from'] ?? date('Y-m-d', strtotime('-12 months')),
            'to' => $filters['date_to'] ?? date('Y-m-d'),
        ];

        $report = SocialValueService::calculateSROI($tenantId, $dateRange);

        // Build summary rows
        $headers = ['Metric', 'Value'];
        $data = [
            ['Period', $report['period']['from'] . ' to ' . $report['period']['to']],
            ['Total Hours Exchanged', number_format($report['hours']['total_hours'], 1)],
            ['Total Transactions', $report['hours']['total_transactions']],
            ['Unique Givers', $report['hours']['unique_givers']],
            ['Unique Receivers', $report['hours']['unique_receivers']],
            ['Currency', $report['config']['currency']],
            ['Hourly Value', number_format($report['config']['hour_value'], 2)],
            ['Direct Monetary Value', number_format($report['valuation']['monetary_value'], 2)],
            ['Social Multiplier', $report['config']['social_multiplier'] . 'x'],
            ['Social Value', number_format($report['valuation']['social_value'], 2)],
            ['SROI Ratio', $report['valuation']['sroi_ratio']],
            ['Active Traders', $report['members']['active_traders']],
            ['Total Registered Members', $report['members']['total_registered']],
            ['Participation Rate', ($report['members']['participation_rate'] * 100) . '%'],
            ['New Members', $report['members']['new_members']],
            ['Total Events', $report['events']['total_events']],
            ['Total Attendees', $report['events']['total_attendees']],
            ['Unique Skills', $report['skills']['unique_skills']],
        ];

        return self::buildResult($headers, $data, 'social_value', $filters);
    }

    // ============================================
    // INTERNAL HELPERS
    // ============================================

    /**
     * Build CSV string and result array from headers and data
     */
    private static function buildResult(array $headers, array $data, string $reportType, array $filters): array
    {
        $csv = self::buildCSV($headers, $data);

        $dateRange = '';
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $dateRange = '_' . $filters['date_from'] . '_to_' . $filters['date_to'];
        } else {
            $dateRange = '_' . date('Y-m-d');
        }

        $filename = "{$reportType}{$dateRange}.csv";

        return [
            'success' => true,
            'csv' => $csv,
            'filename' => $filename,
            'count' => count($data),
            'message' => null,
        ];
    }

    /**
     * Build CSV string from headers and rows
     */
    private static function buildCSV(array $headers, array $rows): string
    {
        $output = fopen('php://temp', 'r+');

        // BOM for Excel UTF-8 compatibility
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, $headers);

        foreach ($rows as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Return empty result
     */
    private static function emptyResult(string $message): array
    {
        return [
            'success' => false,
            'csv' => null,
            'filename' => null,
            'count' => 0,
            'message' => $message,
        ];
    }

    /**
     * Send CSV as download response
     */
    public static function sendCSVDownload(string $csv, string $filename): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Content-Length: ' . strlen($csv));

        echo $csv;
        exit;
    }

    /**
     * Get list of supported export types
     */
    public static function getSupportedTypes(): array
    {
        return [
            'members' => 'Member List',
            'transactions' => 'Transaction History',
            'events' => 'Event Attendance',
            'volunteer_hours' => 'Volunteer Hours Summary',
            'skills' => 'Skill Inventory',
            'hours_by_category' => 'Hours by Category',
            'inactive_members' => 'Inactive Members',
            'social_value' => 'Social Value (SROI) Report',
        ];
    }

    // =========================================================================
    // PDF EXPORT
    // =========================================================================

    /**
     * Export a report as PDF
     *
     * @param string $type Report type (same as CSV export types)
     * @param int $tenantId
     * @param array $filters Optional filters
     * @return array ['success' => bool, 'pdf' => string|null, 'filename' => string|null, 'count' => int, 'message' => string|null]
     */
    public static function exportPdf(string $type, int $tenantId, array $filters = []): array
    {
        $csvResult = self::export($type, $tenantId, $filters);
        if (!$csvResult['success']) {
            return [
                'success' => false,
                'pdf' => null,
                'filename' => null,
                'count' => 0,
                'message' => $csvResult['message'],
            ];
        }

        $typeName = self::getSupportedTypes()[$type] ?? ucfirst($type);
        $tenantName = self::getTenantName($tenantId);

        $rows = self::csvToArray($csvResult['csv']);
        $headers = array_shift($rows) ?? [];

        $html = self::buildPdfHtml($typeName, $tenantName, $headers, $rows, $filters);

        try {
            $options = new DompdfOptions();
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'Helvetica');

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            $pdfContent = $dompdf->output();

            $dateRange = '';
            if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
                $dateRange = '_' . $filters['date_from'] . '_to_' . $filters['date_to'];
            } else {
                $dateRange = '_' . date('Y-m-d');
            }

            $filename = "{$type}{$dateRange}.pdf";

            return [
                'success' => true,
                'pdf' => $pdfContent,
                'filename' => $filename,
                'count' => count($rows),
                'message' => null,
            ];
        } catch (\Throwable $e) {
            error_log("[ReportExportService] PDF generation failed: " . $e->getMessage());
            return [
                'success' => false,
                'pdf' => null,
                'filename' => null,
                'count' => 0,
                'message' => 'PDF generation failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send PDF as download response
     */
    public static function sendPdfDownload(string $pdf, string $filename): void
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Content-Length: ' . strlen($pdf));

        echo $pdf;
        exit;
    }

    /**
     * Build HTML for PDF report
     */
    private static function buildPdfHtml(string $title, string $tenantName, array $headers, array $rows, array $filters): string
    {
        $dateStr = date('F j, Y');
        $filterInfo = '';
        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $from = $filters['date_from'] ?? 'start';
            $to = $filters['date_to'] ?? 'present';
            $filterInfo = "<p style=\"color:#666;font-size:11px;\">Date range: {$from} to {$to}</p>";
        }

        $rowCount = count($rows);

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #333; margin: 20px; }
h1 { font-size: 18px; color: #1a1a2e; margin-bottom: 4px; }
.meta { color: #666; font-size: 11px; margin-bottom: 12px; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; }
th { background: #1a1a2e; color: white; padding: 6px 8px; text-align: left; font-size: 9px; text-transform: uppercase; }
td { padding: 5px 8px; border-bottom: 1px solid #eee; font-size: 9px; }
tr:nth-child(even) td { background: #f8f9fa; }
.footer { margin-top: 20px; font-size: 8px; color: #999; text-align: center; }
</style>
</head>
<body>
<h1>{$title}</h1>
<div class="meta">
<p>{$tenantName} &mdash; Generated {$dateStr} &mdash; {$rowCount} records</p>
{$filterInfo}
</div>
<table>
<thead><tr>
HTML;

        foreach ($headers as $h) {
            $h = htmlspecialchars($h, ENT_QUOTES, 'UTF-8');
            $html .= "<th>{$h}</th>";
        }

        $html .= "</tr></thead><tbody>";

        foreach ($rows as $row) {
            $html .= "<tr>";
            foreach ($row as $cell) {
                $cell = htmlspecialchars((string)$cell, ENT_QUOTES, 'UTF-8');
                $html .= "<td>{$cell}</td>";
            }
            $html .= "</tr>";
        }

        $html .= <<<HTML
</tbody></table>
<div class="footer">Project NEXUS &mdash; Report generated automatically</div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Parse CSV string back into array of rows
     */
    private static function csvToArray(string $csv): array
    {
        // Remove BOM if present
        $csv = ltrim($csv, "\xEF\xBB\xBF");

        $rows = [];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Get tenant name for report header
     */
    private static function getTenantName(int $tenantId): string
    {
        try {
            $tenant = Database::query(
                "SELECT name FROM tenants WHERE id = ?",
                [$tenantId]
            )->fetch();
            return $tenant['name'] ?? "Tenant #{$tenantId}";
        } catch (\Throwable $e) {
            return "Tenant #{$tenantId}";
        }
    }
}
