{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $oName = trim((string) ($organisation['name'] ?? '')) ?: __('govuk_alpha.organisations.title');
        $oWebsite = trim((string) ($organisation['website'] ?? ''));
        $oEmail = trim((string) ($organisation['email'] ?? ($organisation['contact_email'] ?? '')));
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha.organisations.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ $oName }}</h1>

    @if (trim((string) ($organisation['description'] ?? '')) !== '')
        <p class="govuk-body-l">{{ $organisation['description'] }}</p>
    @endif

    @if ($oWebsite !== '' || $oEmail !== '')
        <dl class="govuk-summary-list">
            @if ($oEmail !== '')
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha.organisations.email_label') }}</dt>
                    <dd class="govuk-summary-list__value"><a class="govuk-link" href="mailto:{{ $oEmail }}">{{ $oEmail }}</a></dd>
                </div>
            @endif
            @if ($oWebsite !== '')
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha.organisations.website_label') }}</dt>
                    <dd class="govuk-summary-list__value"><a class="govuk-link" href="{{ \Illuminate\Support\Str::startsWith($oWebsite, ['http://', 'https://']) ? $oWebsite : 'https://' . $oWebsite }}" rel="nofollow noopener noreferrer" target="_blank">{{ $oWebsite }}</a></dd>
                </div>
            @endif
        </dl>
    @endif
@endsection
