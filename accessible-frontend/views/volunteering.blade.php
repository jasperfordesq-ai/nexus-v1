{{-- Copyright (c) 2024-2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $hasFilters = !empty($filters['search']) || !empty($filters['category_id']) || !empty($filters['is_remote']);
        $formatDate = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y') : null;
    @endphp

    <span class="govuk-caption-l">{{ __('govuk_alpha.volunteering.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.volunteering.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.volunteering.description') }}</p>

    @if ($moduleDisabled)
        <div class="govuk-notification-banner" role="region" aria-labelledby="volunteering-disabled-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="volunteering-disabled-title">{{ __('govuk_alpha.states.error_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.states.volunteering_disabled') }}</p>
                <p class="govuk-body">{{ __('govuk_alpha.volunteering.module_disabled_detail', ['community' => $communityName]) }}</p>
            </div>
        </div>
    @else
        @if ($requiresAuth)
            <div class="govuk-notification-banner" role="region" aria-labelledby="volunteering-auth-title">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="volunteering-auth-title">{{ __('govuk_alpha.states.auth_required') }}</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-body">{{ __('govuk_alpha.volunteering.auth_required_detail') }}</p>
                </div>
            </div>
        @else
            @php
                $summary = $hoursSummary ?? [];
            @endphp
            <h2 class="govuk-heading-l">{{ __('govuk_alpha.volunteering.hours_summary_title') }}</h2>
            <dl class="nexus-alpha-stat-grid">
                <div class="nexus-alpha-stat">
                    <dt>{{ __('govuk_alpha.volunteering.approved_hours') }}</dt>
                    <dd>{{ number_format((float) ($summary['total_approved_hours'] ?? $summary['approved_hours'] ?? 0), 1) }}</dd>
                </div>
                <div class="nexus-alpha-stat">
                    <dt>{{ __('govuk_alpha.volunteering.pending_hours') }}</dt>
                    <dd>{{ number_format((float) ($summary['pending_hours'] ?? 0), 1) }}</dd>
                </div>
                <div class="nexus-alpha-stat">
                    <dt>{{ __('govuk_alpha.volunteering.this_month_hours') }}</dt>
                    <dd>{{ number_format((float) ($summary['this_month_hours'] ?? 0), 1) }}</dd>
                </div>
            </dl>
            <p class="govuk-body">
                <a class="govuk-link" href="{{ route('govuk-alpha.volunteering.hours', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.volunteering.log_hours_title') }}</a>
            </p>
        @endif

        <form method="get" action="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-7 govuk-!-margin-top-7">
            <fieldset class="govuk-fieldset" aria-describedby="volunteering-filter-hint">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                    <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.volunteering.filters_title') }}</h2>
                </legend>
                <div id="volunteering-filter-hint" class="govuk-hint">{{ __('govuk_alpha.volunteering.filters_hint') }}</div>
                <div class="govuk-grid-row">
                    <div class="govuk-grid-column-one-half">
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="q">{{ __('govuk_alpha.volunteering.search_label') }}</label>
                            <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha.volunteering.search_hint') }}</div>
                            <input class="govuk-input" id="q" name="q" type="search" value="{{ $filters['search'] ?? '' }}" aria-describedby="q-hint">
                        </div>
                    </div>
                    <div class="govuk-grid-column-one-quarter">
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="category_id">{{ __('govuk_alpha.volunteering.category_label') }}</label>
                            <select class="govuk-select" id="category_id" name="category_id">
                                <option value="">{{ __('govuk_alpha.volunteering.all_categories') }}</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category['id'] }}" @selected((int) ($filters['category_id'] ?? 0) === (int) $category['id'])>{{ $category['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="govuk-grid-column-one-quarter">
                        <div class="govuk-form-group govuk-!-margin-top-6">
                            <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                                <div class="govuk-checkboxes__item">
                                    <input class="govuk-checkboxes__input" id="is_remote" name="is_remote" type="checkbox" value="1" @checked(!empty($filters['is_remote']))>
                                    <label class="govuk-label govuk-checkboxes__label" for="is_remote">{{ __('govuk_alpha.volunteering.remote_label') }}</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="nexus-alpha-actions">
                    <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.actions.search') }}</button>
                    @if ($hasFilters)
                        <a class="govuk-link" href="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.clear_filters') }}</a>
                    @endif
                </div>
            </fieldset>
        </form>

        <h2 class="govuk-heading-l">{{ __('govuk_alpha.volunteering.results_title') }}</h2>
        <p class="govuk-body nexus-alpha-result-count" aria-live="polite">
            {{ trans_choice('govuk_alpha.volunteering.result_count', count($items), ['count' => count($items)]) }}
        </p>

        @if ($error)
            <div class="govuk-error-summary" data-module="govuk-error-summary">
                <div role="alert">
                    <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                    <div class="govuk-error-summary__body">
                        <p>{{ __('govuk_alpha.volunteering.error_detail') }}</p>
                    </div>
                </div>
            </div>
        @elseif (empty($items))
            <div class="govuk-inset-text">
                <h2 class="govuk-heading-m">{{ __('govuk_alpha.states.empty_title') }}</h2>
                <p class="govuk-body">{{ __('govuk_alpha.volunteering.empty') }}</p>
            </div>
        @else
            <div class="nexus-alpha-card-list">
                @foreach ($items as $opportunity)
                    @php
                        $organizationName = $opportunity['organization']['name'] ?? null;
                        $categoryName = $opportunity['category']['name'] ?? $opportunity['category'] ?? null;
                        $start = $formatDate($opportunity['start_date'] ?? null);
                        $end = $formatDate($opportunity['end_date'] ?? null);
                    @endphp
                    <article class="nexus-alpha-card">
                        <h3 class="govuk-heading-m govuk-!-margin-bottom-2">
                            <a class="govuk-link" href="{{ route('govuk-alpha.volunteering.show', ['tenantSlug' => $tenantSlug, 'id' => $opportunity['id']]) }}">{{ $opportunity['title'] }}</a>
                        </h3>
                        @if (!empty($opportunity['is_remote']))
                            <strong class="govuk-tag govuk-tag--turquoise">{{ __('govuk_alpha.volunteering.remote') }}</strong>
                        @endif
                        <dl class="nexus-alpha-inline-list govuk-!-margin-top-2">
                            @if ($organizationName)
                                <div>
                                    <dt>{{ __('govuk_alpha.volunteering.organization') }}</dt>
                                    <dd>{{ $organizationName }}</dd>
                                </div>
                            @endif
                            @if (!empty($opportunity['location']))
                                <div>
                                    <dt>{{ __('govuk_alpha.volunteering.location') }}</dt>
                                    <dd>{{ $opportunity['location'] }}</dd>
                                </div>
                            @endif
                            @if ($categoryName)
                                <div>
                                    <dt>{{ __('govuk_alpha.volunteering.category_label') }}</dt>
                                    <dd>{{ $categoryName }}</dd>
                                </div>
                            @endif
                            @if ($start)
                                <div>
                                    <dt>{{ __('govuk_alpha.volunteering.start_date') }}</dt>
                                    <dd>{{ $start }}</dd>
                                </div>
                            @endif
                            @if ($end)
                                <div>
                                    <dt>{{ __('govuk_alpha.volunteering.end_date') }}</dt>
                                    <dd>{{ $end }}</dd>
                                </div>
                            @endif
                        </dl>
                        @if (!empty($opportunity['description']))
                            <p class="govuk-body govuk-!-margin-top-3">{{ \Illuminate\Support\Str::limit(strip_tags((string) $opportunity['description']), 220) }}</p>
                        @endif
                        <a class="govuk-link govuk-link--no-visited-state" href="{{ route('govuk-alpha.volunteering.show', ['tenantSlug' => $tenantSlug, 'id' => $opportunity['id']]) }}">{{ __('govuk_alpha.actions.view_details') }}</a>
                    </article>
                @endforeach
            </div>
            @if (!empty($meta['has_more']) && !empty($meta['cursor']))
                <nav class="govuk-pagination govuk-pagination--block govuk-!-margin-top-6" aria-label="{{ __('govuk_alpha.volunteering.pagination_label') }}">
                    <div class="govuk-pagination__next">
                        <a class="govuk-link govuk-pagination__link" href="{{ route('govuk-alpha.volunteering.index', array_filter(['tenantSlug' => $tenantSlug, 'q' => $filters['search'] ?? null, 'category_id' => $filters['category_id'] ?? null, 'is_remote' => !empty($filters['is_remote']) ? 1 : null, 'cursor' => $meta['cursor']])) }}" rel="next">
                            <svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                                <path d="m8.107-0.0078125-1.4136 1.414 4.2926 4.293h-12.986v2h12.896l-4.1855 3.9766 1.377 1.4492 6.7441-6.4062-6.7246-6.7266z"></path>
                            </svg>
                            <span class="govuk-pagination__link-title">{{ __('govuk_alpha.actions.load_more') }}</span>
                            <span class="govuk-visually-hidden">:</span>
                            <span class="govuk-pagination__link-label">{{ __('govuk_alpha.volunteering.more_results_label') }}</span>
                        </a>
                    </div>
                </nav>
            @endif
        @endif
    @endif
@endsection
