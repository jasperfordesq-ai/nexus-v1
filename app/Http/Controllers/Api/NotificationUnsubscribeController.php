<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Generic one-click unsubscribe for ANY bulk / notification email the
 * platform sends. The link embedded in those emails is an HMAC-signed URL
 * naming the user, tenant, and notification category to disable.
 *
 * Required by Gmail / Yahoo bulk-sender rules (Feb 2024) — every notification
 * email needs a working `List-Unsubscribe` header pointing here.
 *
 * Endpoints:
 *   GET  /v2/notifications/unsubscribe?token=<base64>  — browser link
 *   POST /v2/notifications/unsubscribe                 — one-click `List-Unsubscribe-Post`
 *
 * Both verify the HMAC against APP_KEY, look up the user, set the matching
 * notification preference to false, and return either a small HTML "you
 * have been unsubscribed" page (GET) or a 200 JSON ack (POST).
 *
 * Categories map to keys in `users.notification_preferences`:
 *
 *   all              → flips every email_* key to false
 *   messages         → email_messages
 *   connections      → email_connections
 *   transactions     → email_transactions
 *   reviews          → email_reviews
 *   listings         → email_listings
 *   digest           → email_digest
 *   gamification     → email_gamification_digest + email_gamification_milestones
 *   org              → email_org_payments + email_org_transfers + email_org_membership + email_org_admin
 *   federation       → federation_notifications_enabled (column, not JSON)
 *
 * Idempotent: re-clicking the link returns the same confirmation.
 */
class NotificationUnsubscribeController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** All email_* keys flipped by category=all. */
    private const ALL_EMAIL_KEYS = [
        'email_messages',
        'email_listings',
        'email_digest',
        'email_connections',
        'email_transactions',
        'email_reviews',
        'email_gamification_digest',
        'email_gamification_milestones',
        'email_org_payments',
        'email_org_transfers',
        'email_org_membership',
        'email_org_admin',
    ];

    /** category => list of JSON keys to flip false (federation handled separately). */
    private const CATEGORY_TO_KEYS = [
        'all'           => self::ALL_EMAIL_KEYS,
        'messages'      => ['email_messages'],
        'connections'   => ['email_connections'],
        'transactions'  => ['email_transactions'],
        'reviews'       => ['email_reviews'],
        'listings'      => ['email_listings'],
        'digest'        => ['email_digest'],
        'gamification'  => ['email_gamification_digest', 'email_gamification_milestones'],
        'org'           => ['email_org_payments', 'email_org_transfers', 'email_org_membership', 'email_org_admin'],
    ];

    /**
     * Build a signed unsubscribe URL for use in an email's List-Unsubscribe
     * header. Caller should be inside a tenant context.
     */
    public static function buildSignedUrl(int $userId, int $tenantId, string $category = 'all'): string
    {
        $payload = $userId . '.' . $tenantId . '.' . $category;
        $sig     = hash_hmac('sha256', $payload, (string) config('app.key'));
        $token   = rtrim(strtr(base64_encode($payload . '.' . $sig), '+/', '-_'), '=');

        // Use the tenant's frontend URL so the unsubscribe page lives under
        // the same domain as the email's other links.
        $baseUrl  = TenantContext::getFrontendUrl();
        $basePath = TenantContext::getSlugPrefix();
        return $baseUrl . $basePath . '/api/v2/notifications/unsubscribe?token=' . $token;
    }

    /** GET handler — renders a confirmation page. */
    public function show(Request $request): \Illuminate\Http\Response
    {
        $result = $this->processToken((string) $request->query('token', ''));
        $status = $result['status']; // 'ok' | 'invalid' | 'already'
        $html   = $this->renderConfirmationHtml($status, $result['category'] ?? null);
        return response($html, $status === 'invalid' ? 400 : 200)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Cache-Control', 'no-store');
    }

    /** POST handler — one-click List-Unsubscribe-Post. */
    public function oneClick(Request $request): JsonResponse
    {
        // The token can arrive in the query string or the body depending on
        // how the mail client formats the one-click POST.
        $token = (string) ($request->input('token') ?? $request->query('token', ''));
        $result = $this->processToken($token);
        if ($result['status'] === 'invalid') {
            return $this->respondWithError('INVALID_TOKEN', 'Unsubscribe token is invalid or expired.', null, 400);
        }
        return $this->respondWithData(['unsubscribed' => true, 'category' => $result['category'] ?? 'all']);
    }

    /**
     * Verify token and flip the relevant preferences. Returns:
     *   ['status' => 'ok'|'already'|'invalid', 'category' => ?string]
     */
    private function processToken(string $token): array
    {
        if ($token === '') {
            return ['status' => 'invalid'];
        }

        $decoded = base64_decode(strtr($token, '-_', '+/'), true);
        if ($decoded === false) {
            return ['status' => 'invalid'];
        }

        $parts = explode('.', $decoded);
        if (count($parts) !== 4) {
            return ['status' => 'invalid'];
        }
        [$userIdStr, $tenantIdStr, $category, $sig] = $parts;

        $userId   = (int) $userIdStr;
        $tenantId = (int) $tenantIdStr;
        if ($userId <= 0 || $tenantId <= 0 || $category === '') {
            return ['status' => 'invalid'];
        }

        if (!isset(self::CATEGORY_TO_KEYS[$category]) && $category !== 'federation') {
            return ['status' => 'invalid'];
        }

        $expected = hash_hmac('sha256', $userId . '.' . $tenantId . '.' . $category, (string) config('app.key'));
        if (!hash_equals($expected, $sig)) {
            return ['status' => 'invalid'];
        }

        TenantContext::setById($tenantId);

        try {
            $user = DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', $tenantId)
                ->first(['id', 'notification_preferences', 'federation_notifications_enabled']);
            if (!$user) {
                return ['status' => 'invalid'];
            }

            // 'federation' category: flip a column, not the JSON.
            if ($category === 'federation') {
                if ((int) ($user->federation_notifications_enabled ?? 1) === 0) {
                    return ['status' => 'already', 'category' => $category];
                }
                DB::table('users')->where('id', $userId)->update([
                    'federation_notifications_enabled' => 0,
                    'updated_at' => now(),
                ]);
                Log::info('NotificationUnsubscribe: federation flipped off', [
                    'user_id'   => $userId,
                    'tenant_id' => $tenantId,
                ]);
                return ['status' => 'ok', 'category' => $category];
            }

            $prefs = json_decode($user->notification_preferences ?? '{}', true) ?: [];
            $keysToFlip = self::CATEGORY_TO_KEYS[$category];
            $alreadyAllOff = true;
            foreach ($keysToFlip as $k) {
                if (($prefs[$k] ?? 1) !== false && (int) ($prefs[$k] ?? 1) !== 0) {
                    $alreadyAllOff = false;
                }
                $prefs[$k] = false;
            }
            if ($alreadyAllOff) {
                return ['status' => 'already', 'category' => $category];
            }

            DB::table('users')->where('id', $userId)->update([
                'notification_preferences' => json_encode($prefs),
                'updated_at' => now(),
            ]);

            Log::info('NotificationUnsubscribe: preferences flipped off', [
                'user_id'   => $userId,
                'tenant_id' => $tenantId,
                'category'  => $category,
                'keys'      => $keysToFlip,
            ]);

            return ['status' => 'ok', 'category' => $category];
        } finally {
            TenantContext::reset();
        }
    }

    private function renderConfirmationHtml(string $status, ?string $category): string
    {
        $tenantName = htmlspecialchars((string) (TenantContext::getSetting('site_name') ?? 'Project NEXUS'), ENT_QUOTES, 'UTF-8');
        $cat = htmlspecialchars((string) $category, ENT_QUOTES, 'UTF-8');
        $title = match ($status) {
            'ok'      => 'You have been unsubscribed',
            'already' => 'You were already unsubscribed',
            default   => 'Unsubscribe link invalid',
        };
        $body = match ($status) {
            'ok' => "You will no longer receive <strong>{$cat}</strong> emails from {$tenantName}. You can re-enable them from your account's notification settings at any time.",
            'already' => "Your <strong>{$cat}</strong> emails were already turned off for {$tenantName}. No further action is needed.",
            default => "This unsubscribe link is invalid or has expired. Please update your preferences directly from your account's notification settings.",
        };
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{$title}</title>
<style>
  body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;max-width:540px;margin:64px auto;padding:0 16px;color:#1f2937;line-height:1.5;}
  .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:32px;box-shadow:0 1px 3px rgba(0,0,0,0.04);}
  h1{font-size:22px;margin:0 0 12px;}
  p{margin:0;color:#4b5563;}
</style>
</head>
<body>
<div class="card">
<h1>{$title}</h1>
<p>{$body}</p>
</div>
</body>
</html>
HTML;
    }
}
