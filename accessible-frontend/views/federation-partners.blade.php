{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $partners = $partners ?? [];
        $dateFmt = fn ($v): ?string => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('F Y') : null;
        $partnerHref = function (array $p) use ($tenantSlug): string {
            return route('govuk-alpha.federation.partners.show', ['tenantSlug' => $tenantSlug, 'id' => $p['id']]);
        };
    @endphp

    <a href="{{ route('govuk-alpha.federation.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.federation.partners_list.back') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha.federation.partners_list.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.federation.partners_list.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.federation.partners_list.description') }}</p>

    @include('accessible-frontend::partials.federation-nav')

    @if (empty($partners))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.federation.partners_list.empty') }}</p></div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($partners as $p)
                @php
                    $pName = trim((string) ($p['name'] ?? '')) ?: __('govuk_alpha.federation.title');
                    $isExternal = (bool) ($p['is_external'] ?? false);
                    $since = $dateFmt($p['partnership_since'] ?? null);
                    $loc = trim((string) ($p['location'] ?? ''));
                    $levelSlug = trim((string) ($p['level_name'] ?? ''));
                    $levelName = $levelSlug !== '' ? __('govuk_alpha.federation.levels.' . $levelSlug) : '';
                    $perms = (array) ($p['permissions'] ?? []);
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1"><a class="govuk-link" href="{{ $partnerHref($p) }}">{{ $pName }}</a></h2>
                        @if ($isExternal)
                            <strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha.federation.external_tag') }}</strong>
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

                    @if (!empty($perms))
                        <p class="govuk-body-s govuk-!-margin-bottom-2">
                            <span class="nexus-alpha-meta">{{ __('govuk_alpha.federation.permissions_label') }}:</span>
                            @foreach ($perms as $perm)
                                <strong class="govuk-tag govuk-tag--grey govuk-!-margin-right-1">{{ __('govuk_alpha.federation.permissions.' . $perm) }}</strong>
                            @endforeach
                        </p>
                    @endif

                    <p class="govuk-body govuk-!-margin-bottom-0">
                        <a class="govuk-link" href="{{ $partnerHref($p) }}">{{ __('govuk_alpha.federation.partners_list.view_community') }}</a>
                    </p>
                </article>
            @endforeach
        </div>
    @endif
@endsection
