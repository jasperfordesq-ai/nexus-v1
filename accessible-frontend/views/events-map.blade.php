{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">{{ __('govuk_alpha_events.common.back_to_event') }}</a>

    <span class="govuk-caption-l">{{ $event['title'] ?? __('govuk_alpha_events.map.caption') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_events.map.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_events.map.intro') }}</p>

    @if ($hasCoordinates)
        @php
            // OpenStreetMap embed + links — no API key, no JavaScript required.
            // The export iframe degrades gracefully (it is a plain <iframe>).
            $latStr = rtrim(rtrim(number_format($lat, 6, '.', ''), '0'), '.');
            $lngStr = rtrim(rtrim(number_format($lng, 6, '.', ''), '0'), '.');
            $delta = 0.01;
            $bbox = ($lng - $delta) . '%2C' . ($lat - $delta) . '%2C' . ($lng + $delta) . '%2C' . ($lat + $delta);
            $embedUrl = 'https://www.openstreetmap.org/export/embed.html?bbox=' . $bbox . '&layer=mapnik&marker=' . $latStr . '%2C' . $lngStr;
            $viewUrl = 'https://www.openstreetmap.org/?mlat=' . $latStr . '&mlon=' . $lngStr . '#map=15/' . $latStr . '/' . $lngStr;
            $directionsUrl = 'https://www.openstreetmap.org/directions?to=' . $latStr . '%2C' . $lngStr;
        @endphp

        @if ($location)
            <dl class="govuk-summary-list govuk-!-margin-bottom-6">
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_events.map.address_label') }}</dt>
                    <dd class="govuk-summary-list__value">{{ $location }}</dd>
                </div>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_events.map.coordinates_label') }}</dt>
                    <dd class="govuk-summary-list__value">{{ $latStr }}, {{ $lngStr }}</dd>
                </div>
            </dl>
        @endif

        <figure class="nexus-alpha-detail-hero govuk-!-margin-bottom-4">
            <iframe
                title="{{ __('govuk_alpha_events.map.static_map_alt', ['title' => $event['title'] ?? '']) }}"
                src="{{ $embedUrl }}"
                width="100%"
                height="360"
                style="border:1px solid #b1b4b6;max-width:100%;"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"></iframe>
        </figure>

        <div class="govuk-button-group">
            <a class="govuk-link" href="{{ $viewUrl }}" rel="noopener noreferrer" target="_blank">{{ __('govuk_alpha_events.map.view_on_map_link') }}<span class="govuk-visually-hidden"> {{ __('govuk_alpha.opens_new_tab') }}</span></a>
            <a class="govuk-link" href="{{ $directionsUrl }}" rel="noopener noreferrer" target="_blank">{{ __('govuk_alpha_events.map.directions_link') }}<span class="govuk-visually-hidden"> {{ __('govuk_alpha.opens_new_tab') }}</span></a>
        </div>
    @else
        <div class="govuk-inset-text">
            <h2 class="govuk-heading-m">{{ __('govuk_alpha_events.map.no_location_heading') }}</h2>
            @if ($isOnline)
                <p class="govuk-body">{{ __('govuk_alpha_events.map.no_location_online') }}</p>
            @else
                <p class="govuk-body">{{ __('govuk_alpha_events.map.no_location_missing') }}</p>
                @if ($location)
                    <p class="govuk-body">{{ __('govuk_alpha_events.map.no_location_address') }}</p>
                    <dl class="govuk-summary-list">
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_events.map.address_label') }}</dt>
                            <dd class="govuk-summary-list__value">{{ $location }}</dd>
                        </div>
                    </dl>
                @endif
            @endif
        </div>
    @endif
@endsection
