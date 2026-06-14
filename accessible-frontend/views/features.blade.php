{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-xl">{{ __('govuk_alpha.features.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.features.title') }}</h1>
            <p class="govuk-body-l">{{ __('govuk_alpha.features.intro') }}</p>

            <ul class="govuk-list govuk-list--bullet govuk-list--spaced">
                @foreach (['find_help', 'wallet', 'events', 'volunteering', 'groups', 'recognition'] as $key)
                    <li>{{ __('govuk_alpha.features.items.' . $key) }}</li>
                @endforeach
            </ul>

            <a class="govuk-button govuk-!-margin-top-4" href="{{ route('govuk-alpha.guide', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.guide.title') }}</a>
        </div>
    </div>
@endsection
