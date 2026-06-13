{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $documents = [
            'terms' => route('govuk-alpha.legal.terms', ['tenantSlug' => $tenantSlug]),
            'privacy' => route('govuk-alpha.legal.privacy', ['tenantSlug' => $tenantSlug]),
            'cookies' => route('govuk-alpha.legal.cookies', ['tenantSlug' => $tenantSlug]),
            'community_guidelines' => route('govuk-alpha.legal.community-guidelines', ['tenantSlug' => $tenantSlug]),
            'acceptable_use' => route('govuk-alpha.legal.acceptable-use', ['tenantSlug' => $tenantSlug]),
            'accessibility' => route('govuk-alpha.accessibility', ['tenantSlug' => $tenantSlug]),
        ];
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-l">{{ __('govuk_alpha.legal.caption', ['community' => $communityName]) }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.legal.hub_title') }}</h1>
            <p class="govuk-body-l">{{ __('govuk_alpha.legal.hub_intro', ['name' => $communityName]) }}</p>

            <ul class="govuk-list">
                @foreach ($documents as $key => $href)
                    <li class="nexus-alpha-card">
                        <h2 class="govuk-heading-m govuk-!-margin-bottom-1">
                            <a class="govuk-link" href="{{ $href }}">{{ __('govuk_alpha.legal.documents.' . $key . '.title') }}</a>
                        </h2>
                        <p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha.legal.documents.' . $key . '.summary') }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endsection
