{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.tenant_chooser.title') }}</h1>
            <p class="govuk-body-l">{{ __('govuk_alpha.tenant_chooser.description') }}</p>
        </div>
    </div>

    @if (empty($tenants))
        <h2 class="govuk-heading-m">{{ __('govuk_alpha.states.empty_title') }}</h2>
        <p class="govuk-body">{{ __('govuk_alpha.tenant_chooser.empty') }}</p>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($tenants as $community)
                <article class="nexus-alpha-card">
                    <h2 class="govuk-heading-m govuk-!-margin-bottom-2">
                        <a class="govuk-link" href="{{ route('govuk-alpha.home', ['tenantSlug' => $community['slug']]) }}">{{ $community['name'] }}</a>
                    </h2>
                    @if (!empty($community['tagline']))
                        <p class="govuk-body">{{ $community['tagline'] }}</p>
                    @endif
                    <p class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha.tenant_chooser.community_slug', ['slug' => $community['slug']]) }}</p>
                </article>
            @endforeach
        </div>
    @endif
@endsection
