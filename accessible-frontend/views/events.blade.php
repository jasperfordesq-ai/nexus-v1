{{-- Copyright (c) 2024-2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @unless ($moduleDisabled)
        <p class="govuk-body">
            <a class="govuk-link" href="{{ route('govuk-alpha.events.calendar.feed', ['tenantSlug' => $tenantSlug]) }}">
                {{ __('govuk_alpha.events.calendar_feed_download') }}
            </a>
            <br><a class="govuk-link" href="{{ route('govuk-alpha.events.calendar.subscriptions', ['tenantSlug' => $tenantSlug]) }}">
                {{ __('govuk_alpha.events.calendar_subscriptions_link') }}
            </a>
            <br><span class="govuk-hint">{{ __('govuk_alpha.events.calendar_feed_hint') }}</span>
        </p>
    @endunless
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $hasFilters = !empty($filters['search'])
            || (($filters['when'] ?? 'upcoming') !== 'upcoming')
            || !empty($filters['category_id'])
            || (($filters['step_free'] ?? 'any') !== 'any');
        $formatDateTime = static fn ($value, string $timezone = 'UTC'): ?string => $value
            ? \Illuminate\Support\Carbon::parse($value)->setTimezone($timezone)->translatedFormat('j F Y, g:ia T')
            : null;
        $formatDate = static fn ($value, string $timezone = 'UTC'): ?string => $value
            ? \Illuminate\Support\Carbon::parse($value)->setTimezone($timezone)->translatedFormat('j F Y')
            : null;
    @endphp

    <span class="govuk-caption-l">{{ __('govuk_alpha.events.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.events.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.events.description') }}</p>

    @unless ($moduleDisabled)
        <p class="govuk-body">
            <a class="govuk-link" href="{{ route('govuk-alpha.events.browse', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_events.nav.browse_by_category') }}</a>
            @if (!empty($canModerateEvents))
                <br><a class="govuk-link" href="{{ route('govuk-alpha.events.moderation.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_events.moderation.queue_link') }}</a>
            @endif
        </p>
    @endunless

    @if (in_array(($status ?? ''), ['event-archived', 'event-deleted'], true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="event-archived-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="event-archived-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.events.archived') }}</p>
            </div>
        </div>
    @elseif (($status ?? '') === 'event-archive-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p>{{ __('govuk_alpha.events.archive_failed') }}</p></div>
            </div>
        </div>
    @endif

    @if ($moduleDisabled)
        <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="events-disabled-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="events-disabled-title">{{ __('govuk_alpha.states.error_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.states.events_disabled') }}</p>
                <p class="govuk-body">{{ __('govuk_alpha.events.module_disabled_detail', ['community' => $communityName]) }}</p>
            </div>
        </div>
    @else
        @if ($isAuthenticated)
            <p class="govuk-body">
                <a class="govuk-button" href="{{ route('govuk-alpha.events.create', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.actions.create_event') }}</a>
            </p>
        @else
            <div class="govuk-inset-text">
                <p class="govuk-body">
                    <a class="govuk-link" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']) }}">{{ __('govuk_alpha.events.create_auth_link') }}</a>
                </p>
            </div>
        @endif

        <form method="get" action="{{ route('govuk-alpha.events.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-7">
            <fieldset class="govuk-fieldset" aria-describedby="events-filter-hint">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                    <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.events.filters_title') }}</h2>
                </legend>
                <div id="events-filter-hint" class="govuk-hint">{{ __('govuk_alpha.events.filters_hint') }}</div>

                <div class="govuk-grid-row">
                    <div class="govuk-grid-column-full">
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="q">{{ __('govuk_alpha.events.search_label') }}</label>
                            <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha.events.search_hint') }}</div>
                            <input class="govuk-input govuk-!-width-two-thirds" id="q" name="q" type="search" value="{{ $filters['search'] ?? '' }}" aria-describedby="q-hint">
                        </div>
                    </div>
                </div>

                <div class="govuk-grid-row">
                    <div class="govuk-grid-column-one-third">
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="when">{{ __('govuk_alpha.events.when_label') }}</label>
                            <select class="govuk-select" id="when" name="when">
                                @foreach (['upcoming', 'past', 'all'] as $when)
                                    <option value="{{ $when }}" @selected(($filters['when'] ?? 'upcoming') === $when)>{{ __('govuk_alpha.events.when.' . $when) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="govuk-grid-column-one-third">
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="category_id">{{ __('govuk_alpha.events.category_label') }}</label>
                            <select class="govuk-select" id="category_id" name="category_id">
                                <option value="">{{ __('govuk_alpha.events.all_categories') }}</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category['id'] }}" @selected((int) ($filters['category_id'] ?? 0) === (int) $category['id'])>{{ $category['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="govuk-grid-column-one-third">
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="near">{{ __('govuk_alpha.near_me.label') }}</label>
                            <select class="govuk-select" id="near" name="near"@if (!empty($filters['near_no_location'])) aria-describedby="events-near-hint"@endif>
                                @foreach (['any', '5', '10', '25', '50'] as $nearOption)
                                    <option value="{{ $nearOption }}" @selected(($filters['near'] ?? 'any') === $nearOption)>{{ __('govuk_alpha.near_me.options.' . $nearOption) }}</option>
                                @endforeach
                            </select>
                            @if (!empty($filters['near_no_location']))
                                <p id="events-near-hint" class="govuk-hint govuk-!-margin-top-1">{{ __('govuk_alpha.near_me.no_location') }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="govuk-grid-row">
                    <div class="govuk-grid-column-one-half">
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="step_free">{{ __('event_accessibility.filters.step_free_label') }}</label>
                            <div id="step-free-hint" class="govuk-hint">{{ __('event_accessibility.filters.step_free_hint') }}</div>
                            <select class="govuk-select" id="step_free" name="step_free" aria-describedby="step-free-hint">
                                @foreach (['any', 'yes', 'no', 'unknown'] as $stepFreeOption)
                                    <option value="{{ $stepFreeOption }}" @selected(($filters['step_free'] ?? 'any') === $stepFreeOption)>{{ __('event_accessibility.filters.step_free_options.' . $stepFreeOption) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="govuk-button-group">
                    <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.actions.search') }}</button>
                    @if ($hasFilters)
                        <a class="govuk-link" href="{{ route('govuk-alpha.events.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.clear_filters') }}</a>
                    @endif
                </div>
            </fieldset>
        </form>

        <h2 class="govuk-heading-l">{{ __('govuk_alpha.events.results_title') }}</h2>
        <p class="govuk-body nexus-alpha-result-count" aria-live="polite">
            {{ trans_choice('govuk_alpha.events.result_count', count($items), ['count' => count($items)]) }}
        </p>

        @if ($error)
            <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                <div role="alert">
                    <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                    <div class="govuk-error-summary__body">
                        <p>{{ __('govuk_alpha.events.error_detail') }}</p>
                    </div>
                </div>
            </div>
        @elseif (empty($items))
            <div class="govuk-inset-text">
                <h2 class="govuk-heading-m">{{ __('govuk_alpha.states.empty_title') }}</h2>
                <p class="govuk-body">{{ __('govuk_alpha.events.empty') }}</p>
                @if ($hasFilters)
                    <p class="govuk-body">
                        <a class="govuk-link" href="{{ route('govuk-alpha.events.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.clear_filters') }}</a>
                    </p>
                @endif
            </div>
        @else
            <div class="nexus-alpha-card-list">
                @foreach ($items as $event)
                    @php
                        $schedule = is_array($event['schedule'] ?? null) ? $event['schedule'] : [];
                        $timezone = (string) ($schedule['timezone'] ?? 'UTC');
                        $isAllDay = (bool) ($schedule['all_day'] ?? false);
                        if ($isAllDay) {
                            $startDate = isset($schedule['start_at'])
                                ? \Illuminate\Support\Carbon::parse($schedule['start_at'])->setTimezone($timezone)
                                : null;
                            $exclusiveEnd = isset($schedule['end_at'])
                                ? \Illuminate\Support\Carbon::parse($schedule['end_at'])->setTimezone($timezone)
                                : null;
                            $inclusiveEnd = $exclusiveEnd !== null
                                && $startDate !== null
                                && $exclusiveEnd->gt($startDate)
                                ? $exclusiveEnd->copy()->subDay()
                                : null;
                            $start = $formatDate($schedule['start_at'] ?? null, $timezone);
                            $end = $inclusiveEnd !== null
                                && $startDate !== null
                                && !$inclusiveEnd->isSameDay($startDate)
                                ? $inclusiveEnd->translatedFormat('j F Y')
                                : null;
                        } else {
                            $start = $formatDateTime($schedule['start_at'] ?? null, $timezone);
                            $end = $formatDateTime($schedule['end_at'] ?? null, $timezone);
                        }
                        $categoryName = $event['category']['name'] ?? null;
                        $locationFacts = is_array($event['location'] ?? null) ? $event['location'] : [];
                        $locationLabel = $locationFacts['label'] ?? null;
                        $relationship = is_array($event['relationship'] ?? null) ? $event['relationship'] : [];
                        $metrics = is_array($event['metrics'] ?? null) ? $event['metrics'] : [];
                        $primaryImage = $event['primary_image']['url'] ?? null;
                    @endphp
                    <article
                        class="nexus-alpha-card"
                        data-events-contract-version="{{ $event['contract_version'] ?? '' }}"
                        data-event-timezone="{{ $timezone }}"
                        data-event-engagement-state="{{ $relationship['engagement']['state'] ?? '' }}"
                        data-event-registration-state="{{ $relationship['registration']['state'] ?? '' }}"
                        data-event-attendance-state="{{ $relationship['attendance']['state'] ?? '' }}">
                        <div class="nexus-alpha-listing-row">
                            @if ($primaryImage)
                                <div class="nexus-alpha-listing-row__media">
                                    <img class="nexus-alpha-card-thumb" src="{{ $primaryImage }}" alt="{{ __('govuk_alpha.events.image_alt', ['title' => $event['title']]) }}" width="120" height="90" loading="lazy" decoding="async">
                                </div>
                            @endif
                            <div class="nexus-alpha-listing-row__body">
                        <h3 class="govuk-heading-m govuk-!-margin-bottom-2">
                            <a class="govuk-link" href="{{ route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">{{ $event['title'] }}</a>
                        </h3>
                        <dl class="nexus-alpha-inline-list">
                            @if ($start)
                                <div>
                                    <dt>{{ __('govuk_alpha.events.starts') }}</dt>
                                    <dd>{{ $start }}@if($isAllDay) · {{ __('govuk_alpha.events.all_day') }}@endif</dd>
                                </div>
                            @endif
                            @if ($end)
                                <div>
                                    <dt>{{ __('govuk_alpha.events.ends') }}</dt>
                                    <dd>{{ $end }}</dd>
                                </div>
                            @endif
                            <div>
                                <dt>{{ __('govuk_alpha.events.location') }}</dt>
                                <dd>{{ $locationLabel ?: __('govuk_alpha.events.online') }}</dd>
                            </div>
                            @if ($categoryName)
                                <div>
                                    <dt>{{ __('govuk_alpha.events.category') }}</dt>
                                    <dd>{{ $categoryName }}</dd>
                                </div>
                            @endif
                        </dl>
                        @if (!empty($event['description']))
                            <p class="govuk-body govuk-!-margin-top-3">{{ \Illuminate\Support\Str::limit(strip_tags((string) $event['description']), 220) }}</p>
                        @endif
                        <p class="govuk-body-s nexus-alpha-meta">
                            {{ trans_choice('govuk_alpha.events.attendees', (int) ($metrics['confirmed_count'] ?? 0), ['count' => (int) ($metrics['confirmed_count'] ?? 0)]) }}
                            @if (!empty($metrics['interested_count']))
                                <br>{{ trans_choice('govuk_alpha.events.interested', (int) $metrics['interested_count'], ['count' => (int) $metrics['interested_count']]) }}
                            @endif
                        </p>
                        <a class="govuk-link govuk-link--no-visited-state" href="{{ route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">{{ __('govuk_alpha.actions.view_details') }}</a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
            @if (!empty($meta['has_more']) && !empty($meta['cursor']))
                <nav class="govuk-pagination govuk-pagination--block govuk-!-margin-top-6" aria-label="{{ __('govuk_alpha.events.pagination_label') }}">
                    <div class="govuk-pagination__next">
                        <a class="govuk-link govuk-pagination__link" href="{{ route('govuk-alpha.events.index', array_filter(['tenantSlug' => $tenantSlug, 'q' => $filters['search'] ?? null, 'when' => $filters['when'] ?? null, 'category_id' => $filters['category_id'] ?? null, 'step_free' => ($filters['step_free'] ?? 'any') !== 'any' ? $filters['step_free'] : null, 'near' => ($filters['near'] ?? 'any') !== 'any' ? $filters['near'] : null, 'cursor' => $meta['cursor']])) }}" rel="next">
                            <svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                                <path d="m8.107-0.0078125-1.4136 1.414 4.2926 4.293h-12.986v2h12.896l-4.1855 3.9766 1.377 1.4492 6.7441-6.4062-6.7246-6.7266z"></path>
                            </svg>
                            <span class="govuk-pagination__link-title">{{ __('govuk_alpha.actions.load_more') }}</span>
                            <span class="govuk-visually-hidden">:</span>
                            <span class="govuk-pagination__link-label">{{ __('govuk_alpha.events.more_results_label') }}</span>
                        </a>
                    </div>
                </nav>
            @endif
        @endif
    @endif
@endsection
