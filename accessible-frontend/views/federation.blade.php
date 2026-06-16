{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $dateFmt = fn ($v): ?string => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('F Y') : null;
        $stats = $stats ?? [];
        $tenantEnabled = (bool) ($stats['tenant_federation_enabled'] ?? false);
        $optedIn = (bool) ($stats['federation_optin'] ?? false);
        $statusBanners = [
            'opted-in' => ['success', __('govuk_alpha.federation.optin.success')],
            'opted-out' => ['success', __('govuk_alpha.federation.optout.success')],
            'optin-failed' => ['error', __('govuk_alpha.federation.optin.failed')],
            'optout-failed' => ['error', __('govuk_alpha.federation.optout.failed')],
        ];
        $banner = $statusBanners[$status ?? ''] ?? null;
        $partnerHref = function (array $p) use ($tenantSlug): string {
            return route('govuk-alpha.federation.partners.show', ['tenantSlug' => $tenantSlug, 'id' => $p['id']]);
        };
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-xl">{{ __('govuk_alpha.federation.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.federation.title') }}</h1>
            <p class="govuk-body-l">{{ __('govuk_alpha.federation.description') }}</p>
        </div>
    </div>

    @if ($banner)
        @if ($banner[0] === 'error')
            <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                <div role="alert">
                    <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                    <div class="govuk-error-summary__body">
                        <ul class="govuk-list govuk-error-summary__list">
                            <li>{{ $banner[1] }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        @else
            <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-labelledby="fed-status-title">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="fed-status-title">{{ __('govuk_alpha.states.success_title') }}</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading">{{ $banner[1] }}</p>
                </div>
            </div>
        @endif
    @endif

    @if (!$tenantEnabled)
        <p class="govuk-inset-text">{{ __('govuk_alpha.federation.hub.disabled_notice') }}</p>
    @elseif (!$optedIn)
        <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="fed-optin-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="fed-optin-title">{{ __('govuk_alpha.federation.hub.optin_banner_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.federation.hub.optin_banner_body') }}</p>
                <div class="govuk-button-group">
                    <a class="govuk-button" data-module="govuk-button" href="{{ route('govuk-alpha.federation.opt-in', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.federation.hub.optin_banner_cta') }}</a>
                </div>
            </div>
        </div>
    @endif

    {{-- Network stats --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha.federation.hub.stats_heading') }}</h2>
    <dl class="govuk-summary-list">
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.federation.hub.stat_partnerships') }}</dt>
            <dd class="govuk-summary-list__value">{{ number_format((int) ($stats['partnerships_count'] ?? 0)) }}</dd>
        </div>
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.federation.hub.stat_messages') }}</dt>
            <dd class="govuk-summary-list__value">{{ number_format((int) ($stats['messages_count'] ?? 0)) }}</dd>
        </div>
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.federation.hub.stat_transactions') }}</dt>
            <dd class="govuk-summary-list__value">{{ number_format((int) ($stats['transactions_count'] ?? 0)) }}</dd>
        </div>
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.federation.hub.optin_status_label') }}</dt>
            <dd class="govuk-summary-list__value">
                @if ($optedIn)
                    <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.federation.hub.optin_on') }}</strong>
                @else
                    <strong class="govuk-tag govuk-tag--grey">{{ __('govuk_alpha.federation.hub.optin_off') }}</strong>
                @endif
            </dd>
        </div>
    </dl>

    {{-- Partner preview --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha.federation.hub.partners_heading') }}</h2>
    @if (empty($partners))
        <p class="govuk-inset-text">{{ __('govuk_alpha.federation.hub.partners_empty') }}</p>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($partners as $p)
                @php
                    $pName = trim((string) ($p['name'] ?? '')) ?: __('govuk_alpha.federation.title');
                    $since = $dateFmt($p['partnership_since'] ?? null);
                    $loc = trim((string) ($p['location'] ?? ''));
                    $levelSlug = trim((string) ($p['level_name'] ?? ''));
                    $levelName = $levelSlug !== '' ? __('govuk_alpha.federation.levels.' . $levelSlug) : '';
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1"><a class="govuk-link" href="{{ $partnerHref($p) }}">{{ $pName }}</a></h3>
                        @if ($levelName !== '')<strong class="govuk-tag govuk-tag--purple">{{ $levelName }}</strong>@endif
                    </div>
                    @if (trim((string) ($p['tagline'] ?? '')) !== '')
                        <p class="govuk-body govuk-!-margin-bottom-2">{{ \Illuminate\Support\Str::limit($p['tagline'], 200) }}</p>
                    @endif
                    <dl class="nexus-alpha-inline-list">
                        @if ($loc !== '')
                            <div><dt>{{ __('govuk_alpha.federation.location_label') }}</dt><dd>{{ $loc }}</dd></div>
                        @endif
                        <div><dt>{{ __('govuk_alpha.federation.members_label') }}</dt><dd>{{ number_format((int) ($p['member_count'] ?? 0)) }}</dd></div>
                        <div><dt>{{ __('govuk_alpha.federation.listings_label') }}</dt><dd>{{ number_format((int) ($p['listing_count'] ?? 0)) }}</dd></div>
                        @if ($since !== null)
                            <div><dt>{{ __('govuk_alpha.federation.since_label') }}</dt><dd>{{ $since }}</dd></div>
                        @endif
                    </dl>
                </article>
            @endforeach
        </div>
    @endif

    {{-- Quick links into the rest of the federation surface --}}
    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <h2 class="govuk-heading-l">{{ __('govuk_alpha.federation.hub.quick_links_heading') }}</h2>
            <ul class="govuk-list">
                <li><a class="govuk-link" href="{{ route('govuk-alpha.federation.members.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.federation.hub.quick_link_members') }}</a></li>
                <li><a class="govuk-link" href="{{ route('govuk-alpha.federation.connections.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.fed2.connections.title') }}</a></li>
                <li><a class="govuk-link" href="{{ route('govuk-alpha.federation.messages.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.fed2.messages.title') }}</a></li>
                <li><a class="govuk-link" href="{{ route('govuk-alpha.federation.listings.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.federation.hub.quick_link_listings') }}</a></li>
                <li><a class="govuk-link" href="{{ route('govuk-alpha.federation.events.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.federation.hub.quick_link_events') }}</a></li>
                <li><a class="govuk-link" href="{{ route('govuk-alpha.federation.groups.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.polish_federation.groups_title') }}</a></li>
                <li><a class="govuk-link" href="{{ route('govuk-alpha.federation.settings', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.federation.hub.quick_link_settings') }}</a></li>
            </ul>
        </div>
    </div>
@endsection
