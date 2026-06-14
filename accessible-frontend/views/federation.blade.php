{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $dateFmt = fn ($v): ?string => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('F Y') : null;
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha.federation.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.federation.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.federation.description') }}</p>

    @if (empty($partners))
        <p class="govuk-inset-text">{{ __('govuk_alpha.federation.empty') }}</p>
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
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $pName }}</h2>
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
@endsection
