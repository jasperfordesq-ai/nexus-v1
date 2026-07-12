<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\FederationApiMiddleware;
use App\Core\TenantContext;
use App\Enums\EventFederationAction;
use App\Enums\EventFederationInboundDecision;
use App\Http\Resources\EventFederationReceiptResource;
use App\Services\EventFederationInboundProjectionService;
use App\Services\FederationFeatureService;
use App\Support\Events\EventFederationPayloadContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

/** Signature-required apply boundary for versioned Event federation facts. */
final class EventFederationInboundController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly EventFederationInboundProjectionService $projections,
        private readonly FederationFeatureService $features,
    ) {}

    public function apply(Request $request): JsonResponse
    {
        if (FederationApiMiddleware::getAuthMethod() !== 'hmac') {
            return $this->noStore($this->respondWithError(
                'HMAC_REQUIRED',
                __('api.federation.permission_denied'),
                null,
                401,
            ));
        }
        $apiKey = FederationApiMiddleware::getPartner();
        $tenantId = (int) ($apiKey['tenant_id'] ?? 0);
        $apiKeyId = (int) ($apiKey['id'] ?? 0);
        if ($tenantId <= 0 || $apiKeyId <= 0 || TenantContext::currentId() !== $tenantId) {
            return $this->noStore($this->respondWithError(
                'PARTNER_CONTEXT_INVALID',
                __('api.federation.webhook_auth_failed'),
                null,
                401,
            ));
        }
        if (! Schema::hasTable('event_federation_deliveries')
            || ! Schema::hasTable('federation_events')
            || ! Schema::hasColumn('federation_api_keys', 'external_partner_id')) {
            return $this->noStore($this->respondWithError(
                'EVENT_FEDERATION_UNAVAILABLE',
                __('api.service_unavailable'),
                null,
                503,
            ));
        }

        $externalPartnerId = (int) DB::table('federation_api_keys')
            ->where('id', $apiKeyId)
            ->where('tenant_id', $tenantId)
            ->value('external_partner_id');
        $externalPartner = $externalPartnerId > 0
            ? DB::table('federation_external_partners')
                ->where('id', $externalPartnerId)
                ->where('tenant_id', $tenantId)
                ->first(['status', 'protocol_type', 'allow_events'])
            : null;
        if ($externalPartner === null || (string) $externalPartner->protocol_type !== 'nexus') {
            return $this->noStore($this->respondWithError(
                'PARTNER_LINK_REQUIRED',
                __('api.federation.permission_denied'),
                null,
                403,
            ));
        }

        $payload = $request->json()->all();
        if (! is_array($payload) || $payload === []) {
            return $this->noStore($this->respondWithError(
                'EVENT_FEDERATION_PAYLOAD_INVALID',
                __('api.invalid_input'),
                null,
                422,
            ));
        }

        try {
            EventFederationPayloadContract::assertValid($payload);
            $action = EventFederationAction::from((string) $payload['action']);
            if ($action === EventFederationAction::Upsert
                && ! (bool) ($this->features->isOperationAllowed('events', $tenantId)['allowed'] ?? false)) {
                return $this->noStore($this->respondWithError(
                    'EVENT_FEDERATION_DISABLED',
                    __('api.federation.permission_denied'),
                    null,
                    403,
                ));
            }
            $result = $this->projections->ingest($tenantId, $externalPartnerId, $payload);
        } catch (InvalidArgumentException $exception) {
            $partnerFailure = str_contains($exception->getMessage(), '_partner_')
                || str_contains($exception->getMessage(), '_retraction_evidence_');

            return $this->noStore($this->respondWithError(
                $partnerFailure ? 'EVENT_FEDERATION_FORBIDDEN' : 'EVENT_FEDERATION_PAYLOAD_INVALID',
                $partnerFailure ? __('api.federation.permission_denied') : __('api.invalid_input'),
                null,
                $partnerFailure ? 403 : 422,
            ));
        } catch (Throwable $exception) {
            Log::error('[EventFederationInbound] apply failed', [
                'tenant_id' => $tenantId,
                'external_partner_id' => $externalPartnerId,
                'exception' => $exception::class,
                'reason_code' => $exception->getMessage(),
            ]);

            return $this->noStore($this->respondWithError(
                'EVENT_FEDERATION_APPLY_FAILED',
                __('api.service_unavailable'),
                null,
                503,
            ));
        }

        $status = match ($result->decision) {
            EventFederationInboundDecision::Accepted => 202,
            EventFederationInboundDecision::Conflict => 409,
            EventFederationInboundDecision::Replay,
            EventFederationInboundDecision::Stale => 200,
        };

        return $this->noStore($this->respondWithData(
            EventFederationReceiptResource::fromResult($result),
            null,
            $status,
        ));
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
