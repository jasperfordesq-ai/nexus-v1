<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Admin;

use Nexus\Core\Auth;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationAuditService;
use Nexus\Services\FederationUserService;

/**
 * FederationImportController
 *
 * Handles CSV import of federation data for bulk user enrollment.
 */
class FederationImportController
{
    /**
     * Process user import from CSV
     * POST /admin-legacy/federation/import/users
     */
    public function importUsers(): void
    {
        $user = Auth::requireAdmin();
        $tenantId = TenantContext::getId();

        // Validate CSRF
        if (!isset($_POST['csrf_token']) || !Auth::validateCsrf($_POST['csrf_token'])) {
            $_SESSION['flash_error'] = 'Invalid request. Please try again.';
            header('Location: /admin-legacy/federation/data');
            exit;
        }

        // Check file upload
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash_error'] = 'Please select a valid CSV file to upload.';
            header('Location: /admin-legacy/federation/data');
            exit;
        }

        $file = $_FILES['csv_file'];

        // Validate file type using finfo (replaces deprecated mime_content_type)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, ['text/csv', 'text/plain', 'application/csv'])) {
            $_SESSION['flash_error'] = 'Invalid file type. Please upload a CSV file.';
            header('Location: /admin-legacy/federation/data');
            exit;
        }

        // Parse CSV
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            $_SESSION['flash_error'] = 'Could not read the uploaded file.';
            header('Location: /admin-legacy/federation/data');
            exit;
        }

        // Get options
        $defaultPrivacyLevel = $_POST['default_privacy_level'] ?? 'social';
        $sendNotification = isset($_POST['send_notification']);
        $skipExisting = isset($_POST['skip_existing']);

        // Read header row
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            $_SESSION['flash_error'] = 'CSV file is empty or invalid.';
            header('Location: /admin-legacy/federation/data');
            exit;
        }

        // Normalize headers
        $headers = array_map(function($h) {
            return strtolower(trim(str_replace([' ', '-'], '_', $h)));
        }, $headers);

        // Find required columns
        $emailCol = array_search('email', $headers);
        $usernameCol = array_search('username', $headers);

        if ($emailCol === false && $usernameCol === false) {
            fclose($handle);
            $_SESSION['flash_error'] = 'CSV must contain either "email" or "username" column.';
            header('Location: /admin-legacy/federation/data');
            exit;
        }

        // Optional columns
        $privacyCol = array_search('privacy_level', $headers);
        $serviceReachCol = array_search('service_reach', $headers);

        $db = Database::getInstance();
        $results = [
            'processed' => 0,
            'enrolled' => 0,
            'skipped' => 0,
            'not_found' => 0,
            'errors' => []
        ];

        // Process each row
        $rowNum = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            $results['processed']++;

            // Get identifier
            $email = ($emailCol !== false && isset($row[$emailCol])) ? trim($row[$emailCol]) : null;
            $username = ($usernameCol !== false && isset($row[$usernameCol])) ? trim($row[$usernameCol]) : null;

            if (empty($email) && empty($username)) {
                $results['errors'][] = "Row {$rowNum}: Missing email or username";
                continue;
            }

            // Find user
            $userQuery = "SELECT id, email, first_name, last_name FROM users WHERE tenant_id = ? AND ";
            $params = [$tenantId];

            if ($email) {
                $userQuery .= "email = ?";
                $params[] = $email;
            } else {
                $userQuery .= "username = ?";
                $params[] = $username;
            }

            $stmt = $db->prepare($userQuery);
            $stmt->execute($params);
            $targetUser = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$targetUser) {
                $results['not_found']++;
                $results['errors'][] = "Row {$rowNum}: User not found (" . ($email ?: $username) . ")";
                continue;
            }

            // Check if already enrolled
            $stmt = $db->prepare("SELECT federation_optin FROM federation_user_settings WHERE user_id = ?");
            $stmt->execute([$targetUser['id']]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($existing && $existing['federation_optin'] && $skipExisting) {
                $results['skipped']++;
                continue;
            }

            // Get privacy level from CSV or use default
            $privacyLevel = $defaultPrivacyLevel;
            if ($privacyCol !== false && isset($row[$privacyCol]) && !empty(trim($row[$privacyCol]))) {
                $csvPrivacy = strtolower(trim($row[$privacyCol]));
                if (in_array($csvPrivacy, ['discovery', 'social', 'economic'])) {
                    $privacyLevel = $csvPrivacy;
                }
            }

            // Get service reach from CSV or use default
            $serviceReach = 'local_only';
            if ($serviceReachCol !== false && isset($row[$serviceReachCol]) && !empty(trim($row[$serviceReachCol]))) {
                $csvReach = strtolower(trim($row[$serviceReachCol]));
                // Map friendly names to DB enum values
                $reachMap = [
                    'local' => 'local_only',
                    'local_only' => 'local_only',
                    'will_travel' => 'travel_ok',
                    'travel_ok' => 'travel_ok',
                    'remote' => 'remote_ok',
                    'remote_ok' => 'remote_ok'
                ];
                if (isset($reachMap[$csvReach])) {
                    $serviceReach = $reachMap[$csvReach];
                }
            }

            // Determine settings based on privacy level
            $settings = $this->getSettingsForPrivacyLevel($privacyLevel);
            $settings['service_reach'] = $serviceReach;

            try {
                if ($existing) {
                    // Update existing settings
                    $stmt = $db->prepare("
                        UPDATE federation_user_settings
                        SET federation_optin = 1,
                            service_reach = ?,
                            appear_in_federated_search = ?,
                            profile_visible_federated = ?,
                            show_location_federated = ?,
                            show_skills_federated = ?,
                            messaging_enabled_federated = ?,
                            transactions_enabled_federated = ?,
                            opted_in_at = COALESCE(opted_in_at, NOW()),
                            updated_at = NOW()
                        WHERE user_id = ?
                    ");
                    $stmt->execute([
                        $settings['service_reach'],
                        $settings['show_in_search'],
                        $settings['profile_visible'],
                        $settings['show_location'],
                        $settings['show_skills'],
                        $settings['accepts_messages'],
                        $settings['accepts_transactions'],
                        $targetUser['id']
                    ]);
                } else {
                    // Create new settings
                    $stmt = $db->prepare("
                        INSERT INTO federation_user_settings
                        (user_id, federation_optin, service_reach,
                         appear_in_federated_search, profile_visible_federated,
                         show_location_federated, show_skills_federated,
                         messaging_enabled_federated, transactions_enabled_federated,
                         opted_in_at, created_at)
                        VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $targetUser['id'],
                        $settings['service_reach'],
                        $settings['show_in_search'],
                        $settings['profile_visible'],
                        $settings['show_location'],
                        $settings['show_skills'],
                        $settings['accepts_messages'],
                        $settings['accepts_transactions']
                    ]);
                }

                $results['enrolled']++;

                // Send notification if requested
                if ($sendNotification) {
                    $this->sendEnrollmentNotification($targetUser, $tenantId);
                }

            } catch (\Exception $e) {
                $results['errors'][] = "Row {$rowNum}: Database error - " . $e->getMessage();
            }
        }

        fclose($handle);

        // Log the import
        FederationAuditService::log(
            $tenantId,
            null,
            'bulk_user_import',
            "Bulk import: {$results['enrolled']} users enrolled, {$results['skipped']} skipped, {$results['not_found']} not found",
            $results
        );

        // Store results in session for display
        $_SESSION['import_results'] = $results;
        $_SESSION['flash_success'] = "Import complete: {$results['enrolled']} users enrolled in federation.";

        header('Location: /admin-legacy/federation/data');
        exit;
    }

    /**
     * Get default settings for privacy level
     */
    private function getSettingsForPrivacyLevel(string $level): array
    {
        switch ($level) {
            case 'discovery':
                return [
                    'show_in_search' => 1,
                    'profile_visible' => 0,
                    'show_location' => 0,
                    'show_skills' => 1,
                    'accepts_messages' => 0,
                    'accepts_transactions' => 0
                ];
            case 'economic':
                return [
                    'show_in_search' => 1,
                    'profile_visible' => 1,
                    'show_location' => 1,
                    'show_skills' => 1,
                    'accepts_messages' => 1,
                    'accepts_transactions' => 1
                ];
            case 'social':
            default:
                return [
                    'show_in_search' => 1,
                    'profile_visible' => 1,
                    'show_location' => 1,
                    'show_skills' => 1,
                    'accepts_messages' => 1,
                    'accepts_transactions' => 0
                ];
        }
    }

    /**
     * Send enrollment notification to user
     */
    private function sendEnrollmentNotification(array $user, int $tenantId): void
    {
        // Create in-app notification
        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, type, message, link, created_at)
            VALUES (?, 'federation', 'You have been enrolled in the Federation network. Customize your settings.', '/federation/settings', NOW())
        ");
        $stmt->execute([$user['id']]);
    }

    /**
     * Download import template
     * GET /admin-legacy/federation/import/template
     */
    public function downloadTemplate(): void
    {
        Auth::requireAdmin();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="federation_import_template.csv"');

        $output = fopen('php://output', 'w');

        // Write header row with all supported columns
        fputcsv($output, [
            'email',
            'username',
            'privacy_level',
            'service_reach'
        ]);

        // Write example rows
        fputcsv($output, ['john@example.com', '', 'social', 'local']);
        fputcsv($output, ['', 'jane_doe', 'economic', 'will_travel']);
        fputcsv($output, ['bob@example.com', 'bob123', 'discovery', 'remote_ok']);

        fclose($output);
        exit;
    }
}
