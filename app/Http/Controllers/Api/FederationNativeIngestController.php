<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\FederationApiMiddleware;
use App\Core\TenantContext;
use App\Services\FederationFeatureService;
use App\Services\FederationLogRedactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FederationNativeIngestController — REST endpoints that let Nexus Native V2
 * federation partners push entities (reviews, listings, events, groups,
 * connections, volunteering, member updates) to us.
 *
 * This controller is intentionally thin: it authenticates via the standard
 * FederationApiMiddleware (API key / HMAC / JWT), validates tenant access,
 * logs the inbound event, and delegates to the same normalized handlers used
 * by FederationExternalWebhookController.
 *
 * Ownership split:
 *   - THIS controller  — HTTP ingress + auth + logging
 *   - WebhookController — business logic (owned by other agents)
 *   - Listeners         — projection to Nexus tables (owned by other agents)
 *
 * All endpoints are tenant-scoped by the authenticated partner's tenant_id.
 *
 * Routes (under `federation.api` middleware):
 *   POST /v2/federation/ingest/reviews
 *   POST /v2/federation/ingest/listings
 *   POST /v2/federation/ingest/events
 *   POST /v2/federation/ingest/groups
 *   POST /v2/federation/ingest/connections
 *   POST /v2/federation/ingest/volunteering
 *   POST /v2/federation/ingest/members/sync
 */
class FederationNativeIngestController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function reviews(Request $request): JsonResponse
    {
        return $this->ingest($request, 'review.created');
    }

    public function listings(Request $request): JsonResponse
    {
        return $this->ingest($request, 'listing.created');
    }

    public function events(Request $request): JsonResponse
    {
        return $this->ingest($request, 'event.created');
    }

    public function groups(Request $request): JsonResponse
    {
        return $this->ingest($request, 'group.created');
    }

    public function connections(Request $request): JsonResponse
    {
        return $this->ingest($request, 'connection.requested');
    }

    public function volunteering(Request $request): JsonResponse
    {
        // A retraction (action 'deleted') routes to the deletion handler so the
        // mirrored/shadow opportunity is withdrawn; everything else upserts.
        $action = (string) ($request->json('action') ?? 'created');
        $eventType = $action === 'deleted' ? 'volunteering.deleted' : 'volunteering.created';

        return $this->ingest($request, $eventType);
    }

    public function membersSync(Request $request): JsonResponse
    {
        return $this->ingest($request, 'member.profile_updated');
    }

    /**
     * Shared ingest path: validates payload, logs the event, and processes it.
     */
    private function ingest(Request $request, string $eventType): JsonResponse
    {
        $partner = FederationApiMiddleware::getPartner();
        if (!$partner) {
            return $this->respondWithError('AUTH_FAILED', 'Federation partner context missing', null, 401);
        }

        $tenantId = $partner['tenant_id'] ?? null;
        if (!$tenantId) {
            return $this->respondWithError('TENANT_ERROR',
                'Unable to resolve tenant for this partner', null, 500);
        }

        // Enforce federation whitelist BEFORE binding TenantContext. If we set
        // the context first and then reject, we have already polluted request-
        // local state with a tenant we've just declined to serve.
        try {
            $feature = app(FederationFeatureService::class);
            if ($feature->isWhitelistModeActive() && !$feature->isTenantWhitelisted((int) $tenantId)) {
                return $this->respondWithError('TENANT_NOT_WHITELISTED',
                    'Partner tenant is not whitelisted for federation', null, 403);
            }
        } catch (\Throwable $e) {
            Log::warning('[FederationIngest] Whitelist check failed', ['error' => $e->getMessage()]);
        }

        if (!TenantContext::setById((int) $tenantId)) {
            return $this->respondWithError('TENANT_ERROR',
                'Unable to resolve tenant for this partner', null, 500);
        }

        $payload = $request->json()->all();
        if (!is_array($payload) || empty($payload)) {
            return $this->respondWithError('INVALID_REQUEST',
                'Request body must be a non-empty JSON object', null, 400);
        }

        // Resolve the external partner record (if any). SECURITY: the partner
        // identity — and with it the per-partner allow_* permission flags —
        // must come from the AUTHENTICATED key row, never from a client
        // header. The old X-Federation-Partner-ID header path (constrained
        // only to same tenant) let a key issued for partner A impersonate
        // partner B: write/overwrite/mass-retract B's federated entities under
        // B's permissions (2026-07-10 audit M3). An unlinked key resolves no
        // partner, so every allow_* flag below defaults false — fail closed.
        $externalPartner = null;
        try {
            $query = DB::table('federation_external_partners')->where('tenant_id', $tenantId);

            $linkedPartnerId = null;
            $keyId = (int) ($partner['id'] ?? 0);
            if ($keyId > 0 && \Illuminate\Support\Facades\Schema::hasColumn('federation_api_keys', 'external_partner_id')) {
                $linkedPartnerId = DB::table('federation_api_keys')
                    ->where('id', $keyId)
                    ->where('tenant_id', $tenantId)
                    ->value('external_partner_id');
            }

            $platformId = $partner['platform_id'] ?? null;
            if ((int) $linkedPartnerId > 0) {
                $externalPartner = $query->where('id', (int) $linkedPartnerId)->first();
            } elseif ($platformId && \Illuminate\Support\Facades\Schema::hasColumn('federation_external_partners', 'platform_id')) {
                $externalPartner = $query->where('platform_id', $platformId)->first();
            }
        } catch (\Throwable $e) {
            // Table missing in minimal test schemas — ignore
        }

        $handlerPartner = (object) [
            'id' => (int) ($externalPartner->id ?? ($partner['id'] ?? 0)),
            'tenant_id' => (int) $tenantId,
            'name' => (string) ($externalPartner->name ?? $partner['name'] ?? $partner['platform_id'] ?? __('api.external_partner_fallback')),
            'status' => (string) ($externalPartner->status ?? 'active'),
            'allow_member_search' => (bool) ($externalPartner->allow_member_search ?? false),
            'allow_listing_search' => (bool) ($externalPartner->allow_listing_search ?? false),
            'allow_messaging' => (bool) ($externalPartner->allow_messaging ?? false),
            'allow_transactions' => (bool) ($externalPartner->allow_transactions ?? false),
            'allow_events' => (bool) ($externalPartner->allow_events ?? false),
            'allow_groups' => (bool) ($externalPartner->allow_groups ?? false),
            'allow_connections' => (bool) ($externalPartner->allow_connections ?? false),
            'allow_volunteering' => (bool) ($externalPartner->allow_volunteering ?? false),
            'allow_member_sync' => (bool) ($externalPartner->allow_member_sync ?? false),
        ];

        if ($handlerPartner->status !== 'active') {
            return $this->respondWithError('PARTNER_INACTIVE', __('api.federation.webhook_partner_inactive'), null, 403);
        }

        try {
            $result = app(FederationExternalWebhookController::class)
                ->processTrustedEvent($eventType, $payload, $handlerPartner);
        } catch (FederationUnavailableException $e) {
            $this->logNativeIngest($handlerPartner->id, $eventType, $payload, 503, false, $e->getMessage());

            return $this->respondWithError('FEDERATION_UNAVAILABLE', $e->getMessage(), null, 503);
        } catch (InboundValidationException $e) {
            $this->logNativeIngest($handlerPartner->id, $eventType, $payload, 400, false, $e->getMessage());

            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), $e->field, 400);
        } catch (\Throwable $e) {
            Log::error('[FederationNativeIngest] Processing failed', [
                'event' => $eventType,
                'partner_id' => $partner['id'] ?? null,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            $this->logNativeIngest($handlerPartner->id, $eventType, $payload, 500, false, $e->getMessage());

            return $this->respondWithError('PROCESSING_FAILED', __('api.federation.ingest_processing_failed'), null, 500);
        }

        if (($result['success'] ?? true) === false
            && in_array(($result['error_code'] ?? null), [
                'VETTING_REQUIRED',
                'SAFEGUARDING_CONTACT_RESTRICTED',
                'SAFEGUARDING_POLICY_UNAVAILABLE',
            ], true)) {
            $code = (string) $result['error_code'];
            $status = $code === 'SAFEGUARDING_POLICY_UNAVAILABLE' ? 503 : 403;
            $error = (string) ($result['error'] ?? __('safeguarding.errors.contact_restricted'));

            $this->logNativeIngest(
                $handlerPartner->id,
                $eventType,
                $payload,
                $status,
                false,
                $error,
                $result,
            );

            return $this->respondWithError($code, $error, null, $status);
        }

        $this->logNativeIngest($handlerPartner->id, $eventType, $payload, 200, true, null, $result);

        Log::info('[FederationNativeIngest] Accepted', [
            'event' => $eventType,
            'partner_id' => $partner['id'] ?? null,
            'tenant_id' => $tenantId,
        ]);

        return $this->respondWithData([
            'received' => true,
            'event' => $eventType,
            'processed' => true,
            'result' => $result,
        ]);
    }

    private function logNativeIngest(
        int $partnerId,
        string $eventType,
        array $payload,
        int $responseCode,
        bool $success,
        ?string $errorMessage = null,
        ?array $responseBody = null,
    ): void {
        try {
            DB::table('federation_external_partner_logs')->insert([
                'partner_id'        => $partnerId,
                'endpoint'          => "/api/v2/federation/ingest [{$eventType}]",
                'method'            => 'POST',
                'response_code'     => $responseCode,
                'success'           => $success,
                'request_body'      => substr(FederationLogRedactor::redactJsonString(json_encode($payload) ?: '{}') ?? '', 0, 10000),
                'response_body'     => $responseBody ? substr(FederationLogRedactor::redactJsonString(json_encode($responseBody) ?: '{}') ?? '', 0, 10000) : null,
                'error_message'     => FederationLogRedactor::redactText($errorMessage),
                'created_at'        => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[FederationNativeIngest] Log write failed', [
                'event' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
