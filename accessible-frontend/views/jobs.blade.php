{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <span class="govuk-caption-xl">{{ __('govuk_alpha.jobs.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.jobs.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.jobs.description') }}</p>

    @include('accessible-frontend::partials.jobs-nav')

    @if (($status ?? null) === 'saved')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-live="polite" aria-labelledby="jobs-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="jobs-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.jobs_t2.states.saved') }}</p></div>
        </div>
    @endif

    @php
        $f = $jobsFilters ?? ['q' => '', 'type' => '', 'commitment' => '', 'sort' => 'newest', 'remote' => false];
        $meta = $jobsMeta ?? ['total' => 0, 'has_more' => false, 'offset' => 0, 'per_page' => 12];
        $typeOptions = [
            '' => __('govuk_alpha.jobs_t2.type_all'),
            'paid' => __('govuk_alpha.jobs.type_paid'),
            'volunteer' => __('govuk_alpha.jobs.type_volunteer'),
            'timebank' => __('govuk_alpha.jobs.type_timebank'),
        ];
        $commitmentOptions = [
            '' => __('govuk_alpha.jobs_t2.commitment_all'),
            'full_time' => __('govuk_alpha.jobs_t2.commitment_full_time'),
            'part_time' => __('govuk_alpha.jobs_t2.commitment_part_time'),
            'flexible' => __('govuk_alpha.jobs_t2.commitment_flexible'),
            'one_off' => __('govuk_alpha.jobs_t2.commitment_one_off'),
        ];
        $sortOptions = [
            'newest' => __('govuk_alpha.jobs_t2.sort_newest'),
            'deadline' => __('govuk_alpha.jobs_t2.sort_deadline'),
            'salary_desc' => __('govuk_alpha.jobs_t2.sort_salary_desc'),
        ];
    @endphp

    <form method="get" action="{{ route('govuk-alpha.jobs.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
        <div class="govuk-form-group">
            <label class="govuk-label" for="q">{{ __('govuk_alpha.jobs.search_label') }}</label>
            <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha.jobs.search_hint') }}</div>
            <input class="govuk-input govuk-!-width-two-thirds" id="q" name="q" type="search" value="{{ $f['q'] }}" aria-describedby="q-hint">
        </div>

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-one-third">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="type">{{ __('govuk_alpha.jobs_t2.type_label') }}</label>
                    <select class="govuk-select" id="type" name="type">
                        @foreach ($typeOptions as $val => $label)
                            <option value="{{ $val }}" @selected($f['type'] === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="govuk-grid-column-one-third">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="commitment">{{ __('govuk_alpha.jobs_t2.commitment_label') }}</label>
                    <select class="govuk-select" id="commitment" name="commitment">
                        @foreach ($commitmentOptions as $val => $label)
                            <option value="{{ $val }}" @selected($f['commitment'] === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="govuk-grid-column-one-third">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="sort">{{ __('govuk_alpha.jobs_t2.sort_label') }}</label>
                    <select class="govuk-select" id="sort" name="sort">
                        @foreach ($sortOptions as $val => $label)
                            <option value="{{ $val }}" @selected($f['sort'] === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="govuk-checkboxes govuk-checkboxes--small govuk-!-margin-bottom-4" data-module="govuk-checkboxes">
            <div class="govuk-checkboxes__item">
                <input class="govuk-checkboxes__input" id="remote" name="remote" type="checkbox" value="1" @checked($f['remote'])>
                <label class="govuk-label govuk-checkboxes__label" for="remote">{{ __('govuk_alpha.jobs_t2.remote_label') }}</label>
            </div>
        </div>

        <div class="nexus-alpha-actions">
            <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.jobs_t2.apply_filters') }}</button>
            <a class="govuk-link" href="{{ route('govuk-alpha.jobs.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.jobs_t2.clear_filters') }}</a>
        </div>
    </form>

    @if (empty($jobs))
        <p class="govuk-inset-text">{{ __('govuk_alpha.jobs.empty') }}</p>
    @else
        <p class="govuk-body govuk-!-font-weight-bold">{{ trans_choice('govuk_alpha.jobs_t2.results_count', (int) ($meta['total'] ?? 0), ['count' => (int) ($meta['total'] ?? 0)]) }}</p>
        <div class="nexus-alpha-card-list">
            @foreach ($jobs as $job)
                @include('accessible-frontend::partials.job-card', ['job' => $job])
            @endforeach
        </div>

        @if (!empty($meta['has_more']))
            <nav class="govuk-pagination govuk-pagination--block govuk-!-margin-top-6" aria-label="{{ __('govuk_alpha.members.pagination_label') }}">
                <div class="govuk-pagination__next">
                    <a class="govuk-link govuk-pagination__link" rel="next" href="{{ route('govuk-alpha.jobs.index', array_filter([
                        'tenantSlug' => $tenantSlug,
                        'q' => $f['q'] !== '' ? $f['q'] : null,
                        'type' => $f['type'] !== '' ? $f['type'] : null,
                        'commitment' => $f['commitment'] !== '' ? $f['commitment'] : null,
                        'sort' => $f['sort'] !== 'newest' ? $f['sort'] : null,
                        'remote' => $f['remote'] ? '1' : null,
                        'offset' => (int) ($meta['offset'] ?? 0) + (int) ($meta['per_page'] ?? 12),
                    ], fn ($v) => $v !== null)) }}">
                        <svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                            <path d="m8.107-0.0078125-1.4136 1.414 4.2926 4.293h-12.986v2h12.896l-4.1855 3.9766 1.377 1.4492 6.7441-6.4062-6.7246-6.7266z"></path>
                        </svg>
                        <span class="govuk-pagination__link-title">{{ __('govuk_alpha.actions.load_more') }}</span>
                        <span class="govuk-visually-hidden">:</span>
                        <span class="govuk-pagination__link-label">{{ __('govuk_alpha.members.more_results_label') }}</span>
                    </a>
                </div>
            </nav>
        @endif
    @endif
@endsection
