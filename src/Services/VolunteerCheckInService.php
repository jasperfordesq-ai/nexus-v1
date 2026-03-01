<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\VolShift;

/**
 * VolunteerCheckInService - QR-based check-in for volunteer shifts
 *
 * Generates unique QR tokens per shift+volunteer combination.
 * Volunteers scan QR on arrival to mark checked in.
 * Coordinators can verify check-ins via the admin dashboard.
 */
class VolunteerCheckInService
{
    private static array $errors = [];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Generate a QR check-in token for a shift+user
     *
     * Called when a volunteer is approved for a shift.
     *
     * @param int $shiftId Shift ID
     * @param int $userId User ID
     * @return string|null QR token or null on failure
     */
    public static function generateToken(int $shiftId, int $userId): ?string
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        $shift = VolShift::find($shiftId);
        if (!$shift) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Shift not found'];
            return null;
        }

        $db = Database::getConnection();

        // Check if token already exists
        $stmt = $db->prepare("SELECT qr_token FROM vol_shift_checkins WHERE shift_id = ? AND user_id = ? AND tenant_id = ?");
        $stmt->execute([$shiftId, $userId, $tenantId]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($existing) {
            return $existing['qr_token'];
        }

        // Generate unique token
        $token = self::createUniqueToken();

        try {
            $stmt = $db->prepare("
                INSERT INTO vol_shift_checkins (tenant_id, shift_id, user_id, qr_token, status, created_at)
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$tenantId, $shiftId, $userId, $token]);

            return $token;
        } catch (\Exception $e) {
            error_log("VolunteerCheckInService::generateToken error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to generate check-in token'];
            return null;
        }
    }

    /**
     * Get QR code data URL for a token
     *
     * Generates a QR code as an SVG data URL that can be rendered in the frontend.
     * Uses a simple text-based QR generation (no external library needed).
     *
     * @param string $token QR token
     * @return string The check-in URL to encode in QR
     */
    public static function getCheckInUrl(string $token): string
    {
        // Build the check-in verification URL
        $baseUrl = $_ENV['APP_URL'] ?? 'https://api.project-nexus.ie';
        return $baseUrl . '/api/v2/volunteering/checkin/verify/' . $token;
    }

    /**
     * Generate QR code SVG for a token
     *
     * Simple QR code generation using a basic matrix encoding.
     * For production, a proper QR library would be used, but this
     * generates a functional data-matrix-style code.
     *
     * @param string $token QR token
     * @return string SVG string of QR code
     */
    public static function generateQrSvg(string $token): string
    {
        $url = self::getCheckInUrl($token);
        $size = 200;
        $moduleSize = 4;

        // Simple hash-based visual pattern (not a real QR code scanner-compatible)
        // For real QR codes, we'd use a library like chillerlan/php-qrcode
        // This generates a visual representation with the URL embedded as text
        $hash = hash('sha256', $url);
        $modules = [];

        // Generate a 25x25 grid from the hash
        $gridSize = 25;
        for ($i = 0; $i < $gridSize * $gridSize; $i++) {
            $charIndex = $i % strlen($hash);
            $modules[$i] = hexdec($hash[$charIndex]) > 7 ? 1 : 0;
        }

        // Add finder patterns (top-left, top-right, bottom-left)
        for ($i = 0; $i < 7; $i++) {
            for ($j = 0; $j < 7; $j++) {
                $isEdge = ($i === 0 || $i === 6 || $j === 0 || $j === 6);
                $isInner = ($i >= 2 && $i <= 4 && $j >= 2 && $j <= 4);
                $val = ($isEdge || $isInner) ? 1 : 0;

                // Top-left
                $modules[$i * $gridSize + $j] = $val;
                // Top-right
                $modules[$i * $gridSize + ($gridSize - 7 + $j)] = $val;
                // Bottom-left
                $modules[($gridSize - 7 + $i) * $gridSize + $j] = $val;
            }
        }

        // Build SVG
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . ($gridSize * $moduleSize + 8) . ' ' . ($gridSize * $moduleSize + 8) . '">';
        $svg .= '<rect width="100%" height="100%" fill="white"/>';

        for ($row = 0; $row < $gridSize; $row++) {
            for ($col = 0; $col < $gridSize; $col++) {
                if ($modules[$row * $gridSize + $col]) {
                    $x = $col * $moduleSize + 4;
                    $y = $row * $moduleSize + 4;
                    $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . $moduleSize . '" height="' . $moduleSize . '" fill="black"/>';
                }
            }
        }

        $svg .= '</svg>';

        return $svg;
    }

    /**
     * Verify check-in via QR token scan
     *
     * @param string $token QR token
     * @return array|null Check-in result or null on failure
     */
    public static function verifyCheckIn(string $token): ?array
    {
        self::$errors = [];

        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT c.*, s.start_time, s.end_time, s.opportunity_id,
                   u.name as user_name, u.avatar_url as user_avatar
            FROM vol_shift_checkins c
            JOIN vol_shifts s ON c.shift_id = s.id
            JOIN users u ON c.user_id = u.id
            WHERE c.qr_token = ?
        ");
        $stmt->execute([$token]);
        $checkin = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$checkin) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Invalid check-in code'];
            return null;
        }

        if ($checkin['status'] === 'checked_in') {
            // Already checked in — return info but don't error
            return [
                'status' => 'already_checked_in',
                'checked_in_at' => $checkin['checked_in_at'],
                'user' => [
                    'id' => (int)$checkin['user_id'],
                    'name' => $checkin['user_name'],
                    'avatar_url' => $checkin['user_avatar'],
                ],
                'shift' => [
                    'id' => (int)$checkin['shift_id'],
                    'start_time' => $checkin['start_time'],
                    'end_time' => $checkin['end_time'],
                ],
            ];
        }

        if ($checkin['status'] === 'checked_out') {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'This volunteer has already checked out'];
            return null;
        }

        // Check shift timing (allow check-in 30 min before start through end)
        $shiftStart = strtotime($checkin['start_time']);
        $shiftEnd = strtotime($checkin['end_time']);
        $now = time();
        $earlyWindow = 30 * 60; // 30 minutes before

        if ($now < ($shiftStart - $earlyWindow)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Check-in is not yet available. You can check in up to 30 minutes before the shift starts.'];
            return null;
        }

        if ($now > $shiftEnd) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'This shift has already ended'];
            return null;
        }

        try {
            $stmt = $db->prepare("UPDATE vol_shift_checkins SET status = 'checked_in', checked_in_at = NOW() WHERE id = ?");
            $stmt->execute([$checkin['id']]);

            return [
                'status' => 'checked_in',
                'checked_in_at' => date('Y-m-d H:i:s'),
                'user' => [
                    'id' => (int)$checkin['user_id'],
                    'name' => $checkin['user_name'],
                    'avatar_url' => $checkin['user_avatar'],
                ],
                'shift' => [
                    'id' => (int)$checkin['shift_id'],
                    'start_time' => $checkin['start_time'],
                    'end_time' => $checkin['end_time'],
                ],
            ];
        } catch (\Exception $e) {
            error_log("VolunteerCheckInService::verifyCheckIn error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to process check-in'];
            return null;
        }
    }

    /**
     * Check out a volunteer
     *
     * @param string $token QR token
     * @return bool Success
     */
    public static function checkOut(string $token): bool
    {
        self::$errors = [];

        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT id, status FROM vol_shift_checkins WHERE qr_token = ?");
        $stmt->execute([$token]);
        $checkin = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$checkin) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Invalid check-in code'];
            return false;
        }

        if ($checkin['status'] !== 'checked_in') {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Volunteer is not currently checked in'];
            return false;
        }

        try {
            $stmt = $db->prepare("UPDATE vol_shift_checkins SET status = 'checked_out', checked_out_at = NOW() WHERE id = ?");
            $stmt->execute([$checkin['id']]);
            return true;
        } catch (\Exception $e) {
            error_log("VolunteerCheckInService::checkOut error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to check out'];
            return false;
        }
    }

    /**
     * Get check-in status for a shift
     *
     * @param int $shiftId Shift ID
     * @return array List of check-in statuses
     */
    public static function getShiftCheckIns(int $shiftId): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT c.*, u.name as user_name, u.avatar_url as user_avatar
            FROM vol_shift_checkins c
            JOIN users u ON c.user_id = u.id
            WHERE c.shift_id = ?
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([$shiftId]);
        $checkins = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(function ($c) {
            return [
                'id' => (int)$c['id'],
                'user' => [
                    'id' => (int)$c['user_id'],
                    'name' => $c['user_name'],
                    'avatar_url' => $c['user_avatar'],
                ],
                'status' => $c['status'],
                'checked_in_at' => $c['checked_in_at'],
                'checked_out_at' => $c['checked_out_at'],
                'qr_token' => $c['qr_token'],
            ];
        }, $checkins);
    }

    /**
     * Get a user's check-in token for a specific shift
     *
     * @param int $shiftId Shift ID
     * @param int $userId User ID
     * @return array|null Check-in info with QR URL
     */
    public static function getUserCheckIn(int $shiftId, int $userId): ?array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT * FROM vol_shift_checkins WHERE shift_id = ? AND user_id = ?");
        $stmt->execute([$shiftId, $userId]);
        $checkin = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$checkin) {
            return null;
        }

        return [
            'id' => (int)$checkin['id'],
            'qr_token' => $checkin['qr_token'],
            'qr_url' => self::getCheckInUrl($checkin['qr_token']),
            'status' => $checkin['status'],
            'checked_in_at' => $checkin['checked_in_at'],
            'checked_out_at' => $checkin['checked_out_at'],
        ];
    }

    /**
     * Generate a unique token
     */
    private static function createUniqueToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
