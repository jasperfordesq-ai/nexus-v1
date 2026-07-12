<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use App\Exceptions\EventLifecycleHistoryException;
use App\Http\Resources\EventLifecycleHistoryResource;
use App\Services\EventLifecycleHistoryQueryService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** HTML-first manager view of the same immutable lifecycle-history contract. */
trait EventLifecycleHistoryParity
{
    public function eventsLifecycleHistory(
        Request $request,
        string $tenantSlug,
        int $id,
    ): Response {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('events'), 403);
        $userId = $this->currentUserId();
        abort_if($userId === null, 401);

        $rawPerPage = $request->query('per_page', 20);
        $perPage = filter_var($rawPerPage, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 100],
        ]);
        abort_if($perPage === false, 422);
        $rawCursor = $request->query('cursor');
        abort_if($rawCursor !== null && ! is_string($rawCursor), 422);
        $cursor = is_string($rawCursor) && trim($rawCursor) !== ''
            ? trim($rawCursor)
            : null;

        try {
            $result = app(EventLifecycleHistoryQueryService::class)->index(
                $id,
                $this->accessibleEventActor($userId),
                $cursor,
                (int) $perPage,
            );
        } catch (EventLifecycleHistoryException $exception) {
            match ($exception->reasonCode) {
                'event_lifecycle_history_event_not_found' => abort(404),
                'event_lifecycle_history_authorization_denied' => abort(403),
                'event_lifecycle_history_cursor_invalid' => abort(422),
                'event_lifecycle_history_schema_unavailable',
                'event_lifecycle_history_tenant_context_missing' => abort(503),
                default => abort(422),
            };
        }

        $response = $this->view('accessible-frontend::event-lifecycle-history', [
            'title' => __('event_lifecycle_history.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'events',
            'event' => [
                'id' => (int) $result['event']->getKey(),
                'title' => (string) $result['event']->getAttribute('title'),
            ],
            'entries' => array_map(
                EventLifecycleHistoryResource::fromModel(...),
                $result['items'],
            ),
            'pagination' => $result['meta'],
        ]);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Pragma', 'no-cache');
        $response->setVary(['Authorization', 'Cookie', 'X-Tenant-ID'], false);

        return $response;
    }
}
