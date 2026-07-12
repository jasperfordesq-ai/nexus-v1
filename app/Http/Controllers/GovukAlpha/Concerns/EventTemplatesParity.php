<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use App\Exceptions\EventTemplateException;
use App\Http\Resources\EventTemplateAuditResource;
use App\Http\Resources\EventTemplatePreviewResource;
use App\Http\Resources\EventTemplateResource;
use App\Models\User;
use App\Services\EventTemplateQueryService;
use App\Services\EventTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/** Essential HTML-first capture, preview, and fresh-draft template workflow. */
trait EventTemplatesParity
{
    public function eventsTemplates(
        Request $request,
        string $tenantSlug,
    ): Response|RedirectResponse {
        $actor = $this->eventsTemplateActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $status = is_string($request->query('filter'))
            && in_array($request->query('filter'), ['active', 'archived', 'all'], true)
            ? (string) $request->query('filter')
            : 'active';
        $cursor = $this->eventsTemplateNullablePositiveInteger($request->query('cursor'));

        try {
            $result = app(EventTemplateQueryService::class)->index(
                $actor,
                $status,
                null,
                null,
                $cursor,
                20,
            );
        } catch (EventTemplateException $exception) {
            return $this->eventsTemplateFailure($exception, $tenantSlug);
        }

        return $this->eventsTemplatePrivateResponse($this->view(
            'accessible-frontend::event-templates',
            [
                'title' => __('event_templates.title'),
                'tenantSlug' => $tenantSlug,
                'activeNav' => 'events',
                'templates' => array_map(
                    EventTemplateResource::fromRecord(...),
                    $result['records'],
                ),
                'pagination' => $result['meta'],
                'filter' => $status,
                'cursor' => $cursor,
                'status' => is_string($request->query('status'))
                    ? trim((string) $request->query('status'))
                    : null,
            ],
        ));
    }

    public function eventsTemplateHistory(
        Request $request,
        string $tenantSlug,
        int $templateId,
    ): Response|RedirectResponse {
        $actor = $this->eventsTemplateActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }

        $cursor = $this->eventsTemplateNullablePositiveInteger($request->query('cursor'));
        $libraryCursor = $this->eventsTemplateNullablePositiveInteger(
            $request->query('library_cursor'),
        );
        if (($request->query->has('cursor') && $cursor === null)
            || ($request->query->has('library_cursor') && $libraryCursor === null)) {
            abort(422);
        }

        $filterInput = $request->query('filter');
        if ($filterInput !== null
            && (! is_string($filterInput)
                || ! in_array($filterInput, ['active', 'archived', 'all'], true))) {
            abort(422);
        }
        $filter = is_string($filterInput) ? $filterInput : 'active';

        try {
            $template = EventTemplateResource::fromRecord(
                app(EventTemplateQueryService::class)->show($templateId, $actor),
            );
            abort_unless((bool) ($template['capabilities']['view_audit'] ?? false), 403);
            $result = app(EventTemplateQueryService::class)->history(
                $templateId,
                $actor,
                $cursor,
                20,
            );
        } catch (EventTemplateException $exception) {
            return $this->eventsTemplateFailure($exception, $tenantSlug);
        }

        return $this->eventsTemplatePrivateResponse($this->view(
            'accessible-frontend::event-template-history',
            [
                'title' => __('event_templates.audit_title'),
                'tenantSlug' => $tenantSlug,
                'activeNav' => 'events',
                'template' => $template,
                'audits' => array_map(
                    EventTemplateAuditResource::fromModel(...),
                    $result['audits'],
                ),
                'pagination' => $result['meta'],
                'filter' => $filter,
                'libraryCursor' => $libraryCursor,
            ],
        ));
    }

    public function eventsTemplateCapturePreview(
        Request $request,
        string $tenantSlug,
        int $id,
    ): Response|RedirectResponse {
        $actor = $this->eventsTemplateActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }

        $templateId = $this->eventsTemplateNullablePositiveInteger(
            $request->query('template_id'),
        );
        try {
            $preview = EventTemplatePreviewResource::capture(
                app(EventTemplateService::class)->previewCapture($id, $actor),
            );
            $template = $templateId === null
                ? null
                : EventTemplateResource::fromRecord(
                    app(EventTemplateQueryService::class)->show($templateId, $actor),
                );
            if ($template !== null && (int) $template['source_event']['id'] !== $id) {
                throw new EventTemplateException('event_template_source_not_found');
            }
        } catch (EventTemplateException $exception) {
            return $this->eventsTemplateFailure($exception, $tenantSlug, $id);
        }

        return $this->eventsTemplatePrivateResponse($this->view(
            'accessible-frontend::event-template-capture-preview',
            [
                'title' => __('event_templates.capture_preview_title'),
                'tenantSlug' => $tenantSlug,
                'activeNav' => 'events',
                'sourceEventId' => $id,
                'preview' => $preview,
                'template' => $template,
            ],
        ));
    }

    public function eventsTemplateCapture(
        Request $request,
        string $tenantSlug,
        int $id,
    ): RedirectResponse {
        $actor = $this->eventsTemplateActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $key = $this->eventsTemplateIdempotencyKey($request);
        $templateId = $this->eventsTemplateNullablePositiveInteger(
            $request->input('template_id'),
        );
        $expectedVersion = $this->eventsTemplateNullablePositiveInteger(
            $request->input('expected_version'),
        );
        if ($key === null || ($templateId !== null && $expectedVersion === null)) {
            return $this->eventsTemplateInvalidRedirect($tenantSlug, $id, $templateId);
        }

        try {
            if ($templateId === null) {
                app(EventTemplateService::class)->capture($id, $actor, $key);
                $status = 'captured';
            } else {
                app(EventTemplateService::class)->revise(
                    $templateId,
                    $actor,
                    (int) $expectedVersion,
                    $key,
                );
                $status = 'revised';
            }
        } catch (EventTemplateException $exception) {
            return $this->eventsTemplateMutationFailure(
                $exception,
                $tenantSlug,
                'govuk-alpha.events.templates.capture.preview',
                ['id' => $id, 'template_id' => $templateId],
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->eventsTemplateInvalidRedirect($tenantSlug, $id, $templateId, true);
        }

        return redirect()->route('govuk-alpha.events.templates.index', [
            'tenantSlug' => $tenantSlug,
            'status' => $status,
        ]);
    }

    public function eventsTemplateMaterializeForm(
        Request $request,
        string $tenantSlug,
        int $templateId,
    ): Response|RedirectResponse {
        $actor = $this->eventsTemplateActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        try {
            $template = EventTemplateResource::fromRecord(
                app(EventTemplateQueryService::class)->show($templateId, $actor),
            );
        } catch (EventTemplateException $exception) {
            return $this->eventsTemplateFailure($exception, $tenantSlug);
        }

        return $this->eventsTemplateMaterializeView(
            $tenantSlug,
            $template,
            $this->eventsTemplateDefaultMaterializationValues($template),
            null,
        );
    }

    public function eventsTemplateMaterializePreview(
        Request $request,
        string $tenantSlug,
        int $templateId,
    ): Response|RedirectResponse {
        $actor = $this->eventsTemplateActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $input = $this->eventsTemplateMaterializationInput($request);
        if ($input === null) {
            return redirect()
                ->route('govuk-alpha.events.templates.materialize.form', compact('tenantSlug', 'templateId'))
                ->withErrors(['template' => __('event_templates.validation_error')])
                ->withInput($request->except(['idempotency_key']));
        }

        try {
            $template = EventTemplateResource::fromRecord(
                app(EventTemplateQueryService::class)->show($templateId, $actor),
            );
            $preview = EventTemplatePreviewResource::materialization(
                app(EventTemplateService::class)->previewMaterialization(
                    $templateId,
                    $input['template_version'],
                    $actor,
                    $input['start_time'],
                    $input['end_time'],
                    $input['overrides'],
                ),
            );
        } catch (EventTemplateException $exception) {
            return $this->eventsTemplateMutationFailure(
                $exception,
                $tenantSlug,
                'govuk-alpha.events.templates.materialize.form',
                ['templateId' => $templateId],
                $request,
            );
        }

        return $this->eventsTemplateMaterializeView(
            $tenantSlug,
            $template,
            $input,
            $preview,
        );
    }

    public function eventsTemplateMaterialize(
        Request $request,
        string $tenantSlug,
        int $templateId,
    ): RedirectResponse {
        $actor = $this->eventsTemplateActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $input = $this->eventsTemplateMaterializationInput($request);
        $key = $this->eventsTemplateIdempotencyKey($request);
        if ($input === null || $key === null) {
            return redirect()
                ->route('govuk-alpha.events.templates.materialize.form', compact('tenantSlug', 'templateId'))
                ->withErrors(['template' => __('event_templates.validation_error')]);
        }

        try {
            $result = app(EventTemplateService::class)->materialize(
                $templateId,
                $input['template_version'],
                $actor,
                $input['start_time'],
                $input['end_time'],
                $input['overrides'],
                $key,
            );
        } catch (EventTemplateException $exception) {
            return $this->eventsTemplateMutationFailure(
                $exception,
                $tenantSlug,
                'govuk-alpha.events.templates.materialize.form',
                ['templateId' => $templateId],
            );
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('govuk-alpha.events.templates.materialize.form', compact('tenantSlug', 'templateId'))
                ->withErrors(['template' => __('event_templates.save_error')]);
        }

        return redirect()->route('govuk-alpha.events.edit', [
            'tenantSlug' => $tenantSlug,
            'id' => (int) $result['event']->id,
        ]);
    }

    /** @param array<string,mixed> $template @param array<string,mixed> $values @param array<string,mixed>|null $preview */
    private function eventsTemplateMaterializeView(
        string $tenantSlug,
        array $template,
        array $values,
        ?array $preview,
    ): Response {
        return $this->eventsTemplatePrivateResponse($this->view(
            'accessible-frontend::event-template-materialize',
            [
                'title' => __('event_templates.materialize_title'),
                'tenantSlug' => $tenantSlug,
                'activeNav' => 'events',
                'template' => $template,
                'values' => $values,
                'preview' => $preview,
            ],
        ));
    }

    /** @param array<string,mixed> $template @return array<string,mixed> */
    private function eventsTemplateDefaultMaterializationValues(array $template): array
    {
        $configuration = is_array($template['version']['configuration'] ?? null)
            ? $template['version']['configuration']
            : [];

        return [
            'template_version' => (int) ($template['current_version'] ?? 0),
            'start_time' => '',
            'end_time' => '',
            'overrides' => [
                'title' => (string) ($configuration['title'] ?? ''),
                'location' => $configuration['location'] ?? null,
                'max_attendees' => $configuration['max_attendees'] ?? null,
                'timezone' => (string) ($configuration['timezone'] ?? 'UTC'),
                'all_day' => (bool) ($configuration['all_day'] ?? false),
            ],
        ];
    }

    /** @return array{template_version:int,start_time:string,end_time:?string,overrides:array<string,mixed>}|null */
    private function eventsTemplateMaterializationInput(Request $request): ?array
    {
        $version = $this->eventsTemplateNullablePositiveInteger(
            $request->input('template_version'),
        );
        $start = is_string($request->input('start_time'))
            ? trim((string) $request->input('start_time'))
            : '';
        $end = is_string($request->input('end_time'))
            ? trim((string) $request->input('end_time'))
            : '';
        $title = is_string($request->input('title'))
            ? trim((string) $request->input('title'))
            : '';
        $timezone = is_string($request->input('timezone'))
            ? trim((string) $request->input('timezone'))
            : '';
        $location = is_string($request->input('location'))
            ? trim((string) $request->input('location'))
            : '';
        $capacity = $request->input('max_attendees');
        $maxAttendees = $capacity === null || $capacity === ''
            ? null
            : $this->eventsTemplateNullablePositiveInteger($capacity);
        $allDay = $request->boolean('all_day');

        if ($version === null
            || $start === ''
            || $title === ''
            || mb_strlen($title) > 255
            || $timezone === ''
            || mb_strlen($timezone) > 64
            || mb_strlen($location) > 255
            || ($capacity !== null && $capacity !== '' && $maxAttendees === null)
            || ($allDay && $end === '')) {
            return null;
        }

        return [
            'template_version' => $version,
            'start_time' => $start,
            'end_time' => $end === '' ? null : $end,
            'overrides' => [
                'title' => $title,
                'location' => $location === '' ? null : $location,
                'max_attendees' => $maxAttendees,
                'timezone' => $timezone,
                'all_day' => $allDay,
            ],
        ];
    }

    private function eventsTemplateActor(string $tenantSlug): User|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('events'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', compact('tenantSlug'));
        }

        return $this->accessibleEventActor($userId);
    }

    private function eventsTemplateIdempotencyKey(Request $request): ?string
    {
        $key = $request->input('idempotency_key');
        if (! is_string($key)) {
            return null;
        }
        $key = trim($key);

        return $key !== '' && mb_strlen($key) <= 512 ? $key : null;
    }

    private function eventsTemplateNullablePositiveInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $parsed === false ? null : (int) $parsed;
    }

    private function eventsTemplateFailure(
        EventTemplateException $exception,
        string $tenantSlug,
        ?int $sourceEventId = null,
    ): RedirectResponse {
        if (in_array($exception->reasonCode, [
            'event_template_not_found',
            'event_template_source_not_found',
            'event_template_version_not_found',
        ], true)) {
            abort(404);
        }
        if (in_array($exception->reasonCode, [
            'event_template_authorization_denied',
            'event_template_actor_not_active',
        ], true)) {
            abort(403);
        }
        if (in_array($exception->reasonCode, [
            'event_template_schema_unavailable',
            'event_template_feature_disabled',
            'event_template_tenant_context_required',
        ], true)) {
            abort(503);
        }

        $route = $sourceEventId === null
            ? 'govuk-alpha.events.index'
            : 'govuk-alpha.events.show';
        $parameters = ['tenantSlug' => $tenantSlug];
        if ($sourceEventId !== null) {
            $parameters['id'] = $sourceEventId;
        }

        return redirect()->route($route, $parameters)
            ->withErrors(['template' => __('event_templates.load_error')]);
    }

    /** @param array<string,mixed> $parameters */
    private function eventsTemplateMutationFailure(
        EventTemplateException $exception,
        string $tenantSlug,
        string $route,
        array $parameters,
        ?Request $request = null,
    ): RedirectResponse {
        if (in_array($exception->reasonCode, [
            'event_template_not_found',
            'event_template_source_not_found',
            'event_template_version_not_found',
        ], true)) {
            abort(404);
        }
        if (in_array($exception->reasonCode, [
            'event_template_authorization_denied',
            'event_template_actor_not_active',
        ], true)) {
            abort(403);
        }
        if (in_array($exception->reasonCode, [
            'event_template_schema_unavailable',
            'event_template_feature_disabled',
            'event_template_tenant_context_required',
        ], true)) {
            abort(503);
        }

        $redirect = redirect()->route($route, ['tenantSlug' => $tenantSlug, ...$parameters])
            ->withErrors(['template' => __('event_templates.save_error')]);

        return $request === null
            ? $redirect
            : $redirect->withInput($request->except(['idempotency_key']));
    }

    private function eventsTemplateInvalidRedirect(
        string $tenantSlug,
        int $sourceEventId,
        ?int $templateId = null,
        bool $serverError = false,
    ): RedirectResponse {
        return redirect()->route('govuk-alpha.events.templates.capture.preview', [
            'tenantSlug' => $tenantSlug,
            'id' => $sourceEventId,
            'template_id' => $templateId,
        ])->withErrors([
            'template' => $serverError
                ? __('event_templates.save_error')
                : __('event_templates.validation_error'),
        ]);
    }

    private function eventsTemplatePrivateResponse(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Pragma', 'no-cache');
        $response->setVary('Cookie', false);

        return $response;
    }
}
