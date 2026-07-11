{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $contactHref = route('govuk-alpha.contact', ['tenantSlug' => $tenantSlug]);
        $guidelinesHref = route('govuk-alpha.legal.community-guidelines', ['tenantSlug' => $tenantSlug]);
        $sectionKeys = [
            'how_exchanges', 'what_we_do', 'what_we_dont', 'precautions',
            'vetting_attestation', 'insurance', 'disputes', 'responsibilities', 'rights',
        ];
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-l">{{ __('govuk_alpha.trust_safety.caption', ['community' => $communityName]) }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.trust_safety.title') }}</h1>
            <p class="govuk-body-l">{{ __('govuk_alpha.trust_safety.subtitle', ['name' => $communityName]) }}</p>

            <div class="govuk-warning-text">
                <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                <strong class="govuk-warning-text__text">
                    <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.important') }}</span>
                    <span class="govuk-!-font-weight-bold">{{ __('govuk_alpha.trust_safety.safeguarding_title') }}.</span>
                    {{ __('govuk_alpha.trust_safety.safeguarding_body') }}
                </strong>
            </div>

            @foreach ($sectionKeys as $key)
                @php
                    $base = 'govuk_alpha.trust_safety.sections.' . $key;
                    $intro = __($base . '.intro');
                    $items = __($base . '.items');
                @endphp
                <h2 class="govuk-heading-m">{{ __($base . '.heading') }}</h2>
                @if (is_string($intro) && trim($intro) !== '')
                    <p class="govuk-body">{{ $intro }}</p>
                @endif
                @if (is_array($items))
                    <ul class="govuk-list govuk-list--bullet">
                        @foreach ($items as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @endif
            @endforeach

            <h2 class="govuk-heading-m">{{ __('govuk_alpha.trust_safety.contact_cta_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha.trust_safety.contact_cta_body') }}</p>
            <div class="govuk-button-group">
                <a class="govuk-button" data-module="govuk-button" href="{{ $contactHref }}">{{ __('govuk_alpha.trust_safety.contact_cta_button') }}</a>
                <a class="govuk-link" href="{{ $guidelinesHref }}">{{ __('govuk_alpha.trust_safety.community_guidelines_link') }}</a>
            </div>
        </div>
    </div>
@endsection
