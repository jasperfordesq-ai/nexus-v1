<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\OrgTransaction;
use Nexus\Models\OrgMember;

/**
 * TransactionExportService
 *
 * Handles exporting transaction data to CSV and other formats.
 */
class TransactionExportService
{
    /**
     * Export organization transactions to CSV
     *
     * @param int $organizationId Organization ID
     * @param int $userId User requesting (for permission check)
     * @param array $filters Optional filters: startDate, endDate, type, status
     * @return array ['success' => bool, 'csv' => string|null, 'filename' => string|null, 'message' => string|null]
     */
    public static function exportOrgTransactionsCSV($organizationId, $userId, $filters = [])
    {
        // Check permission
        if (!OrgMember::isAdmin($organizationId, $userId)) {
            return ['success' => false, 'message' => 'Access denied. Admin rights required.'];
        }

        $tenantId = TenantContext::getId();

        // Build query
        $sql = "SELECT
                    ot.id,
                    ot.created_at,
                    ot.amount,
                    ot.description,
                    ot.sender_type,
                    ot.sender_id,
                    ot.receiver_type,
                    ot.receiver_id,
                    CASE
                        WHEN ot.sender_type = 'user' THEN CONCAT(su.first_name, ' ', su.last_name)
                        ELSE 'Organization'
                    END as sender_name,
                    CASE
                        WHEN ot.receiver_type = 'user' THEN CONCAT(ru.first_name, ' ', ru.last_name)
                        ELSE 'Organization'
                    END as receiver_name,
                    CASE
                        WHEN ot.sender_type = 'organization' THEN 'Outgoing'
                        ELSE 'Incoming'
                    END as direction
                FROM org_transactions ot
                LEFT JOIN users su ON ot.sender_type = 'user' AND ot.sender_id = su.id
                LEFT JOIN users ru ON ot.receiver_type = 'user' AND ot.receiver_id = ru.id
                WHERE ot.tenant_id = ? AND ot.organization_id = ?";

        $params = [$tenantId, $organizationId];

        // Apply date filters
        if (!empty($filters['startDate'])) {
            $sql .= " AND ot.created_at >= ?";
            $params[] = $filters['startDate'] . ' 00:00:00';
        }

        if (!empty($filters['endDate'])) {
            $sql .= " AND ot.created_at <= ?";
            $params[] = $filters['endDate'] . ' 23:59:59';
        }

        // Apply direction filter
        if (!empty($filters['direction'])) {
            if ($filters['direction'] === 'incoming') {
                $sql .= " AND ot.receiver_type = 'organization'";
            } elseif ($filters['direction'] === 'outgoing') {
                $sql .= " AND ot.sender_type = 'organization'";
            }
        }

        $sql .= " ORDER BY ot.created_at DESC";

        // Apply limit if specified
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }

        $transactions = Database::query($sql, $params)->fetchAll();

        if (empty($transactions)) {
            return ['success' => false, 'message' => 'No transactions found for the selected criteria'];
        }

        // Build CSV
        $csv = self::buildCSV($transactions);

        // Generate filename
        $dateRange = '';
        if (!empty($filters['startDate']) && !empty($filters['endDate'])) {
            $dateRange = '_' . $filters['startDate'] . '_to_' . $filters['endDate'];
        } else {
            $dateRange = '_' . date('Y-m-d');
        }

        $filename = "org_transactions_{$organizationId}{$dateRange}.csv";

        return [
            'success' => true,
            'csv' => $csv,
            'filename' => $filename,
            'count' => count($transactions)
        ];
    }

    /**
     * Export transfer requests to CSV
     */
    public static function exportTransferRequestsCSV($organizationId, $userId, $filters = [])
    {
        // Check permission
        if (!OrgMember::isAdmin($organizationId, $userId)) {
            return ['success' => false, 'message' => 'Access denied. Admin rights required.'];
        }

        $tenantId = TenantContext::getId();

        $sql = "SELECT
                    tr.id,
                    tr.created_at,
                    tr.amount,
                    tr.description,
                    tr.status,
                    CONCAT(req.first_name, ' ', req.last_name) as requester_name,
                    CONCAT(rec.first_name, ' ', rec.last_name) as recipient_name,
                    CONCAT(app.first_name, ' ', app.last_name) as approved_by_name,
                    tr.approved_at,
                    tr.rejection_reason
                FROM org_transfer_requests tr
                JOIN users req ON tr.requester_id = req.id
                JOIN users rec ON tr.recipient_id = rec.id
                LEFT JOIN users app ON tr.approved_by = app.id
                WHERE tr.tenant_id = ? AND tr.organization_id = ?";

        $params = [$tenantId, $organizationId];

        // Apply status filter
        if (!empty($filters['status'])) {
            $sql .= " AND tr.status = ?";
            $params[] = $filters['status'];
        }

        // Apply date filters
        if (!empty($filters['startDate'])) {
            $sql .= " AND tr.created_at >= ?";
            $params[] = $filters['startDate'] . ' 00:00:00';
        }

        if (!empty($filters['endDate'])) {
            $sql .= " AND tr.created_at <= ?";
            $params[] = $filters['endDate'] . ' 23:59:59';
        }

        $sql .= " ORDER BY tr.created_at DESC";

        $requests = Database::query($sql, $params)->fetchAll();

        if (empty($requests)) {
            return ['success' => false, 'message' => 'No transfer requests found for the selected criteria'];
        }

        // Build CSV with custom headers
        $headers = ['ID', 'Date', 'Amount', 'Description', 'Status', 'Requester', 'Recipient', 'Approved By', 'Approved At', 'Rejection Reason'];
        $rows = [];
        foreach ($requests as $r) {
            $rows[] = [
                $r['id'],
                $r['created_at'],
                $r['amount'],
                $r['description'],
                ucfirst($r['status']),
                $r['requester_name'],
                $r['recipient_name'],
                $r['approved_by_name'] ?? '',
                $r['approved_at'] ?? '',
                $r['rejection_reason'] ?? ''
            ];
        }

        $csv = self::buildCSVFromArray($headers, $rows);

        $filename = "transfer_requests_{$organizationId}_" . date('Y-m-d') . ".csv";

        return [
            'success' => true,
            'csv' => $csv,
            'filename' => $filename,
            'count' => count($requests)
        ];
    }

    /**
     * Export member list to CSV
     */
    public static function exportMembersCSV($organizationId, $userId)
    {
        // Check permission
        if (!OrgMember::isAdmin($organizationId, $userId)) {
            return ['success' => false, 'message' => 'Access denied. Admin rights required.'];
        }

        $members = OrgMember::getMembers($organizationId);

        if (empty($members)) {
            return ['success' => false, 'message' => 'No members found'];
        }

        $headers = ['Member ID', 'Name', 'Email', 'Role', 'Status', 'Joined Date'];
        $rows = [];
        foreach ($members as $m) {
            $rows[] = [
                $m['user_id'],
                $m['display_name'],
                $m['email'],
                ucfirst($m['role']),
                ucfirst($m['status']),
                $m['created_at'] ?? ''
            ];
        }

        $csv = self::buildCSVFromArray($headers, $rows);

        $filename = "org_members_{$organizationId}_" . date('Y-m-d') . ".csv";

        return [
            'success' => true,
            'csv' => $csv,
            'filename' => $filename,
            'count' => count($members)
        ];
    }

    /**
     * Build CSV string from transaction data
     */
    private static function buildCSV($transactions)
    {
        $headers = ['Transaction ID', 'Date', 'Amount', 'Direction', 'From', 'To', 'Description'];

        $rows = [];
        foreach ($transactions as $t) {
            $rows[] = [
                $t['id'],
                $t['created_at'],
                number_format($t['amount'], 2),
                $t['direction'],
                $t['sender_name'],
                $t['receiver_name'],
                $t['description']
            ];
        }

        return self::buildCSVFromArray($headers, $rows);
    }

    /**
     * Build CSV string from headers and rows arrays
     */
    private static function buildCSVFromArray($headers, $rows)
    {
        $output = fopen('php://temp', 'r+');

        // Add BOM for Excel UTF-8 compatibility
        fwrite($output, "\xEF\xBB\xBF");

        // Write headers
        fputcsv($output, $headers);

        // Write data rows
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Send CSV as download response
     */
    public static function sendCSVDownload($csv, $filename)
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $csv;
        exit;
    }
}
