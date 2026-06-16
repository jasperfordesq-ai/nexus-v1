{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $allowed = (bool) ($allowed ?? false);
        $members = $members ?? [];
        $query = trim((string) ($query ?? ''));
        $skills = trim((string) ($skills ?? ''));
        $partnerId = (int) ($partnerId ?? 0);
        $serviceReach = (string) ($serviceReach ?? '');
        $partnerOptions = $partnerOptions ?? [];
        $total = $total ?? null;
        $viewerCanMessage = (bool) ($viewerCanMessage ?? false);
        $loadError = (bool) ($loadError ?? false);
        $nextCursor = $nextCursor ?? null;

        $reachLabels = [
            'local_only' => __('govuk_alpha.federation.settings.reach_local_only'),
            'remote_ok' => __('govuk_alpha.federation.settings.reach_remote_ok'),
            'travel_ok' => __('govuk_alpha.federation.settings.reach_travel_ok'),
        ];

        $hasFilters = ($query !== '') || ($skills !== '') || ($partnerId > 0) || ($serviceReach !== '');

        $indexHref = route('govuk-alpha.federation.members.index', ['tenantSlug' => $tenantSlug]);

        $memberHref = function (array $m) use ($tenantSlug): string {
            // tenant_id is REQUIRED so the profile scopes to the owning community.
            return route('govuk-alpha.federation.members.show', [
                'tenantSlug' => $tenantSlug,
                'id' => $m['id'] ?? 0,
                'tenant_id' => $m['tenant_id'] ?? 0,
            ]);
        };

        $moreHref = function () use ($tenantSlug, $query, $skills, $partnerId, $serviceReach, $nextCursor): string {
            $params = ['tenantSlug' => $tenantSlug, 'cursor' => $nextCursor];
            if ($query !== '') { $params['q'] = $query; }
            if ($skills !== '') { $params['skills'] = $skills; }
            if ($partnerId > 0) { $params['partner_id'] = $partnerId; }
            if ($serviceReach !== '') { $params['service_reach'] = $serviceReach; }
            return route('govuk-alpha.federation.members.index', $params);
        };
    @endphp

    <a href="{{ route('govuk-alpha.federation.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.federation.members_browse.back') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha.federation.members_browse.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.federation.members_browse.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.federation.members_browse.description') }}</p>

    @include('accessible-frontend::partials.federation-nav')

    @if (!$allowed)
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.federation.members_browse.not_available') }}</p></div>
    @else
        <form method="get" action="{{ $indexHref }}" class="govuk-!-margin-bottom-6">
            <fieldset class="govuk-fieldset">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">{{ __('govuk_alpha.federation.members_browse.filters_legend') }}</legend>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="q">{{ __('govuk_alpha.federation.members_browse.search_label') }}</label>
                    <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha.federation.members_browse.search_hint') }}</div>
                    <input class="govuk-input govuk-!-width-two-thirds" id="q" name="q" type="search" value="{{ $query }}" aria-describedby="q-hint">
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="partner_id">{{ __('govuk_alpha.federation.members_browse.community_filter_label') }}</label>
                    <select class="govuk-select" id="partner_id" name="partner_id">
                        <option value="" @selected($partnerId === 0)>{{ __('govuk_alpha.federation.members_browse.all_communities') }}</option>
                        @foreach ($partnerOptions as $partner)
                            <option value="{{ $partner['id'] }}" @selected($partnerId === (int) ($partner['id'] ?? 0))>{{ $partner['name'] ?? '' }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="skills">{{ __('govuk_alpha.federation.members_browse.skills_filter_label') }}</label>
                    <div id="skills-hint" class="govuk-hint">{{ __('govuk_alpha.federation.members_browse.skills_filter_hint') }}</div>
                    <input class="govuk-input govuk-!-width-two-thirds" id="skills" name="skills" type="text" value="{{ $skills }}" aria-describedby="skills-hint">
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="service_reach">{{ __('govuk_alpha.federation.members_browse.service_reach_label') }}</label>
                    <select class="govuk-select" id="service_reach" name="service_reach">
                        <option value="" @selected($serviceReach === '')>{{ __('govuk_alpha.federation.members_browse.reach_all') }}</option>
                        <option value="local_only" @selected($serviceReach === 'local_only')>{{ __('govuk_alpha.federation.settings.reach_local_only') }}</option>
                        <option value="remote_ok" @selected($serviceReach === 'remote_ok')>{{ __('govuk_alpha.federation.settings.reach_remote_ok') }}</option>
                        <option value="travel_ok" @selected($serviceReach === 'travel_ok')>{{ __('govuk_alpha.federation.settings.reach_travel_ok') }}</option>
                    </select>
                </div>

                <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.federation.members_browse.apply_filters') }}</button>
            </fieldset>
        </form>

        @if ($loadError)
            <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                <div role="alert">
                    <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.federation.members_browse.unable_to_load') }}</h2>
                    <div class="govuk-error-summary__body">
                        <p class="govuk-body">{{ __('govuk_alpha.federation.members_browse.load_error') }}</p>
                        <ul class="govuk-list govuk-error-summary__list">
                            <li><a href="{{ $indexHref }}">{{ __('govuk_alpha.federation.members_browse.try_again') }}</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        @else
            @if ($total !== null)
                <p class="govuk-body-s">{{ __('govuk_alpha.federation.members_browse.showing_count', ['shown' => count($members), 'total' => $total]) }}</p>
            @endif

            @if (empty($members))
                @if (empty($partnerOptions))
                    <div class="govuk-inset-text">
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha.federation.members_browse.no_partner_communities') }}</h2>
                        <p class="govuk-body">{{ __('govuk_alpha.federation.members_browse.no_partner_communities_description') }}</p>
                    </div>
                @elseif ($hasFilters)
                    <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.federation.members_browse.no_members_search') }}</p></div>
                @else
                    <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.federation.members_browse.no_members_available') }}</p></div>
                @endif
            @else
                <div class="nexus-alpha-card-list">
                    @foreach ($members as $m)
                        @php
                            $mName = trim((string) ($m['name'] ?? '')) ?: __('govuk_alpha.members.unknown_member');
                            $mBio = trim((string) ($m['bio'] ?? ''));
                            $mLoc = trim((string) ($m['location'] ?? ''));
                            $mReach = (string) ($m['service_reach'] ?? '');
                            $mReachLabel = $reachLabels[$mReach] ?? '';
                            $mSkills = array_values(array_filter((array) ($m['skills'] ?? []), fn ($s) => trim((string) $s) !== ''));
                            $mSkillCount = count($mSkills);
                        @endphp
                        <article class="nexus-alpha-card">
                            <h2 class="govuk-heading-s govuk-!-margin-bottom-1"><a class="govuk-link" href="{{ $memberHref($m) }}">{{ $mName }}</a></h2>

                            <p class="govuk-body-s govuk-!-margin-bottom-1">
                                <strong class="govuk-tag govuk-tag--grey">{{ __('govuk_alpha.federation.members_browse.community_label') }}: {{ $m['tenant_name'] ?? '' }}</strong>
                            </p>

                            @if ($mBio !== '')
                                <p class="govuk-body govuk-!-margin-bottom-1">{{ \Illuminate\Support\Str::limit($mBio, 160) }}</p>
                            @endif

                            @if ($mLoc !== '')
                                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.federation.location_label') }}: {{ $mLoc }}</p>
                            @endif

                            @if ($mReachLabel !== '')
                                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.federation.members_browse.reach_label') }}: {{ $mReachLabel }}</p>
                            @endif

                            @if ($mSkillCount > 0)
                                <p class="govuk-body-s govuk-!-margin-bottom-2 nexus-alpha-inline-list">
                                    @foreach (array_slice($mSkills, 0, 5) as $skill)
                                        <strong class="govuk-tag govuk-tag--grey govuk-!-margin-right-1">{{ $skill }}</strong>
                                    @endforeach
                                    @if ($mSkillCount > 5)
                                        <strong class="govuk-tag govuk-tag--grey">{{ __('govuk_alpha.federation.members_browse.more_skills', ['count' => $mSkillCount - 5]) }}</strong>
                                    @endif
                                </p>
                            @endif

                            <p class="govuk-body-s govuk-!-margin-bottom-0">
                                <a class="govuk-link" href="{{ $memberHref($m) }}">{{ __('govuk_alpha.federation.members_browse.view_profile') }}</a>
                                @if ($viewerCanMessage)
                                    <span aria-hidden="true"> &middot; </span>
                                    <a class="govuk-link" href="{{ $memberHref($m) }}">{{ __('govuk_alpha.federation.members_browse.send_message') }}</a>
                                @endif
                            </p>
                        </article>
                    @endforeach
                </div>

                @if (!empty($nextCursor))
                    <p class="govuk-body govuk-!-margin-top-4">
                        <a class="govuk-link" href="{{ $moreHref() }}">{{ __('govuk_alpha.federation.members_browse.load_more') }}</a>
                    </p>
                @endif
            @endif
        @endif
    @endif
@endsection
