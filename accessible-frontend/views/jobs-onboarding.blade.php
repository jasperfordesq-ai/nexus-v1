{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $returning = (bool) ($hasPosted ?? false);
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha_jobs.onboarding.caption') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_jobs.onboarding.title') }}</h1>

    @include('accessible-frontend::partials.jobs-nav', ['jobsActiveTab' => 'mine'])

    @if ($returning)
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_jobs.onboarding.returning_heading') }}</h2>
        <p class="govuk-body-l">{{ __('govuk_alpha_jobs.onboarding.returning_body') }}</p>
    @else
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_jobs.onboarding.welcome_heading') }}</h2>
        <p class="govuk-body-l">{{ __('govuk_alpha_jobs.onboarding.welcome_body') }}</p>
    @endif

    {{-- How it works --}}
    <h2 class="govuk-heading-m">{{ __('govuk_alpha_jobs.onboarding.how_heading') }}</h2>
    <ol class="govuk-list govuk-list--number govuk-!-margin-bottom-6">
        <li>{{ __('govuk_alpha_jobs.onboarding.step_1') }}</li>
        <li>{{ __('govuk_alpha_jobs.onboarding.step_2') }}</li>
        <li>{{ __('govuk_alpha_jobs.onboarding.step_3') }}</li>
    </ol>

    {{-- Pay transparency note --}}
    <div class="govuk-inset-text">
        <p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha_jobs.onboarding.transparency_note') }}</p>
    </div>

    {{-- Tips --}}
    <h2 class="govuk-heading-m">{{ __('govuk_alpha_jobs.onboarding.tips_heading') }}</h2>
    <ul class="govuk-list govuk-list--bullet govuk-!-margin-bottom-6">
        <li>{{ __('govuk_alpha_jobs.onboarding.tip_1') }}</li>
        <li>{{ __('govuk_alpha_jobs.onboarding.tip_2') }}</li>
        <li>{{ __('govuk_alpha_jobs.onboarding.tip_3') }}</li>
        <li>{{ __('govuk_alpha_jobs.onboarding.tip_4') }}</li>
    </ul>

    {{-- Actions --}}
    <div class="nexus-alpha-actions govuk-!-margin-bottom-4">
        <a class="govuk-button" data-module="govuk-button" href="{{ route('govuk-alpha.jobs.create', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_jobs.onboarding.start_button') }}</a>
        <a class="govuk-button govuk-button--secondary" data-module="govuk-button" href="{{ route('govuk-alpha.jobs.mine', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_jobs.onboarding.view_mine_button') }}</a>
    </div>

    <p class="govuk-body">
        <a class="govuk-link" href="{{ route('govuk-alpha.jobs.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_jobs.onboarding.browse_link') }}</a>
    </p>
@endsection
