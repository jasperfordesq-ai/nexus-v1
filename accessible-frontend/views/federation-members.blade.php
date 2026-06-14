{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $allowed = (bool) ($allowed ?? false);
        $members = $members ?? [];
        $query = (string) ($query ?? '');
        $memberHref = function (array $m) use ($tenantSlug): string {
            // tenant_id is REQUIRED so the profile scopes to the owning community.
            return route('govuk-alpha.federation.members.show', [
                'tenantSlug' => $tenantSlug,
                'id' => $m['id'],
                'tenant_id' => $m['tenant_id'],
            ]);
        };
        $moreHref = function () use ($tenantSlug, $query, $nextCursor): string {
            $params = ['tenantSlug' => $tenantSlug, 'cursor' => $nextCursor];
            if ($query !== '') { $params['q'] = $query; }
            return route('govuk-alpha.federation.members.index', $params);
        };
    @endphp

    <a href="{{ route('govuk-alpha.federation.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.federation.members_browse.back') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha.federation.members_browse.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.federation.members_browse.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.federation.members_browse.description') }}</p>

    @if (!$allowed)
        <p class="govuk-inset-text">{{ __('govuk_alpha.federation.members_browse.not_available') }}</p>
    @else
        <form method="get" action="{{ route('govuk-alpha.federation.members.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
            <div class="govuk-form-group">
                <label class="govuk-label" for="q">{{ __('govuk_alpha.federation.members_browse.search_label') }}</label>
                <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha.federation.members_browse.search_hint') }}</div>
                <input class="govuk-input govuk-!-width-two-thirds" id="q" name="q" type="search" value="{{ $query }}" aria-describedby="q-hint">
            </div>
            <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.actions.search') }}</button>
        </form>

        @if (empty($members))
            <p class="govuk-inset-text">{{ __('govuk_alpha.federation.members_browse.empty') }}</p>
        @else
            <div class="nexus-alpha-card-list">
                @foreach ($members as $m)
                    @php
                        $mName = trim((string) ($m['name'] ?? '')) ?: __('govuk_alpha.members.unknown_member');
                        $loc = trim((string) ($m['location'] ?? ''));
                        $skills = (array) ($m['skills'] ?? []);
                    @endphp
                    <article class="nexus-alpha-card">
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1"><a class="govuk-link" href="{{ $memberHref($m) }}">{{ $mName }}</a></h2>
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.federation.members_browse.community_label') }}: {{ $m['tenant_name'] ?? '' }}</p>
                        @if (trim((string) ($m['bio'] ?? '')) !== '')
                            <p class="govuk-body govuk-!-margin-bottom-1">{{ \Illuminate\Support\Str::limit($m['bio'], 160) }}</p>
                        @endif
                        @if ($loc !== '')
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.federation.location_label') }}: {{ $loc }}</p>
                        @endif
                        @if (!empty($skills))
                            <p class="govuk-body-s govuk-!-margin-bottom-0">
                                @foreach (array_slice($skills, 0, 6) as $skill)
                                    <strong class="govuk-tag govuk-tag--grey govuk-!-margin-right-1">{{ $skill }}</strong>
                                @endforeach
                            </p>
                        @endif
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
@endsection
