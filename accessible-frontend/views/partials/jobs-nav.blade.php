{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
{{--
    Jobs sub-navigation — a server-rendered GOV.UK tab strip (plain links, no JS).
    Each destination is rendered only once its route exists, so the strip grows
    automatically as later waves add my-postings / alerts / post-a-job.
--}}
@php
    $jobsTabs = [
        'browse' => [
            'label' => __('govuk_alpha.jobs_t2.nav_browse'),
            'href' => route('govuk-alpha.jobs.index', ['tenantSlug' => $tenantSlug]),
        ],
    ];
    if (\Illuminate\Support\Facades\Route::has('govuk-alpha.jobs.saved')) {
        $jobsTabs['saved'] = [
            'label' => __('govuk_alpha.jobs_t2.nav_saved'),
            'href' => route('govuk-alpha.jobs.saved', ['tenantSlug' => $tenantSlug]),
        ];
    }
    if (\Illuminate\Support\Facades\Route::has('govuk-alpha.jobs.applications')) {
        $jobsTabs['applications'] = [
            'label' => __('govuk_alpha.jobs_t2.nav_applications'),
            'href' => route('govuk-alpha.jobs.applications', ['tenantSlug' => $tenantSlug]),
        ];
    }
    if (\Illuminate\Support\Facades\Route::has('govuk-alpha.jobs.responses')) {
        $jobsTabs['responses'] = [
            'label' => __('govuk_alpha_jobs.responses.title'),
            'href' => route('govuk-alpha.jobs.responses', ['tenantSlug' => $tenantSlug]),
        ];
    }
    if (\Illuminate\Support\Facades\Route::has('govuk-alpha.jobs.alerts')) {
        $jobsTabs['alerts'] = [
            'label' => __('govuk_alpha.jobs_t4.nav_alerts'),
            'href' => route('govuk-alpha.jobs.alerts', ['tenantSlug' => $tenantSlug]),
        ];
    }
    if (\Illuminate\Support\Facades\Route::has('govuk-alpha.jobs.mine')) {
        $jobsTabs['mine'] = [
            'label' => __('govuk_alpha.jobs_t3.nav_mine'),
            'href' => route('govuk-alpha.jobs.mine', ['tenantSlug' => $tenantSlug]),
        ];
    }
    if (\Illuminate\Support\Facades\Route::has('govuk-alpha.jobs.create')) {
        $jobsTabs['create'] = [
            'label' => __('govuk_alpha.jobs_t3.nav_create'),
            'href' => route('govuk-alpha.jobs.create', ['tenantSlug' => $tenantSlug]),
        ];
    }
    $jobsActive = $jobsActiveTab ?? 'browse';
@endphp
<div class="govuk-tabs govuk-!-margin-top-2 govuk-!-margin-bottom-4">
    <h2 class="govuk-tabs__title">{{ __('govuk_alpha.jobs.title') }}</h2>
    <ul class="govuk-tabs__list">
        @foreach ($jobsTabs as $tabKey => $tab)
            <li class="govuk-tabs__list-item{{ $jobsActive === $tabKey ? ' govuk-tabs__list-item--selected' : '' }}">
                <a class="govuk-tabs__tab" href="{{ $tab['href'] }}" @if ($jobsActive === $tabKey) aria-current="page" @endif>{{ $tab['label'] }}</a>
            </li>
        @endforeach
    </ul>
</div>
