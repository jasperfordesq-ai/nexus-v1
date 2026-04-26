<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * ReportExportService — Native Eloquent implementation for CSV/PDF report exports.
 *
 * Generates downloadable reports for admin analytics. Each report type queries
 * tenant-scoped data and returns either CSV content or PDF content with metadata.
 */
class ReportExportService
{
    private ?MunicipalImpactReportService $municipalImpactReportService;

    /**
     * Supported export report types.
     */
    private const SUPPORTED_TYPES = [
        'transactions'    => 'Transaction History',
        'members'         => 'Member Directory',
        'hours_summary'   => 'Hours Summary',
        'hours_category'  => 'Hours by Category',
        'events'          => 'Events Report',
        'listings'        => 'Listings Report',
        'inactive'        => 'Inactive Members',
        'social_value'    => 'Social Value (SROI)',
        'municipal_impact' => 'Municipal Impact Pack',
    ];

    public function __construct(?MunicipalImpactReportService $municipalImpactReportService = null)
    {
        $this->municipalImpactReportService = $municipalImpactReportService;
    }

    /**
     * Export a report as CSV.
     *
     * @param string $type     Report type key
     * @param int    $tenantId
     * @param array  $filters  ['date_from', 'date_to', 'status', 'days']
     * @return array ['success' => bool, 'csv' => string, 'filename' => string, 'message' => ?string]
     */
    public function export(string $type, int $tenantId, array $filters = []): array
    {
        $data = $this->getReportData($type, $tenantId, $filters);

        if (empty($data['rows'])) {
            return [
                'success'  => false,
                'message'  => __('svc_notifications_2.report_export.no_data'),
                'csv'      => '',
                'filename' => '',
            ];
        }

        $csv = $this->arrayToCsv($data['headers'], $data['rows']);
        $date = now()->format('Y-m-d');
        $filename = "{$type}_report_{$date}.csv";

        return [
            'success'  => true,
            'csv'      => $csv,
            'filename' => $filename,
            'rows'     => count($data['rows']),
        ];
    }

    /**
     * Send a CSV download response (used when called directly, not via controller).
     */
    public function sendCSVDownload(string $csv, string $filename): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo $csv;
    }

    /**
     * Get supported report types.
     *
     * @return array<string, string>  type_key => label
     */
    public function getSupportedTypes(): array
    {
        return self::SUPPORTED_TYPES;
    }

    /**
     * Export a report as PDF (simple text-based PDF).
     *
     * @param string $type
     * @param int    $tenantId
     * @param array  $filters
     * @return array ['success' => bool, 'pdf' => string, 'filename' => string, 'message' => ?string]
     */
    public function exportPdf(string $type, int $tenantId, array $filters = []): array
    {
        $data = $this->getReportData($type, $tenantId, $filters);

        if (empty($data['rows'])) {
            return [
                'success'  => false,
                'message'  => __('svc_notifications_2.report_export.no_data'),
                'pdf'      => '',
                'filename' => '',
            ];
        }

        $pdf = $this->generateSimplePdf($type, $data['headers'], $data['rows']);
        $date = now()->format('Y-m-d');
        $filename = "{$type}_report_{$date}.pdf";

        return [
            'success'  => true,
            'pdf'      => $pdf,
            'filename' => $filename,
            'rows'     => count($data['rows']),
        ];
    }

    /**
     * Send a PDF download response.
     */
    public function sendPdfDownload(string $pdf, string $filename): void
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
    }

    // =========================================================================
    // DATA FETCHERS
    // =========================================================================

    /**
     * Get report data (headers + rows) for a given report type.
     */
    private function getReportData(string $type, int $tenantId, array $filters): array
    {
        return match ($type) {
            'transactions'   => $this->getTransactionData($tenantId, $filters),
            'members'        => $this->getMemberData($tenantId, $filters),
            'hours_summary'  => $this->getHoursSummaryData($tenantId, $filters),
            'hours_category' => $this->getHoursCategoryData($tenantId, $filters),
            'events'         => $this->getEventData($tenantId, $filters),
            'listings'       => $this->getListingData($tenantId, $filters),
            'inactive'       => $this->getInactiveData($tenantId, $filters),
            'social_value'   => $this->getSocialValueData($tenantId, $filters),
            'municipal_impact' => $this->municipalImpactReportService()->exportData($tenantId, $filters),
            default          => ['headers' => [], 'rows' => []],
        };
    }

    private function municipalImpactReportService(): MunicipalImpactReportService
    {
        if (!$this->municipalImpactReportService) {
            $this->municipalImpactReportService = app(MunicipalImpactReportService::class);
        }

        return $this->municipalImpactReportService;
    }

    private function getTransactionData(int $tenantId, array $filters): array
    {
        $dateConditions = $this->buildDateConditions($filters, 't.created_at');
        $dateBindings = $this->buildDateBindings($filters);

        $query = "SELECT
                t.id, t.created_at, t.amount, t.description, t.status, t.transaction_type,
                s.name AS sender_name, s.email AS sender_email,
                r.name AS receiver_name, r.email AS receiver_email,
                c.name AS category_name
            FROM transactions t
            LEFT JOIN users s ON s.id = t.sender_id AND s.tenant_id = t.tenant_id
            LEFT JOIN users r ON r.id = t.receiver_id AND r.tenant_id = t.tenant_id
            LEFT JOIN listings l ON l.id = t.listing_id AND l.tenant_id = t.tenant_id
            LEFT JOIN categories c ON c.id = l.category_id AND c.tenant_id = t.tenant_id
            WHERE t.tenant_id = ?
            {$dateConditions}
            ORDER BY t.created_at DESC
            LIMIT 10000";

        $rows = DB::select($query, array_merge([$tenantId], $dateBindings));

        return [
            'headers' => ['ID', 'Date', 'Hours', 'Description', 'Status', 'Type', 'Provider', 'Provider Email', 'Receiver', 'Receiver Email', 'Category'],
            'rows' => array_map(fn($r) => [
                $r->id,
                $r->created_at,
                $r->amount,
                $r->description ?? '',
                $r->status,
                $r->transaction_type ?? 'transfer',
                $r->sender_name ?? '',
                $r->sender_email ?? '',
                $r->receiver_name ?? '',
                $r->receiver_email ?? '',
                $r->category_name ?? 'Uncategorized',
            ], $rows),
        ];
    }

    private function getMemberData(int $tenantId, array $filters): array
    {
        $statusCondition = '';
        $bindings = [$tenantId];

        if (!empty($filters['status'])) {
            $statusCondition = ' AND u.status = ?';
            $bindings[] = $filters['status'];
        }

        $query = "SELECT
                u.id, u.name, u.email, u.phone, u.location, u.role, u.status,
                u.is_approved, u.created_at, u.last_login_at, u.balance, u.xp, u.level
            FROM users u
            WHERE u.tenant_id = ? {$statusCondition}
            ORDER BY u.created_at DESC
            LIMIT 10000";

        $rows = DB::select($query, $bindings);

        return [
            'headers' => ['ID', 'Name', 'Email', 'Phone', 'Location', 'Role', 'Status', 'Approved', 'Joined', 'Last Login', 'Balance', 'XP', 'Level'],
            'rows' => array_map(fn($r) => [
                $r->id,
                $r->name ?? '',
                $r->email ?? '',
                $r->phone ?? '',
                $r->location ?? '',
                $r->role ?? 'member',
                $r->status ?? 'active',
                $r->is_approved ? 'Yes' : 'No',
                $r->created_at,
                $r->last_login_at ?? 'Never',
                $r->balance ?? 0,
                $r->xp ?? 0,
                $r->level ?? 1,
            ], $rows),
        ];
    }

    private function getHoursSummaryData(int $tenantId, array $filters): array
    {
        $dateConditions = $this->buildDateConditions($filters);
        $dateBindings = $this->buildDateBindings($filters);

        $query = "SELECT
                DATE_FORMAT(created_at, '%Y-%m') AS period,
                COALESCE(SUM(amount), 0) AS total_hours,
                COUNT(*) AS transaction_count,
                COUNT(DISTINCT sender_id) AS unique_providers,
                COUNT(DISTINCT receiver_id) AS unique_receivers
            FROM transactions
            WHERE tenant_id = ? AND status = 'completed'
            {$dateConditions}
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY period ASC";

        $rows = DB::select($query, array_merge([$tenantId], $dateBindings));

        return [
            'headers' => ['Period', 'Total Hours', 'Transactions', 'Unique Providers', 'Unique Receivers'],
            'rows' => array_map(fn($r) => [
                $r->period,
                round((float) $r->total_hours, 2),
                $r->transaction_count,
                $r->unique_providers,
                $r->unique_receivers,
            ], $rows),
        ];
    }

    private function getHoursCategoryData(int $tenantId, array $filters): array
    {
        $dateConditions = $this->buildDateConditions($filters, 't.created_at');
        $dateBindings = $this->buildDateBindings($filters);

        $query = "SELECT
                c.name AS category_name,
                COALESCE(SUM(t.amount), 0) AS total_hours,
                COUNT(*) AS transaction_count,
                COUNT(DISTINCT t.sender_id) AS unique_providers,
                COUNT(DISTINCT t.receiver_id) AS unique_receivers
            FROM transactions t
            LEFT JOIN listings l ON l.id = t.listing_id AND l.tenant_id = t.tenant_id
            LEFT JOIN categories c ON c.id = l.category_id AND c.tenant_id = t.tenant_id
            WHERE t.tenant_id = ? AND t.status = 'completed'
            {$dateConditions}
            GROUP BY c.name
            ORDER BY total_hours DESC";

        $rows = DB::select($query, array_merge([$tenantId], $dateBindings));

        return [
            'headers' => ['Category', 'Total Hours', 'Transactions', 'Unique Providers', 'Unique Receivers'],
            'rows' => array_map(fn($r) => [
                $r->category_name ?? 'Uncategorized',
                round((float) $r->total_hours, 2),
                $r->transaction_count,
                $r->unique_providers,
                $r->unique_receivers,
            ], $rows),
        ];
    }

    private function getEventData(int $tenantId, array $filters): array
    {
        $dateConditions = $this->buildDateConditions($filters, 'e.start_time');
        $dateBindings = $this->buildDateBindings($filters);

        $query = "SELECT
                e.id, e.title, e.location, e.start_time, e.end_time,
                e.max_attendees, e.status,
                u.name AS organizer_name,
                (SELECT COUNT(*) FROM event_attendees ea WHERE ea.event_id = e.id) AS attendee_count
            FROM events e
            LEFT JOIN users u ON u.id = e.user_id AND u.tenant_id = e.tenant_id
            WHERE e.tenant_id = ?
            {$dateConditions}
            ORDER BY e.start_time DESC
            LIMIT 10000";

        $rows = DB::select($query, array_merge([$tenantId], $dateBindings));

        return [
            'headers' => ['ID', 'Title', 'Location', 'Start Time', 'End Time', 'Max Attendees', 'Attendees', 'Status', 'Organizer'],
            'rows' => array_map(fn($r) => [
                $r->id,
                $r->title ?? '',
                $r->location ?? '',
                $r->start_time,
                $r->end_time ?? '',
                $r->max_attendees ?? 'Unlimited',
                $r->attendee_count ?? 0,
                $r->status ?? 'active',
                $r->organizer_name ?? '',
            ], $rows),
        ];
    }

    private function getListingData(int $tenantId, array $filters): array
    {
        $dateConditions = $this->buildDateConditions($filters, 'l.created_at');
        $dateBindings = $this->buildDateBindings($filters);

        $statusCondition = '';
        $statusBindings = [];
        if (!empty($filters['status'])) {
            $statusCondition = ' AND l.status = ?';
            $statusBindings[] = $filters['status'];
        }

        $query = "SELECT
                l.id, l.title, l.type, l.status, l.location, l.created_at,
                l.price, l.view_count, l.contact_count,
                u.name AS owner_name,
                c.name AS category_name
            FROM listings l
            LEFT JOIN users u ON u.id = l.user_id AND u.tenant_id = l.tenant_id
            LEFT JOIN categories c ON c.id = l.category_id AND c.tenant_id = l.tenant_id
            WHERE l.tenant_id = ?
            {$dateConditions}
            {$statusCondition}
            ORDER BY l.created_at DESC
            LIMIT 10000";

        $rows = DB::select($query, array_merge([$tenantId], $dateBindings, $statusBindings));

        return [
            'headers' => ['ID', 'Title', 'Type', 'Status', 'Category', 'Location', 'Hours Estimate', 'Views', 'Contacts', 'Owner', 'Created'],
            'rows' => array_map(fn($r) => [
                $r->id,
                $r->title ?? '',
                $r->type ?? '',
                $r->status ?? '',
                $r->category_name ?? 'Uncategorized',
                $r->location ?? '',
                $r->price ?? 0,
                $r->view_count ?? 0,
                $r->contact_count ?? 0,
                $r->owner_name ?? '',
                $r->created_at,
            ], $rows),
        ];
    }

    private function getInactiveData(int $tenantId, array $filters): array
    {
        $days = (int) ($filters['days'] ?? 90);

        $query = "SELECT
                u.id, u.name, u.email, u.phone, u.location, u.role,
                u.created_at, u.last_login_at, u.last_active_at, u.balance,
                f.flag_type, f.flagged_at, f.last_activity_at AS flag_last_activity
            FROM users u
            LEFT JOIN member_activity_flags f ON f.user_id = u.id AND f.tenant_id = u.tenant_id AND f.resolved_at IS NULL
            WHERE u.tenant_id = ? AND u.is_approved = 1
                AND (u.last_active_at IS NULL OR u.last_active_at < DATE_SUB(NOW(), INTERVAL ? DAY))
            ORDER BY u.last_active_at ASC
            LIMIT 10000";

        $rows = DB::select($query, [$tenantId, $days]);

        return [
            'headers' => ['ID', 'Name', 'Email', 'Phone', 'Location', 'Role', 'Joined', 'Last Login', 'Last Active', 'Balance', 'Flag Type', 'Flagged At'],
            'rows' => array_map(fn($r) => [
                $r->id,
                $r->name ?? '',
                $r->email ?? '',
                $r->phone ?? '',
                $r->location ?? '',
                $r->role ?? 'member',
                $r->created_at,
                $r->last_login_at ?? 'Never',
                $r->last_active_at ?? 'Never',
                $r->balance ?? 0,
                $r->flag_type ?? 'None',
                $r->flagged_at ?? '',
            ], $rows),
        ];
    }

    private function getSocialValueData(int $tenantId, array $filters): array
    {
        $dateConditions = $this->buildDateConditions($filters, 't.created_at');
        $dateBindings = $this->buildDateBindings($filters);

        // Get SROI config
        $config = DB::table('social_value_config')
            ->where('tenant_id', $tenantId)
            ->first();

        $hourValue = (float) ($config->hour_value_amount ?? 15.00);
        $multiplier = (float) ($config->social_multiplier ?? 3.50);
        $currency = $config->hour_value_currency ?? 'GBP';

        $query = "SELECT
                DATE_FORMAT(t.created_at, '%Y-%m') AS period,
                COALESCE(SUM(t.amount), 0) AS total_hours,
                COUNT(*) AS transaction_count,
                COUNT(DISTINCT t.sender_id) AS unique_members
            FROM transactions t
            WHERE t.tenant_id = ? AND t.status = 'completed'
            {$dateConditions}
            GROUP BY DATE_FORMAT(t.created_at, '%Y-%m')
            ORDER BY period ASC";

        $rows = DB::select($query, array_merge([$tenantId], $dateBindings));

        return [
            'headers' => ['Period', 'Total Hours', 'Transactions', 'Unique Members', "Direct Value ({$currency})", "Social Value ({$currency})", "Total Value ({$currency})"],
            'rows' => array_map(function ($r) use ($hourValue, $multiplier) {
                $hours = (float) $r->total_hours;
                $direct = round($hours * $hourValue, 2);
                $social = round($direct * $multiplier, 2);
                return [
                    $r->period,
                    round($hours, 2),
                    $r->transaction_count,
                    $r->unique_members,
                    $direct,
                    $social,
                    round($direct + $social, 2),
                ];
            }, $rows),
        ];
    }

    // =========================================================================
    // CSV / PDF GENERATION
    // =========================================================================

    /**
     * Convert headers + rows into a CSV string.
     */
    private function arrayToCsv(array $headers, array $rows): string
    {
        $output = fopen('php://temp', 'r+');

        // BOM for Excel UTF-8 compatibility
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, $headers);

        foreach ($rows as $row) {
            // CSV injection prevention: sanitize each cell value
            $sanitized = array_map([$this, 'sanitizeCsvCell'], $row);
            fputcsv($output, $sanitized);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Sanitize a CSV cell value to prevent formula injection.
     * Prefixes formula-trigger characters (=, +, -, @, tab, CR) with a single quote.
     */
    private function sanitizeCsvCell(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        if (preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'" . $value;
        }
        return $value;
    }

    /**
     * Generate a simple text-based PDF.
     *
     * Produces a minimal valid PDF without external dependencies.
     * For production use, a library like DOMPDF or TCPDF would be preferred.
     */
    private function generateSimplePdf(string $type, array $headers, array $rows): string
    {
        $title = self::SUPPORTED_TYPES[$type] ?? 'Report';
        $date = now()->format('Y-m-d H:i');
        $rowCount = count($rows);

        // Build text content
        $lines = [];
        $lines[] = $title . ' Report';
        $lines[] = 'Generated: ' . $date;
        $lines[] = 'Total Records: ' . $rowCount;
        $lines[] = str_repeat('-', 80);
        $lines[] = implode(' | ', $headers);
        $lines[] = str_repeat('-', 80);

        foreach (array_slice($rows, 0, 500) as $row) {
            $lines[] = implode(' | ', array_map(fn($v) => (string) $v, $row));
        }

        if ($rowCount > 500) {
            $lines[] = '';
            $lines[] = "... and " . ($rowCount - 500) . " more rows (use CSV export for full data)";
        }

        $text = implode("\n", $lines);

        // Generate minimal PDF
        return $this->buildMinimalPdf($text);
    }

    /**
     * Build a minimal valid PDF 1.4 document containing the given text.
     */
    private function buildMinimalPdf(string $text): string
    {
        // Escape special PDF characters in text
        $escapedText = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);

        // Split text into lines for Tj operators
        $lines = explode("\n", $escapedText);
        $textOps = '';
        foreach ($lines as $line) {
            $textOps .= "({$line}) Tj T* \n";
        }

        $stream = "BT\n/F1 9 Tf\n50 750 Td\n12 TL\n{$textOps}ET";
        $streamLength = strlen($stream);

        $objects = [];

        // Object 1: Catalog
        $objects[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj";

        // Object 2: Pages
        $objects[2] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj";

        // Object 3: Page
        $objects[3] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj";

        // Object 4: Content stream
        $objects[4] = "4 0 obj\n<< /Length {$streamLength} >>\nstream\n{$stream}\nendstream\nendobj";

        // Object 5: Font
        $objects[5] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>\nendobj";

        // Build PDF
        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $num => $obj) {
            $offsets[$num] = strlen($pdf);
            $pdf .= $obj . "\n";
        }

        // Xref
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        // Trailer
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Build SQL date condition fragment.
     */
    private function buildDateConditions(array $filters, string $column = 'created_at'): string
    {
        $conditions = '';
        if (!empty($filters['date_from'])) {
            $conditions .= " AND {$column} >= ?";
        }
        if (!empty($filters['date_to'])) {
            $conditions .= " AND {$column} <= ?";
        }
        return $conditions;
    }

    /**
     * Build bindings array for date conditions.
     */
    private function buildDateBindings(array $filters): array
    {
        $bindings = [];
        if (!empty($filters['date_from'])) {
            $bindings[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $bindings[] = $filters['date_to'];
        }
        return $bindings;
    }
}
