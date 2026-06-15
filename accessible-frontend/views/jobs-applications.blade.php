{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $statusLabel = function (?string $s): string {
            $s = (string) $s;
            $key = 'govuk_alpha.jobs_t2.app_status_' . $s;
            $label = __($key);
            return $label === $key ? ucfirst(str_replace('_', ' ', $s)) : $label;
        };
        $terminal = ['accepted', 'rejected', 'withdrawn'];
        $meta = $jobsMeta ?? ['has_more' => false, 'cursor' => null];
        $statusOptions = [
            '' => __('govuk_alpha.jobs_t2.status_filter_all'),
            'applied' => __('govuk_alpha.jobs_t2.app_status_applied'),
            'pending' => __('govuk_alpha.jobs_t2.app_status_pending'),
            'screening' => __('govuk_alpha.jobs_t2.app_status_screening'),
            'reviewed' => __('govuk_alpha.jobs_t2.app_status_reviewed'),
            'interview' => __('govuk_alpha.jobs_t2.app_status_interview'),
            'offer' => __('govuk_alpha.jobs_t2.app_status_offer'),
            'accepted' => __('govuk_alpha.jobs_t2.app_status_accepted'),
            'rejected' => __('govuk_alpha.jobs_t2.app_status_rejected'),
            'withdrawn' => __('govuk_alpha.jobs_t2.app_status_withdrawn'),
        ];
        $activeStatus = (string) ($statusFilter ?? '');
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha.jobs_t2.applications_caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.jobs_t2.applications_title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.jobs_t2.applications_description') }}</p>

    @include('accessible-frontend::partials.jobs-nav', ['jobsActiveTab' => 'applications'])

    @if (($status ?? null) === 'withdrawn')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-live="polite" aria-labelledby="apps-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="apps-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.jobs_t2.states.withdrawn') }}</p></div>
        </div>
    @elseif (($status ?? null) === 'withdraw-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert"><h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li>{{ __('govuk_alpha.jobs_t2.states.withdraw-failed') }}</li></ul></div></div>
        </div>
    @endif

    <form method="get" action="{{ route('govuk-alpha.jobs.applications', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
        <div class="govuk-form-group">
            <label class="govuk-label" for="status_filter">{{ __('govuk_alpha.jobs_t2.status_label') }}</label>
            <select class="govuk-select" id="status_filter" name="status_filter">
                @foreach ($statusOptions as $val => $label)
                    <option value="{{ $val }}" @selected($activeStatus === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.jobs_t2.apply_filters') }}</button>
    </form>

    @if (empty($applications))
        <p class="govuk-inset-text">{{ __('govuk_alpha.jobs_t2.applications_empty') }}</p>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($applications as $app)
                @php
                    $vac = $app['vacancy'] ?? [];
                    $vacTitle = trim((string) ($vac['title'] ?? '')) ?: __('govuk_alpha.jobs.title');
                    $appStatus = (string) ($app['status'] ?? 'applied');
                    $appliedOn = !empty($app['created_at']) ? \Illuminate\Support\Carbon::parse($app['created_at'])->translatedFormat('j F Y') : null;
                    $canWithdraw = !in_array($appStatus, $terminal, true);
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1">
                            <a class="govuk-link" href="{{ route('govuk-alpha.jobs.show', ['tenantSlug' => $tenantSlug, 'id' => (int) ($vac['id'] ?? $app['vacancy_id'] ?? 0)]) }}">{{ $vacTitle }}</a>
                        </h2>
                        <strong class="govuk-tag govuk-tag--blue">{{ $statusLabel($appStatus) }}</strong>
                    </div>
                    @if ($appliedOn)
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.jobs_t2.applied_on', ['date' => $appliedOn]) }}</p>
                    @endif
                    @if ($canWithdraw)
                        <form method="post" action="{{ route('govuk-alpha.jobs.applications.withdraw', ['tenantSlug' => $tenantSlug, 'appId' => (int) ($app['id'] ?? 0)]) }}" class="govuk-!-margin-top-2">
                            @csrf
                            <div class="govuk-warning-text govuk-!-margin-bottom-2">
                                <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                                <strong class="govuk-warning-text__text">
                                    <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.warning') }}</span>
                                    {{ __('govuk_alpha.jobs_t2.withdraw_warning') }}
                                </strong>
                            </div>
                            <button type="submit" class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.jobs_t2.withdraw_button') }}</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>

        @if (!empty($meta['has_more']) && !empty($meta['cursor']))
            <nav class="govuk-pagination govuk-pagination--block govuk-!-margin-top-6" aria-label="{{ __('govuk_alpha.members.pagination_label') }}">
                <div class="govuk-pagination__next">
                    <a class="govuk-link govuk-pagination__link" rel="next" href="{{ route('govuk-alpha.jobs.applications', array_filter(['tenantSlug' => $tenantSlug, 'status_filter' => $activeStatus !== '' ? $activeStatus : null, 'cursor' => $meta['cursor']], fn ($v) => $v !== null)) }}">
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
