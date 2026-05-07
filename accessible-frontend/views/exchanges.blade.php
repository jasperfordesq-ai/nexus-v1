{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $statusOptions = ['active', 'pending_provider', 'pending_broker', 'accepted', 'in_progress', 'pending_confirmation', 'completed', 'cancelled', 'disputed'];
        $formatDate = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y') : null;
    @endphp

    <span class="govuk-caption-l">{{ __('govuk_alpha.nav.exchanges') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.exchanges.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.exchanges.description') }}</p>

    @if (!$workflowEnabled)
        <div class="govuk-notification-banner" role="region" aria-labelledby="exchange-disabled-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="exchange-disabled-title">{{ __('govuk_alpha.exchanges.disabled_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-body">{{ __('govuk_alpha.exchanges.disabled_detail') }}</p>
            </div>
        </div>
    @endif

    <form method="get" action="{{ route('govuk-alpha.exchanges.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-7">
        <fieldset class="govuk-fieldset">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.exchanges.filters_title') }}</h2>
            </legend>
            <div class="govuk-form-group">
                <label class="govuk-label" for="status_filter">{{ __('govuk_alpha.exchanges.status_label') }}</label>
                <select class="govuk-select" id="status_filter" name="status_filter">
                    <option value="">{{ __('govuk_alpha.exchanges.statuses.all') }}</option>
                    @foreach ($statusOptions as $statusOption)
                        <option value="{{ $statusOption }}" @selected(($filters['status_filter'] ?? null) === $statusOption)>{{ __('govuk_alpha.exchanges.statuses.' . $statusOption) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="nexus-alpha-actions">
                <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.actions.apply_filters') }}</button>
                @if (!empty($filters['status_filter']))
                    <a class="govuk-link" href="{{ route('govuk-alpha.exchanges.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.clear_filters') }}</a>
                @endif
            </div>
        </fieldset>
    </form>

    <h2 class="govuk-heading-l">{{ __('govuk_alpha.exchanges.results_title') }}</h2>
    <p class="govuk-body nexus-alpha-result-count" aria-live="polite">
        {{ trans_choice('govuk_alpha.exchanges.result_count', count($items), ['count' => count($items)]) }}
    </p>

    @if (empty($items))
        <div class="govuk-inset-text">
            <h2 class="govuk-heading-m">{{ __('govuk_alpha.states.empty_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha.exchanges.empty') }}</p>
        </div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($items as $exchange)
                @php
                    $statusKey = $exchange['status'] ?? 'pending_provider';
                    $created = $formatDate($exchange['created_at'] ?? null);
                    $otherName = (int) ($exchange['requester_id'] ?? 0) === $currentUserId
                        ? ($exchange['provider_name'] ?? __('govuk_alpha.members.unknown_member'))
                        : ($exchange['requester_name'] ?? __('govuk_alpha.members.unknown_member'));
                @endphp
                <article class="nexus-alpha-card">
                    <h3 class="govuk-heading-m govuk-!-margin-bottom-2">
                        <a class="govuk-link" href="{{ route('govuk-alpha.exchanges.show', ['tenantSlug' => $tenantSlug, 'id' => $exchange['id']]) }}">{{ $exchange['listing_title'] ?? __('govuk_alpha.exchanges.detail_title') }}</a>
                    </h3>
                    <strong class="govuk-tag">{{ __('govuk_alpha.exchanges.statuses.' . $statusKey) }}</strong>
                    <dl class="nexus-alpha-inline-list govuk-!-margin-top-3">
                        <div>
                            <dt>{{ __('govuk_alpha.exchanges.proposed_hours_label') }}</dt>
                            <dd>{{ __('govuk_alpha.exchanges.hours', ['count' => (float) ($exchange['proposed_hours'] ?? 0)]) }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('govuk_alpha.members.title') }}</dt>
                            <dd>{{ $otherName }}</dd>
                        </div>
                        @if ($created)
                            <div>
                                <dt>{{ __('govuk_alpha.exchanges.created_label') }}</dt>
                                <dd>{{ $created }}</dd>
                            </div>
                        @endif
                    </dl>
                    <p class="govuk-body govuk-!-margin-bottom-0">
                        <a class="govuk-link govuk-link--no-visited-state" href="{{ route('govuk-alpha.exchanges.show', ['tenantSlug' => $tenantSlug, 'id' => $exchange['id']]) }}">{{ __('govuk_alpha.actions.view_exchange') }}</a>
                    </p>
                </article>
            @endforeach
        </div>
        @if (!empty($meta['has_more']))
            <nav class="govuk-pagination govuk-pagination--block govuk-!-margin-top-6" aria-label="{{ __('govuk_alpha.exchanges.pagination_label') }}">
                <div class="govuk-pagination__next">
                    <a class="govuk-link govuk-pagination__link" href="{{ route('govuk-alpha.exchanges.index', ['tenantSlug' => $tenantSlug, 'status_filter' => $filters['status_filter'] ?? null, 'cursor' => $meta['cursor']]) }}" rel="next">
                        <svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                            <path d="m8.107-0.0078125-1.4136 1.414 4.2926 4.293h-12.986v2h12.896l-4.1855 3.9766 1.377 1.4492 6.7441-6.4062-6.7246-6.7266z"></path>
                        </svg>
                        <span class="govuk-pagination__link-title">{{ __('govuk_alpha.actions.load_more') }}</span>
                        <span class="govuk-visually-hidden">:</span>
                        <span class="govuk-pagination__link-label">{{ __('govuk_alpha.exchanges.more_results_label') }}</span>
                    </a>
                </div>
            </nav>
        @endif
    @endif
@endsection
