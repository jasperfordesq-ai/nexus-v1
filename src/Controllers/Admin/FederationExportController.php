<?php

namespace Nexus\Controllers\Admin;

use Nexus\Core\Auth;
use Nexus\Core\Database;
use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationAuditService;

/**
 * FederationExportController
 *
 * Handles CSV export of federation data for admin reporting and backup.
 */
class FederationExportController
{
    /**
     * Export/Import dashboard
     * GET /admin/federation/data
     */
    public function index(): void
    {
        Auth::requireAdmin();
        $tenantId = TenantContext::getId();
        $db = Database::getInstance();

        // Get counts for export preview
        $stats = [];

        // Opted-in users count
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM federation_user_settings fus
            JOIN users u ON u.id = fus.user_id
            WHERE u.tenant_id = ? AND fus.federation_optin = 1
        ");
        $stmt->execute([$tenantId]);
        $stats['users'] = (int)$stmt->fetchColumn();

        // Partnerships count
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM federation_partnerships
            WHERE (tenant_id = ? OR partner_tenant_id = ?) AND status = 'active'
        ");
        $stmt->execute([$tenantId, $tenantId]);
        $stats['partnerships'] = (int)$stmt->fetchColumn();

        // Federated messages count
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM federation_messages
            WHERE sender_tenant_id = ? OR receiver_tenant_id = ?
        ");
        $stmt->execute([$tenantId, $tenantId]);
        $stats['messages'] = (int)$stmt->fetchColumn();

        // Federated transactions count
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM federation_transactions
            WHERE sender_tenant_id = ? OR receiver_tenant_id = ?
        ");
        $stmt->execute([$tenantId, $tenantId]);
        $stats['transactions'] = (int)$stmt->fetchColumn();

        // Audit logs count
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM federation_audit_log
            WHERE source_tenant_id = ? OR target_tenant_id = ?
        ");
        $stmt->execute([$tenantId, $tenantId]);
        $stats['audit_logs'] = (int)$stmt->fetchColumn();

        // Recent exports
        $stmt = $db->prepare("
            SELECT * FROM federation_exports
            WHERE tenant_id = ?
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$tenantId]);
        $recentExports = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        View::render('admin/federation/data', [
            'pageTitle' => 'Federation Data Management',
            'stats' => $stats,
            'recentExports' => $recentExports,
            'basePath' => TenantContext::getBasePath()
        ]);
    }

    /**
     * Export federated users
     * GET /admin/federation/export/users
     */
    public function exportUsers(): void
    {
        $user = Auth::requireAdmin();
        $tenantId = TenantContext::getId();
        $db = Database::getInstance();

        $stmt = $db->prepare("
            SELECT
                u.id,
                u.username,
                u.email,
                u.first_name,
                u.last_name,
                u.city,
                u.region,
                u.country,
                u.skills,
                fus.service_reach,
                fus.appear_in_federated_search as show_in_search,
                fus.profile_visible_federated as profile_visible,
                fus.show_location_federated as show_location,
                fus.show_skills_federated as show_skills,
                fus.messaging_enabled_federated as accepts_messages,
                fus.transactions_enabled_federated as accepts_transactions,
                fus.opted_in_at,
                fus.updated_at
            FROM users u
            JOIN federation_user_settings fus ON fus.user_id = u.id
            WHERE u.tenant_id = ? AND fus.federation_optin = 1
            ORDER BY u.last_name, u.first_name
        ");
        $stmt->execute([$tenantId]);
        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->logExport($tenantId, $user['id'], 'users', count($users));

        $this->sendCsv('federation_users_' . date('Y-m-d'), $users, [
            'id' => 'User ID',
            'username' => 'Username',
            'email' => 'Email',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'city' => 'City',
            'region' => 'Region',
            'country' => 'Country',
            'skills' => 'Skills',
            'service_reach' => 'Service Reach',
            'show_in_search' => 'Show in Search',
            'profile_visible' => 'Profile Visible',
            'show_location' => 'Show Location',
            'show_skills' => 'Show Skills',
            'accepts_messages' => 'Accepts Messages',
            'accepts_transactions' => 'Accepts Transactions',
            'opted_in_at' => 'Opted In Date',
            'updated_at' => 'Last Updated'
        ]);
    }

    /**
     * Export partnerships
     * GET /admin/federation/export/partnerships
     */
    public function exportPartnerships(): void
    {
        $user = Auth::requireAdmin();
        $tenantId = TenantContext::getId();
        $db = Database::getInstance();

        $stmt = $db->prepare("
            SELECT
                fp.id,
                t1.name as initiator_timebank,
                t2.name as partner_timebank,
                fp.status,
                fp.federation_level,
                fp.profiles_enabled,
                fp.messaging_enabled,
                fp.transactions_enabled,
                fp.listings_enabled,
                fp.requested_by,
                fp.created_at,
                fp.updated_at,
                fp.approved_at,
                fp.terminated_at,
                fp.termination_reason
            FROM federation_partnerships fp
            JOIN tenants t1 ON t1.id = fp.tenant_id
            JOIN tenants t2 ON t2.id = fp.partner_tenant_id
            WHERE fp.tenant_id = ? OR fp.partner_tenant_id = ?
            ORDER BY fp.created_at DESC
        ");
        $stmt->execute([$tenantId, $tenantId]);
        $partnerships = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->logExport($tenantId, $user['id'], 'partnerships', count($partnerships));

        $this->sendCsv('federation_partnerships_' . date('Y-m-d'), $partnerships, [
            'id' => 'Partnership ID',
            'initiator_timebank' => 'Initiator Timebank',
            'partner_timebank' => 'Partner Timebank',
            'status' => 'Status',
            'federation_level' => 'Federation Level',
            'profiles_enabled' => 'Profiles Enabled',
            'messaging_enabled' => 'Messaging Enabled',
            'transactions_enabled' => 'Transactions Enabled',
            'listings_enabled' => 'Listings Enabled',
            'requested_by' => 'Requested By',
            'created_at' => 'Created Date',
            'updated_at' => 'Updated Date',
            'approved_at' => 'Approved Date',
            'terminated_at' => 'Terminated Date',
            'termination_reason' => 'Termination Reason'
        ]);
    }

    /**
     * Export federated transactions
     * GET /admin/federation/export/transactions
     */
    public function exportTransactions(): void
    {
        $user = Auth::requireAdmin();
        $tenantId = TenantContext::getId();
        $db = Database::getInstance();

        $stmt = $db->prepare("
            SELECT
                ft.id,
                ft.amount,
                ft.description,
                ft.status,
                ft.created_at,
                sender.first_name as sender_first_name,
                sender.last_name as sender_last_name,
                sender.email as sender_email,
                st.name as sender_timebank,
                receiver.first_name as receiver_first_name,
                receiver.last_name as receiver_last_name,
                receiver.email as receiver_email,
                rt.name as receiver_timebank
            FROM federation_transactions ft
            JOIN users sender ON sender.id = ft.sender_user_id
            JOIN users receiver ON receiver.id = ft.receiver_user_id
            LEFT JOIN tenants st ON st.id = ft.sender_tenant_id
            LEFT JOIN tenants rt ON rt.id = ft.receiver_tenant_id
            WHERE ft.sender_tenant_id = ? OR ft.receiver_tenant_id = ?
            ORDER BY ft.created_at DESC
        ");
        $stmt->execute([$tenantId, $tenantId]);
        $transactions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->logExport($tenantId, $user['id'], 'transactions', count($transactions));

        $this->sendCsv('federation_transactions_' . date('Y-m-d'), $transactions, [
            'id' => 'Transaction ID',
            'amount' => 'Amount (Hours)',
            'description' => 'Description',
            'status' => 'Status',
            'created_at' => 'Date',
            'sender_first_name' => 'Sender First Name',
            'sender_last_name' => 'Sender Last Name',
            'sender_email' => 'Sender Email',
            'sender_timebank' => 'Sender Timebank',
            'receiver_first_name' => 'Receiver First Name',
            'receiver_last_name' => 'Receiver Last Name',
            'receiver_email' => 'Receiver Email',
            'receiver_timebank' => 'Receiver Timebank'
        ]);
    }

    /**
     * Export audit logs
     * GET /admin/federation/export/audit
     */
    public function exportAudit(): void
    {
        $user = Auth::requireAdmin();
        $tenantId = TenantContext::getId();
        $db = Database::getInstance();

        // Get date range from query params
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');

        $stmt = $db->prepare("
            SELECT
                fal.id,
                fal.action_type,
                fal.category,
                fal.level,
                fal.ip_address,
                fal.user_agent,
                fal.created_at,
                t1.name as tenant_name,
                t2.name as partner_name,
                fal.actor_name,
                fal.actor_email
            FROM federation_audit_log fal
            LEFT JOIN tenants t1 ON t1.id = fal.source_tenant_id
            LEFT JOIN tenants t2 ON t2.id = fal.target_tenant_id
            WHERE (fal.source_tenant_id = ? OR fal.target_tenant_id = ?)
            AND DATE(fal.created_at) BETWEEN ? AND ?
            ORDER BY fal.created_at DESC
        ");
        $stmt->execute([$tenantId, $tenantId, $startDate, $endDate]);
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->logExport($tenantId, $user['id'], 'audit', count($logs));

        $this->sendCsv('federation_audit_' . date('Y-m-d'), $logs, [
            'id' => 'Log ID',
            'action_type' => 'Action',
            'category' => 'Category',
            'level' => 'Level',
            'tenant_name' => 'Source Tenant',
            'partner_name' => 'Target Tenant',
            'actor_name' => 'Actor Name',
            'actor_email' => 'Actor Email',
            'ip_address' => 'IP Address',
            'user_agent' => 'User Agent',
            'created_at' => 'Timestamp'
        ]);
    }

    /**
     * Export all federation data as ZIP
     * GET /admin/federation/export/all
     */
    public function exportAll(): void
    {
        $user = Auth::requireAdmin();
        $tenantId = TenantContext::getId();

        // Create temporary directory for CSV files
        $tempDir = sys_get_temp_dir() . '/federation_export_' . uniqid();
        mkdir($tempDir);

        $db = Database::getInstance();

        // Export each data type to separate CSV
        $exports = [
            'users' => $this->getUsersData($tenantId, $db),
            'partnerships' => $this->getPartnershipsData($tenantId, $db),
            'transactions' => $this->getTransactionsData($tenantId, $db),
            'messages' => $this->getMessagesData($tenantId, $db),
            'audit_log' => $this->getAuditData($tenantId, $db)
        ];

        foreach ($exports as $name => $data) {
            if (!empty($data['rows'])) {
                $this->writeCsvFile($tempDir . "/{$name}.csv", $data['rows'], $data['headers']);
            }
        }

        // Create ZIP file
        $zipFile = $tempDir . '/federation_export_' . date('Y-m-d_His') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipFile, \ZipArchive::CREATE);

        foreach (glob($tempDir . '/*.csv') as $file) {
            $zip->addFile($file, basename($file));
        }

        // Add metadata
        $metadata = [
            'export_date' => date('c'),
            'tenant_id' => $tenantId,
            'exported_by' => $user['first_name'] . ' ' . $user['last_name'],
            'record_counts' => array_map(fn($d) => count($d['rows']), $exports)
        ];
        $zip->addFromString('metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));

        $zip->close();

        // Log export
        $this->logExport($tenantId, $user['id'], 'full_backup', array_sum(array_map(fn($d) => count($d['rows']), $exports)));

        // Send ZIP file
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="federation_backup_' . date('Y-m-d') . '.zip"');
        header('Content-Length: ' . filesize($zipFile));
        readfile($zipFile);

        // Cleanup
        array_map('unlink', glob($tempDir . '/*'));
        rmdir($tempDir);
        exit;
    }

    /**
     * Get users data for export
     */
    private function getUsersData(int $tenantId, $db): array
    {
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.email, u.first_name, u.last_name,
                   u.city, u.region, u.country, u.skills,
                   fus.service_reach, fus.opted_in_at
            FROM users u
            JOIN federation_user_settings fus ON fus.user_id = u.id
            WHERE u.tenant_id = ? AND fus.federation_optin = 1
        ");
        $stmt->execute([$tenantId]);

        return [
            'rows' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'headers' => ['id', 'username', 'email', 'first_name', 'last_name', 'city', 'region', 'country', 'skills', 'service_reach', 'opted_in_at']
        ];
    }

    /**
     * Get partnerships data for export
     */
    private function getPartnershipsData(int $tenantId, $db): array
    {
        $stmt = $db->prepare("
            SELECT fp.id, t1.name as initiator, t2.name as partner,
                   fp.status, fp.federation_level, fp.created_at, fp.approved_at
            FROM federation_partnerships fp
            JOIN tenants t1 ON t1.id = fp.tenant_id
            JOIN tenants t2 ON t2.id = fp.partner_tenant_id
            WHERE fp.tenant_id = ? OR fp.partner_tenant_id = ?
        ");
        $stmt->execute([$tenantId, $tenantId]);

        return [
            'rows' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'headers' => ['id', 'initiator', 'partner', 'status', 'federation_level', 'created_at', 'approved_at']
        ];
    }

    /**
     * Get transactions data for export
     */
    private function getTransactionsData(int $tenantId, $db): array
    {
        $stmt = $db->prepare("
            SELECT ft.id, ft.amount, ft.description, ft.status, ft.created_at,
                   ft.sender_user_id, ft.receiver_user_id, ft.sender_tenant_id, ft.receiver_tenant_id
            FROM federation_transactions ft
            WHERE ft.sender_tenant_id = ? OR ft.receiver_tenant_id = ?
        ");
        $stmt->execute([$tenantId, $tenantId]);

        return [
            'rows' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'headers' => ['id', 'amount', 'description', 'status', 'created_at', 'sender_user_id', 'receiver_user_id', 'sender_tenant_id', 'receiver_tenant_id']
        ];
    }

    /**
     * Get messages data for export
     */
    private function getMessagesData(int $tenantId, $db): array
    {
        $stmt = $db->prepare("
            SELECT fm.id, fm.subject, fm.sender_user_id, fm.receiver_user_id,
                   fm.sender_tenant_id, fm.receiver_tenant_id, fm.status, fm.created_at
            FROM federation_messages fm
            WHERE fm.sender_tenant_id = ? OR fm.receiver_tenant_id = ?
        ");
        $stmt->execute([$tenantId, $tenantId]);

        return [
            'rows' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'headers' => ['id', 'subject', 'sender_user_id', 'receiver_user_id', 'sender_tenant_id', 'receiver_tenant_id', 'status', 'created_at']
        ];
    }

    /**
     * Get audit data for export
     */
    private function getAuditData(int $tenantId, $db): array
    {
        $stmt = $db->prepare("
            SELECT id, action_type, category, level, source_tenant_id, target_tenant_id, actor_user_id, actor_name, ip_address, created_at
            FROM federation_audit_log
            WHERE source_tenant_id = ? OR target_tenant_id = ?
            ORDER BY created_at DESC
            LIMIT 10000
        ");
        $stmt->execute([$tenantId, $tenantId]);

        return [
            'rows' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'headers' => ['id', 'action_type', 'category', 'level', 'source_tenant_id', 'target_tenant_id', 'actor_user_id', 'actor_name', 'ip_address', 'created_at']
        ];
    }

    /**
     * Write data to CSV file
     */
    private function writeCsvFile(string $filepath, array $rows, array $headers): void
    {
        $fp = fopen($filepath, 'w');
        fputcsv($fp, $headers);
        foreach ($rows as $row) {
            $csvRow = [];
            foreach ($headers as $key) {
                $csvRow[] = $row[$key] ?? '';
            }
            fputcsv($fp, $csvRow);
        }
        fclose($fp);
    }

    /**
     * Send CSV response
     */
    private function sendCsv(string $filename, array $rows, array $headerMap): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

        $output = fopen('php://output', 'w');

        // Write header row
        fputcsv($output, array_values($headerMap));

        // Write data rows
        foreach ($rows as $row) {
            $csvRow = [];
            foreach (array_keys($headerMap) as $key) {
                $value = $row[$key] ?? '';
                // Handle boolean values
                if ($value === '1' || $value === 1) $value = 'Yes';
                if ($value === '0' || $value === 0) $value = 'No';
                $csvRow[] = $value;
            }
            fputcsv($output, $csvRow);
        }

        fclose($output);
        exit;
    }

    /**
     * Log export action
     */
    private function logExport(int $tenantId, int $userId, string $type, int $recordCount): void
    {
        $db = Database::getInstance();

        $filename = "federation_{$type}_" . date('Y-m-d_His') . ".csv";

        // Log to exports table
        $stmt = $db->prepare("
            INSERT INTO federation_exports (tenant_id, exported_by, export_type, filename, record_count, status, created_at, completed_at)
            VALUES (?, ?, ?, ?, ?, 'completed', NOW(), NOW())
        ");
        $stmt->execute([$tenantId, $userId, $type, $filename, $recordCount]);

        // Log to audit
        FederationAuditService::log(
            'data_exported',
            $tenantId,
            null,
            $userId,
            ['type' => $type, 'count' => $recordCount]
        );
    }
}
