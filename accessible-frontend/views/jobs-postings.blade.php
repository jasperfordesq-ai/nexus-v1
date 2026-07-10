{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $meta = $jobsMeta ?? ['has_more' => false, 'cursor' => null];
        $statusTag = function (string $s): string {
            $key = 'govuk_alpha.jobs_t3.status_' . $s;
            $label = __($key);
            return $label === $key ? ucfirst($s) : $label;
        };
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha.jobs_t3.mine_caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.jobs_t3.mine_title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.jobs_t3.mine_description') }}</p>

    @include('accessible-frontend::partials.jobs-nav', ['jobsActiveTab' => 'mine'])

    @if (($status ?? null) === 'deleted')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="mine-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="mine-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.jobs_t3.states.deleted') }}</p></div>
        </div>
    @elseif (($status ?? null) === 'delete-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert"><h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li>{{ __('govuk_alpha.jobs_t3.states.delete-failed') }}</li></ul></div></div>
        </div>
    @endif

    <div class="nexus-alpha-actions govuk-!-margin-bottom-6">
        <a class="govuk-button" data-module="govuk-button" href="{{ route('govuk-alpha.jobs.create', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.jobs_t3.post_button') }}</a>
        @if (\Illuminate\Support\Facades\Route::has('govuk-alpha.jobs.onboarding'))
            <a class="govuk-button govuk-button--secondary" data-module="govuk-button" href="{{ route('govuk-alpha.jobs.onboarding', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_jobs.onboarding.title') }}</a>
        @endif
    </div>

    @if (empty($jobs))
        @if (\Illuminate\Support\Facades\Route::has('govuk-alpha.jobs.onboarding'))
            <div class="govuk-inset-text">
                <p class="govuk-body">{{ __('govuk_alpha.jobs_t3.mine_empty') }}</p>
                <p class="govuk-body govuk-!-margin-bottom-0"><a class="govuk-link" href="{{ route('govuk-alpha.jobs.onboarding', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_jobs.onboarding.title') }}</a></p>
            </div>
        @else
            <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.jobs_t3.mine_empty') }}</p></div>
        @endif
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($jobs as $job)
                @php
                    $pId = (int) ($job['id'] ?? 0);
                    $pTitle = trim((string) ($job['title'] ?? '')) ?: __('govuk_alpha.jobs.title');
                    $pStatus = (string) ($job['status'] ?? 'open');
                    $pApps = (int) ($job['applications_count'] ?? 0);
                    $pViews = (int) ($job['views_count'] ?? 0);
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1">
                            <a class="govuk-link" href="{{ route('govuk-alpha.jobs.show', ['tenantSlug' => $tenantSlug, 'id' => $pId]) }}">{{ $pTitle }}</a>
                        </h2>
                        <strong class="govuk-tag govuk-tag--blue">{{ $statusTag($pStatus) }}</strong>
                    </div>
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">
                        {{ trans_choice('govuk_alpha.jobs_t2.views_count', $pViews, ['count' => $pViews]) }}
                        &middot; {{ trans_choice('govuk_alpha.jobs_t2.applications_count', $pApps, ['count' => $pApps]) }}
                    </p>
                    <div class="nexus-alpha-actions govuk-!-margin-bottom-2">
                        <a class="govuk-link" href="{{ route('govuk-alpha.jobs.applicants', ['tenantSlug' => $tenantSlug, 'id' => $pId]) }}">{{ __('govuk_alpha.jobs_t3.manage_button') }}</a>
                        <a class="govuk-link" href="{{ route('govuk-alpha.jobs.edit', ['tenantSlug' => $tenantSlug, 'id' => $pId]) }}">{{ __('govuk_alpha.jobs_t3.edit_button') }}</a>
                    </div>
                    <div class="nexus-alpha-actions">
                        <form method="post" action="{{ route('govuk-alpha.jobs.renew', ['tenantSlug' => $tenantSlug, 'id' => $pId]) }}" class="nexus-alpha-linkform">
                            @csrf
                            <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.jobs_t3.renew_button') }}</button>
                        </form>
                        <details class="govuk-details govuk-!-margin-bottom-0" data-module="govuk-details">
                            <summary class="govuk-details__summary">
                                <span class="govuk-details__summary-text">{{ __('govuk_alpha.jobs_t3.delete_button') }}</span>
                            </summary>
                            <div class="govuk-details__text">
                                <div class="govuk-warning-text">
                                    <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                                    <strong class="govuk-warning-text__text">
                                        <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.warning_prefix') }}</span>
                                        {{ __('govuk_alpha.jobs_t3.delete_warning') }}
                                    </strong>
                                </div>
                                <form method="post" action="{{ route('govuk-alpha.jobs.delete', ['tenantSlug' => $tenantSlug, 'id' => $pId]) }}" class="nexus-alpha-linkform">
                                    @csrf
                                    <button type="submit" class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.ux.confirm_delete_button') }}</button>
                                </form>
                            </div>
                        </details>
                    </div>
                </article>
            @endforeach
        </div>

        @if (!empty($meta['has_more']) && !empty($meta['cursor']))
            <nav class="govuk-pagination govuk-pagination--block govuk-!-margin-top-6" aria-label="{{ __('govuk_alpha.members.pagination_label') }}">
                <div class="govuk-pagination__next">
                    <a class="govuk-link govuk-pagination__link" rel="next" href="{{ route('govuk-alpha.jobs.mine', ['tenantSlug' => $tenantSlug, 'cursor' => $meta['cursor']]) }}">
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
