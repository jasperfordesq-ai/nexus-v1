{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <span class="govuk-caption-xl">{{ __('govuk_alpha.jobs_t2.saved_caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.jobs_t2.saved_title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.jobs_t2.saved_description') }}</p>

    @include('accessible-frontend::partials.jobs-nav', ['jobsActiveTab' => 'saved'])

    @if (($status ?? null) === 'unsaved')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="saved-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="saved-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.jobs_t2.states.unsaved') }}</p></div>
        </div>
    @endif

    @php $meta = $jobsMeta ?? ['has_more' => false, 'cursor' => null]; @endphp

    @if (empty($jobs))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.jobs_t2.saved_empty') }}</p></div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($jobs as $job)
                @include('accessible-frontend::partials.job-card', ['job' => $job, 'showUnsave' => true, 'unsaveFrom' => 'saved'])
            @endforeach
        </div>

        @if (!empty($meta['has_more']) && !empty($meta['cursor']))
            <nav class="govuk-pagination govuk-pagination--block govuk-!-margin-top-6" aria-label="{{ __('govuk_alpha.members.pagination_label') }}">
                <div class="govuk-pagination__next">
                    <a class="govuk-link govuk-pagination__link" rel="next" href="{{ route('govuk-alpha.jobs.saved', ['tenantSlug' => $tenantSlug, 'cursor' => $meta['cursor']]) }}">
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
