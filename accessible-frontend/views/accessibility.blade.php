{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $contactHref = route('govuk-alpha.contact', ['tenantSlug' => $tenantSlug]);
        $featureKeys = ['keyboard', 'visual', 'screen_reader', 'responsive'];
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <a class="govuk-back-link" href="{{ route('govuk-alpha.legal.hub', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.legal.back_to_hub') }}</a>

            <span class="govuk-caption-l">{{ __('govuk_alpha.accessibility.caption', ['community' => $communityName]) }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.accessibility.title') }}</h1>
            <p class="govuk-body-l">{{ __('govuk_alpha.accessibility.intro', ['name' => $communityName]) }}</p>

            <h2 class="govuk-heading-m">{{ __('govuk_alpha.accessibility.commitment_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha.accessibility.commitment_body') }}</p>

            <h2 class="govuk-heading-m">{{ __('govuk_alpha.accessibility.conformance_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha.accessibility.conformance_body') }}</p>

            <dl class="govuk-summary-list">
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha.accessibility.standard_label') }}</dt>
                    <dd class="govuk-summary-list__value">{{ __('govuk_alpha.accessibility.standard_value') }}</dd>
                </div>
            </dl>

            <h2 class="govuk-heading-m">{{ __('govuk_alpha.accessibility.features_title') }}</h2>
            @foreach ($featureKeys as $feature)
                <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha.accessibility.features.' . $feature . '.title') }}</h3>
                <p class="govuk-body">{{ __('govuk_alpha.accessibility.features.' . $feature . '.description') }}</p>
            @endforeach

            <h2 class="govuk-heading-m">{{ __('govuk_alpha.accessibility.limitations_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha.accessibility.limitations_body') }}</p>

            <h2 class="govuk-heading-m">{{ __('govuk_alpha.accessibility.feedback_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha.accessibility.feedback_body') }}</p>
            <p class="govuk-body">
                <a class="govuk-link" href="{{ $contactHref }}">{{ __('govuk_alpha.accessibility.feedback_cta') }}</a>
            </p>

            <h2 class="govuk-heading-m">{{ __('govuk_alpha.accessibility.testing_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha.accessibility.testing_body') }}</p>
        </div>
    </div>
@endsection
