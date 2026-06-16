{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $partner = $partner ?? [];
        $dateFmt = fn ($v): ?string => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('j F Y') : null;
        $pName = trim((string) ($partner['name'] ?? '')) ?: __('govuk_alpha.federation.partner.caption');
        $levelSlug = trim((string) ($partner['level_name'] ?? ''));
        $levelName = $levelSlug !== '' ? __('govuk_alpha.federation.levels.' . $levelSlug) : '';
        $since = $dateFmt($partner['partnership_since'] ?? null);
        $loc = trim((string) ($partner['location'] ?? ''));
        $country = trim((string) ($partner['country'] ?? ''));
        $isExternal = (bool) ($partner['is_external'] ?? false);
        $partnerId = (int) ($partner['id'] ?? 0);
        $permissions = (array) ($partner['permissions'] ?? []);
        $permissionLabels = [
            'profiles' => __('govuk_alpha.federation.permissions.profiles'),
            'listings' => __('govuk_alpha.federation.permissions.listings'),
            'events' => __('govuk_alpha.federation.permissions.events'),
            'messaging' => __('govuk_alpha.federation.permissions.messaging'),
            'transactions' => __('govuk_alpha.federation.permissions.transactions'),
            'groups' => __('govuk_alpha.federation.permissions.groups'),
        ];
    @endphp

    <a href="{{ route('govuk-alpha.federation.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.federation.partner.back') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha.federation.partner.caption') }}</span>
    <div class="nexus-alpha-module-row">
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">{{ $pName }}</h1>
        @if ($isExternal)
            <strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha.federation.external_tag') }}</strong>
        @elseif ($levelName !== '')
            <strong class="govuk-tag govuk-tag--purple">{{ $levelName }}</strong>
        @endif
    </div>

    @include('accessible-frontend::partials.federation-nav')

    @if (trim((string) ($partner['tagline'] ?? '')) !== '')
        <h2 class="govuk-heading-l">{{ __('govuk_alpha.federation.partner.about_label') }}</h2>
        <div class="govuk-body">{!! nl2br(e($partner['tagline'])) !!}</div>
    @endif

    <dl class="govuk-summary-list">
        @if ($loc !== '')
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.federation.location_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $loc }}</dd>
            </div>
        @endif
        @if ($country !== '')
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.federation.country_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $country }}</dd>
            </div>
        @endif
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.federation.members_label') }}</dt>
            <dd class="govuk-summary-list__value">{{ number_format((int) ($partner['member_count'] ?? 0)) }}</dd>
        </div>
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.federation.listings_label') }}</dt>
            <dd class="govuk-summary-list__value">{{ number_format((int) ($partner['listing_count'] ?? 0)) }}</dd>
        </div>
        @if (!$isExternal && $levelName !== '')
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.federation.level_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $levelName }}</dd>
            </div>
        @endif
        @if ($since !== null)
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.federation.since_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $since }}</dd>
            </div>
        @endif
        @if (!empty($permissions))
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.federation.permissions_label') }}</dt>
                <dd class="govuk-summary-list__value">
                    @foreach ($permissions as $perm)
                        <strong class="govuk-tag govuk-tag--grey govuk-!-margin-right-1">{{ $permissionLabels[$perm] ?? $perm }}</strong>
                    @endforeach
                </dd>
            </div>
        @endif
    </dl>

    <h2 class="govuk-heading-m">{{ __('govuk_alpha.federation.partner.browse_heading') }}</h2>
    <ul class="govuk-list">
        @if (in_array('profiles', $permissions, true))
            <li>
                @if (!$isExternal && $partnerId > 0)
                    <a class="govuk-link" href="{{ route('govuk-alpha.federation.members.index', ['tenantSlug' => $tenantSlug, 'partner_id' => $partnerId]) }}">{{ __('govuk_alpha.federation.partner.browse_members') }}</a>
                @else
                    <a class="govuk-link" href="{{ route('govuk-alpha.federation.members.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.federation.partner.browse_members') }}</a>
                @endif
            </li>
        @endif
        @if (in_array('listings', $permissions, true))
            <li>
                @if (!$isExternal && $partnerId > 0)
                    <a class="govuk-link" href="{{ route('govuk-alpha.federation.listings.index', ['tenantSlug' => $tenantSlug, 'partner_id' => $partnerId]) }}">{{ __('govuk_alpha.federation.partner.browse_listings') }}</a>
                @else
                    <a class="govuk-link" href="{{ route('govuk-alpha.federation.listings.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.federation.partner.browse_listings') }}</a>
                @endif
            </li>
        @endif
        @if (in_array('events', $permissions, true))
            <li><a class="govuk-link" href="{{ route('govuk-alpha.federation.events.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.federation.partner.browse_events') }}</a></li>
        @endif
    </ul>
@endsection
