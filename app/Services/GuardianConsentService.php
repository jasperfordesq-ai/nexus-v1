<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GuardianConsentService
 *
 * Manages guardian/parental consent for minor volunteers.
 * Supports consent requests, granting, withdrawal, checking,
 * admin views, expiry, and minor detection.
 *
 * All queries are tenant-scoped via TenantContext::getId().
 */
class GuardianConsentService
{
    /** Minimum age threshold (users younger than this are minors). */
    private const MINOR_AGE_THRESHOLD = 18;

    /** Valid relationship types for guardians. */
    private const VALID_RELATIONSHIPS = ['parent', 'guardian', 'legal_guardian', 'carer'];

    /** Default consent expiry in days. */
    private const CONSENT_EXPIRY_DAYS = 365;

    public function __construct()
    {
    }

    /**
     * Check if a user is a minor based on date of birth.
     *
     * @param int $userId User ID
     * @return bool
     */
    public static function isMinor(int $userId): bool
    {
        try {
            $tenantId = TenantContext::getId();

            $dob = DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', $tenantId)
                ->value('date_of_birth');

            if (!$dob) {
                return false;
            }

            $birthDate = new \DateTime($dob);
            $now = new \DateTime();
            $age = $now->diff($birthDate)->y;

            return $age < self::MINOR_AGE_THRESHOLD;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Request guardian consent for a minor.
     *
     * @param int $minorUserId Minor user ID
     * @param array $guardianData Guardian information (guardian_name, guardian_email, relationship)
     * @param int|null $opportunityId Optional opportunity ID
     * @return array Consent record with consent_token
     * @throws \InvalidArgumentException If validation fails
     */
    public static function requestConsent(int $minorUserId, array $guardianData, ?int $opportunityId = null): array
    {
        $tenantId = TenantContext::getId();

        // Validate required fields
        if (empty($guardianData['guardian_name'])) {
            throw new \InvalidArgumentException('Guardian name is required.');
        }
        if (empty($guardianData['guardian_email'])) {
            throw new \InvalidArgumentException('Guardian email is required.');
        }
        if (empty($guardianData['relationship'])) {
            throw new \InvalidArgumentException('Relationship is required.');
        }

        // Validate email
        if (!filter_var($guardianData['guardian_email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid guardian email address.');
        }

        // Validate relationship type
        if (!in_array($guardianData['relationship'], self::VALID_RELATIONSHIPS, true)) {
            throw new \InvalidArgumentException('Invalid relationship type. Must be one of: ' . implode(', ', self::VALID_RELATIONSHIPS));
        }

        // Validate user is a minor
        if (!self::isMinor($minorUserId)) {
            throw new \InvalidArgumentException('User is not a minor and does not require guardian consent.');
        }

        // Generate consent token
        $consentToken = bin2hex(random_bytes(32));

        // Calculate expiry
        $expiresAt = (new \DateTime())->modify('+' . self::CONSENT_EXPIRY_DAYS . ' days')->format('Y-m-d H:i:s');

        // Insert consent record
        $id = DB::table('vol_guardian_consents')->insertGetId([
            'tenant_id' => $tenantId,
            'minor_user_id' => $minorUserId,
            'guardian_name' => $guardianData['guardian_name'],
            'guardian_email' => $guardianData['guardian_email'],
            'relationship' => $guardianData['relationship'],
            'opportunity_id' => $opportunityId,
            'consent_token' => $consentToken,
            'status' => 'pending',
            'expires_at' => $expiresAt,
            'created_at' => now(),
        ]);

        // Send consent email to guardian
        $tenant = TenantContext::get();
        $tenantSlug = $tenant['slug'] ?? '';
        $verifyUrl = config('app.frontend_url', 'https://app.project-nexus.ie')
            . '/' . $tenantSlug
            . '/volunteering/guardian-consent/verify/' . $consentToken;

        try {
            \Illuminate\Support\Facades\Mail::raw(
                "Dear {$guardianData['guardian_name']},\n\n"
                . "A minor in your care has requested your consent to participate in volunteering activities on Project NEXUS.\n\n"
                . "To grant your consent, please visit the following link:\n"
                . "{$verifyUrl}\n\n"
                . "This link will expire in " . self::CONSENT_EXPIRY_DAYS . " days.\n\n"
                . "If you did not expect this request, you can safely ignore this email.\n\n"
                . "Regards,\nProject NEXUS",
                function ($message) use ($guardianData) {
                    $message->to($guardianData['guardian_email'], $guardianData['guardian_name'])
                            ->subject('Guardian Consent Request — Project NEXUS');
                }
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to send guardian consent email: ' . $e->getMessage());
            // Don't fail the request — consent record is still created
        }

        return [
            'id' => $id,
            'minor_user_id' => $minorUserId,
            'guardian_name' => $guardianData['guardian_name'],
            'guardian_email' => $guardianData['guardian_email'],
            'relationship' => $guardianData['relationship'],
            'opportunity_id' => $opportunityId,
            'consent_token' => $consentToken,
            'status' => 'pending',
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Grant consent using a consent token.
     *
     * @param string $token Consent token
     * @param string $ip IP address of the granting party
     * @return bool
     */
    public static function grantConsent(string $token, string $ip): bool
    {
        $tenantId = TenantContext::getId();

        try {
            $consent = DB::table('vol_guardian_consents')
                ->where('consent_token', $token)
                ->where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->first();

            if (!$consent) {
                return false;
            }

            // Check if expired
            if ($consent->expires_at && strtotime($consent->expires_at) < time()) {
                return false;
            }

            DB::table('vol_guardian_consents')
                ->where('id', $consent->id)
                ->update([
                    'status' => 'active',
                    'granted_at' => now(),
                    'granted_ip' => $ip,
                    'updated_at' => now(),
                ]);

            return true;
        } catch (\Exception $e) {
            Log::error('GuardianConsentService::grantConsent error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Withdraw an active consent.
     *
     * @param int $consentId Consent record ID
     * @param int $userId User ID performing the withdrawal
     * @return bool
     */
    public static function withdrawConsent(int $consentId, int $userId): bool
    {
        $tenantId = TenantContext::getId();

        try {
            $consent = DB::table('vol_guardian_consents')
                ->where('id', $consentId)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->first();

            if (!$consent) {
                return false;
            }

            DB::table('vol_guardian_consents')
                ->where('id', $consentId)
                ->update([
                    'status' => 'withdrawn',
                    'withdrawn_at' => now(),
                    'withdrawn_by' => $userId,
                    'updated_at' => now(),
                ]);

            return true;
        } catch (\Exception $e) {
            Log::error('GuardianConsentService::withdrawConsent error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a minor has active consent.
     *
     * @param int $minorUserId Minor user ID
     * @param int|null $opportunityId Optional opportunity ID to check specific consent
     * @return bool
     */
    public static function checkConsent(int $minorUserId, ?int $opportunityId = null): bool
    {
        $tenantId = TenantContext::getId();

        try {
            $query = DB::table('vol_guardian_consents')
                ->where('minor_user_id', $minorUserId)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                });

            if ($opportunityId !== null) {
                $query->where(function ($q) use ($opportunityId) {
                    $q->where('opportunity_id', $opportunityId)
                        ->orWhereNull('opportunity_id');
                });
            }

            return $query->exists();
        } catch (\Exception $e) {
            Log::error('GuardianConsentService::checkConsent error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all consent records for a minor.
     *
     * @param int $minorUserId Minor user ID
     * @return array
     */
    public static function getConsentsForMinor(int $minorUserId): array
    {
        $tenantId = TenantContext::getId();

        try {
            return DB::table('vol_guardian_consents')
                ->where('minor_user_id', $minorUserId)
                ->where('tenant_id', $tenantId)
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->toArray();
        } catch (\Exception $e) {
            Log::error('GuardianConsentService::getConsentsForMinor error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get consent records for admin view with pagination.
     *
     * @param array $filters Optional filters (status, limit, cursor)
     * @return array Paginated result with items, cursor, has_more
     */
    public static function getConsentsForAdmin(array $filters = []): array
    {
        $tenantId = TenantContext::getId();

        try {
            $limit = (int) ($filters['limit'] ?? 20);
            $cursor = (int) ($filters['cursor'] ?? 0);

            $query = DB::table('vol_guardian_consents as gc')
                ->leftJoin('users as minor', 'gc.minor_user_id', '=', 'minor.id')
                ->where('gc.tenant_id', $tenantId);

            if (!empty($filters['status'])) {
                $query->where('gc.status', $filters['status']);
            }

            if ($cursor > 0) {
                $query->where('gc.id', '<', $cursor);
            }

            $items = $query->orderByDesc('gc.id')
                ->limit($limit + 1)
                ->select([
                    'gc.*',
                    'minor.name as minor_name',
                    'minor.email as minor_email',
                ])
                ->get()
                ->map(fn ($row) => (array) $row)
                ->toArray();

            $hasMore = count($items) > $limit;
            if ($hasMore) {
                array_pop($items);
            }

            $nextCursor = !empty($items) ? $items[count($items) - 1]['id'] : null;

            return [
                'items' => $items,
                'cursor' => $nextCursor,
                'has_more' => $hasMore,
            ];
        } catch (\Exception $e) {
            Log::error('GuardianConsentService::getConsentsForAdmin error: ' . $e->getMessage());
            return [
                'items' => [],
                'cursor' => null,
                'has_more' => false,
            ];
        }
    }

    /**
     * Expire old pending consent records.
     *
     * @return int Number of records expired
     */
    public static function expireOldConsents(): int
    {
        $tenantId = TenantContext::getId();

        try {
            return DB::table('vol_guardian_consents')
                ->where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->update([
                    'status' => 'expired',
                    'updated_at' => now(),
                ]);
        } catch (\Exception $e) {
            Log::error('GuardianConsentService::expireOldConsents error: ' . $e->getMessage());
            return 0;
        }
    }
}
