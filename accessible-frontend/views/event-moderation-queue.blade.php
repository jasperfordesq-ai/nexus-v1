{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $knownStatuses = ['approved', 'rejected', 'action-failed'];
        $safeStatus = in_array($status ?? '', $knownStatuses, true) ? $status : null;
        $page = (int) ($pagination['page'] ?? 1);
        $total = (int) ($pagination['total'] ?? 0);
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_events.common.back_to_events') }}</a>
    <span class="govuk-caption-l">{{ __('govuk_alpha_events.moderation.caption') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_events.moderation.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_events.moderation.intro') }}</p>

    @if ($safeStatus === 'approved' || $safeStatus === 'rejected')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="moderation-success-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="moderation-success-title">{{ __('govuk_alpha_events.common.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha_events.moderation.' . $safeStatus) }}</p>
            </div>
        </div>
    @elseif ($safeStatus === 'action-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_events.common.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p>{{ __('govuk_alpha_events.moderation.action_failed') }}</p></div>
            </div>
        </div>
    @endif

    <p class="govuk-body" aria-live="polite">{{ trans_choice('govuk_alpha_events.moderation.pending_count', $total, ['count' => $total]) }}</p>

    @if ($items === [])
        <div class="govuk-inset-text">
            <h2 class="govuk-heading-m">{{ __('govuk_alpha_events.moderation.empty_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha_events.moderation.empty_body') }}</p>
        </div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($items as $event)
                @php
                    $timezone = (string) ($event['timezone'] ?? 'UTC');
                    $start = $event['start_time']
                        ? \Illuminate\Support\Carbon::parse($event['start_time'])->setTimezone($timezone)
                        : null;
                    $end = $event['end_time']
                        ? \Illuminate\Support\Carbon::parse($event['end_time'])->setTimezone($timezone)
                        : null;
                    $submitted = $event['submitted_at']
                        ? \Illuminate\Support\Carbon::parse($event['submitted_at'])->setTimezone($timezone)
                        : null;
                @endphp
                <article class="nexus-alpha-card">
                    <h2 class="govuk-heading-m">{{ $event['title'] }}</h2>
                    @if (!empty($event['is_recurring_template']))
                        <strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha_events.moderation.recurring_series') }}</strong>
                    @endif
                    <dl class="govuk-summary-list govuk-!-margin-top-3">
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_events.moderation.organizer') }}</dt>
                            <dd class="govuk-summary-list__value">{{ $event['organizer_name'] }}</dd>
                        </div>
                        @if ($submitted)
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_events.moderation.submitted') }}</dt>
                                <dd class="govuk-summary-list__value">{{ $submitted->translatedFormat('j F Y, g:ia T') }}</dd>
                            </div>
                        @endif
                        @if ($start)
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_events.moderation.schedule') }}</dt>
                                <dd class="govuk-summary-list__value">
                                    {{ $event['all_day'] ? $start->translatedFormat('j F Y') : $start->translatedFormat('j F Y, g:ia T') }}
                                    @if ($end)
                                        {{ __('govuk_alpha_events.moderation.schedule_to') }}
                                        {{ $event['all_day'] ? $end->translatedFormat('j F Y') : $end->translatedFormat('j F Y, g:ia T') }}
                                    @endif
                                    @if (!empty($event['all_day']))
                                        <br>{{ __('govuk_alpha_events.moderation.all_day') }}
                                    @endif
                                </dd>
                            </div>
                        @endif
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_events.moderation.location') }}</dt>
                            <dd class="govuk-summary-list__value">
                                {{ $event['location'] ?: (!empty($event['is_online']) ? __('govuk_alpha_events.moderation.online') : __('govuk_alpha_events.moderation.location_unavailable')) }}
                            </dd>
                        </div>
                    </dl>
                    <h3 class="govuk-heading-s">{{ __('govuk_alpha_events.moderation.description') }}</h3>
                    <p class="govuk-body">{{ $event['description'] }}</p>
                    <div class="govuk-button-group">
                        <a class="govuk-button" href="{{ route('govuk-alpha.events.moderation.approve.confirm', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha_events.moderation.approve') }}</a>
                        <a class="govuk-button govuk-button--warning" href="{{ route('govuk-alpha.events.moderation.reject.confirm', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha_events.moderation.reject') }}</a>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    @if (!empty($pagination['has_previous']) || !empty($pagination['has_next']))
        <nav class="govuk-pagination govuk-!-margin-top-6" aria-label="{{ __('govuk_alpha_events.moderation.pagination_label') }}">
            @if (!empty($pagination['has_previous']))
                <div class="govuk-pagination__prev">
                    <a class="govuk-link govuk-pagination__link" rel="prev" href="{{ route('govuk-alpha.events.moderation.index', ['tenantSlug' => $tenantSlug, 'page' => $page - 1]) }}">{{ __('govuk_alpha_events.moderation.previous') }}</a>
                </div>
            @endif
            @if (!empty($pagination['has_next']))
                <div class="govuk-pagination__next">
                    <a class="govuk-link govuk-pagination__link" rel="next" href="{{ route('govuk-alpha.events.moderation.index', ['tenantSlug' => $tenantSlug, 'page' => $page + 1]) }}">{{ __('govuk_alpha_events.moderation.next') }}</a>
                </div>
            @endif
        </nav>
    @endif
@endsection
