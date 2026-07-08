{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $stats = $stats ?? [];
        $partners = $partners ?? [];
        $partnerTotal = (int) ($partnerTotal ?? count($partners));
        $activity = $activity ?? [];
        $loadError = (bool) ($loadError ?? false);

        $tenantName = $tenant['name'] ?? $tenantSlug;
        $tenantEnabled = (bool) ($stats['tenant_federation_enabled'] ?? false);
        $optedIn = (bool) ($stats['federation_optin'] ?? false);

        $dateFmt = fn ($v): ?string => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('j F Y') : null;

        // Status banner map — mirrors the existing hub logic.
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

    <span class="govuk-caption-xl">{{ __('govuk_alpha.federation.caption', ['community' => $tenantName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.federation.title') }}</h1>
    <p class="govuk-body-l">{{ $optedIn ? __('govuk_alpha.federation.hub.subtitle_opted_in') : __('govuk_alpha.federation.hub.subtitle_opted_out') }}</p>

    @include('accessible-frontend::partials.federation-nav')

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
            <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="fed-status-title">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="fed-status-title">{{ __('govuk_alpha.states.success_title') }}</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading">{{ $banner[1] }}</p>
                </div>
            </div>
        @endif
    @endif

    @if ($loadError)
        <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="fed-load-error-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="fed-load-error-title">{{ __('govuk_alpha.states.error_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.federation.hub.load_error') }}</p>
            </div>
        </div>
    @endif

    @if (!$tenantEnabled)
        {{-- NOT-AVAILABLE: federation is off for this community. Self-contained block; render nothing else. --}}
        <div class="govuk-inset-text">
            <h2 class="govuk-heading-m">{{ __('govuk_alpha.federation.hub.not_available_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha.federation.hub.not_available_description', ['community' => $tenantName]) }}</p>
            <p class="govuk-body">{{ __('govuk_alpha.federation.hub.not_available_contact') }}</p>
        </div>
    @else
        @if (!$optedIn)
            {{-- OPTED-OUT: marketing / explainer for members who have not joined the network. --}}
            <div class="govuk-grid-row">
                <div class="govuk-grid-column-two-thirds">
                    <h2 class="govuk-heading-l">{{ __('govuk_alpha.federation.hub.hero_title') }}</h2>
                    <p class="govuk-body">{{ __('govuk_alpha.federation.hub.hero_description') }}</p>
                    <div class="govuk-button-group">
                        <a class="govuk-button" data-module="govuk-button" href="{{ route('govuk-alpha.federation.onboarding', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.federation.hub.optin_banner_cta') }}</a>
                    </div>
                </div>
            </div>

            <h2 class="govuk-heading-l">{{ __('govuk_alpha.federation.hub.how_it_works_heading') }}</h2>
            <div class="govuk-grid-row">
                @foreach ([0, 1, 2] as $step)
                    <div class="govuk-grid-column-one-third">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha.federation.hub.how_it_works_' . $step . '_title') }}</h3>
                        <p class="govuk-body">{{ __('govuk_alpha.federation.hub.how_it_works_' . $step . '_description') }}</p>
                    </div>
                @endforeach
            </div>

            <div class="govuk-grid-row govuk-!-margin-top-4">
                <div class="govuk-grid-column-one-third">
                    <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha.federation.hub.feature_privacy_title') }}</h3>
                    <p class="govuk-body">{{ __('govuk_alpha.federation.hub.feature_privacy_description') }}</p>
                </div>
                <div class="govuk-grid-column-one-third">
                    <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha.federation.hub.feature_instant_title') }}</h3>
                    <p class="govuk-body">{{ __('govuk_alpha.federation.hub.feature_instant_description') }}</p>
                </div>
                <div class="govuk-grid-column-one-third">
                    <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha.federation.hub.feature_network_title') }}</h3>
                    <p class="govuk-body">{{ __('govuk_alpha.federation.hub.feature_network_description') }}</p>
                </div>
            </div>
        @endif

        {{-- Network stats summary-list (shown for both opted-in and opted-out members). --}}
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

        @if ($optedIn)
            {{-- RECENT ACTIVITY — opted-in members only. --}}
            <h2 class="govuk-heading-l">{{ __('govuk_alpha.federation.hub.recent_activity_heading') }}</h2>
            @if (empty($activity))
                <div class="govuk-inset-text">
                    <p class="govuk-body govuk-!-margin-bottom-1"><strong>{{ __('govuk_alpha.federation.hub.activity_empty') }}</strong></p>
                    <p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha.federation.hub.activity_empty_description') }}</p>
                </div>
            @else
                <div class="nexus-alpha-card-list">
                    @foreach ($activity as $item)
                        @php
                            $aTitle = trim((string) ($item['title'] ?? ''));
                            $aDescription = trim((string) ($item['description'] ?? ''));
                            $aCommunity = trim((string) ($item['community'] ?? ''));
                            $aDate = $dateFmt($item['created_at'] ?? null);
                        @endphp
                        <article class="nexus-alpha-card">
                            @if ($aTitle !== '')
                                <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $aTitle }}</h3>
                            @endif
                            @if ($aDescription !== '')
                                <p class="govuk-body govuk-!-margin-bottom-1">{{ $aDescription }}</p>
                            @endif
                            @if ($aCommunity !== '')
                                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.federation.hub.activity_from_community', ['community' => $aCommunity]) }}</p>
                            @endif
                            @if ($aDate !== null)
                                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">{{ $aDate }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        @endif

        {{-- Partner preview. --}}
        <h2 class="govuk-heading-l">{{ __('govuk_alpha.federation.hub.partners_heading') }}</h2>
        @if (empty($partners))
            <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.federation.hub.partners_empty') }}</p></div>
        @else
            <div class="nexus-alpha-card-list">
                @foreach ($partners as $p)
                    @php
                        $pName = trim((string) ($p['name'] ?? '')) ?: __('govuk_alpha.federation.title');
                        $since = $dateFmt($p['partnership_since'] ?? null);
                        $loc = trim((string) ($p['location'] ?? ''));
                        $isExternal = (bool) ($p['is_external'] ?? false);
                        $levelSlug = trim((string) ($p['level_name'] ?? ''));
                        $levelName = $levelSlug !== '' ? __('govuk_alpha.federation.levels.' . $levelSlug) : '';
                    @endphp
                    <article class="nexus-alpha-card">
                        <div class="nexus-alpha-module-row">
                            <h3 class="govuk-heading-s govuk-!-margin-bottom-1"><a class="govuk-link" href="{{ $partnerHref($p) }}">{{ $pName }}</a></h3>
                            @if ($isExternal)
                                <strong class="govuk-tag govuk-tag--grey">{{ __('govuk_alpha.federation.external_tag') }}</strong>
                            @elseif ($levelName !== '')
                                <strong class="govuk-tag govuk-tag--purple">{{ $levelName }}</strong>
                            @endif
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
                        <p class="govuk-body-s govuk-!-margin-bottom-0">
                            <a class="govuk-link" href="{{ $partnerHref($p) }}">{{ __('govuk_alpha.federation.hub.view_partner') }}</a>
                        </p>
                    </article>
                @endforeach
            </div>

            @if ($partnerTotal > count($partners))
                <p class="govuk-body govuk-!-margin-top-4">
                    <a class="govuk-link" href="{{ route('govuk-alpha.federation.partners.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.federation.hub.view_all_partners', ['count' => $partnerTotal]) }}</a>
                </p>
            @endif
        @endif

        @if ($optedIn)
            {{-- Quick links into the rest of the federation surface — opted-in members only. --}}
            <div class="govuk-grid-row">
                <div class="govuk-grid-column-two-thirds">
                    <h2 class="govuk-heading-l">{{ __('govuk_alpha.federation.hub.quick_links_heading') }}</h2>
                    <ul class="govuk-list">
                        <li><a class="govuk-link" href="{{ route('govuk-alpha.federation.partners.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.federation.hub.quick_link_partners') }}</a></li>
                        <li><a class="govuk-link" href="{{ route('govuk-alpha.federation.members.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.federation.hub.quick_link_members') }}</a></li>
                        <li><a class="govuk-link" href="{{ route('govuk-alpha.federation.connections.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.federation.hub.quick_link_connections') }}</a></li>
                        <li><a class="govuk-link" href="{{ route('govuk-alpha.federation.messages.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.federation.hub.quick_link_messages') }}</a></li>
                        <li><a class="govuk-link" href="{{ route('govuk-alpha.federation.listings.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.federation.hub.quick_link_listings') }}</a></li>
                        <li><a class="govuk-link" href="{{ route('govuk-alpha.federation.events.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.federation.hub.quick_link_events') }}</a></li>
                        <li><a class="govuk-link" href="{{ route('govuk-alpha.federation.groups.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.federation.hub.quick_link_groups') }}</a></li>
                        <li><a class="govuk-link" href="{{ route('govuk-alpha.federation.settings', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.federation.hub.quick_link_settings') }}</a></li>
                    </ul>

                    <p class="govuk-body govuk-!-margin-top-4">
                        <a class="govuk-link" href="{{ route('govuk-alpha.federation.opt-out', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.federation.hub.disable_federation') }}</a>
                    </p>
                </div>
            </div>
        @endif
    @endif
@endsection
