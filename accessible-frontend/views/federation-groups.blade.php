{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $allowed = (bool) ($allowed ?? false);
        $groups = $groups ?? [];
        $query = (string) ($query ?? '');
        $moreHref = function () use ($tenantSlug, $query, $nextCursor): string {
            $params = ['tenantSlug' => $tenantSlug, 'cursor' => $nextCursor];
            if ($query !== '') { $params['q'] = $query; }
            return route('govuk-alpha.federation.groups.index', $params);
        };
    @endphp

    <a href="{{ route('govuk-alpha.federation.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.polish_federation.groups_back') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha.polish_federation.groups_caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.polish_federation.groups_title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.polish_federation.groups_description') }}</p>

    @if (!$allowed)
        <div class="govuk-inset-text">{{ __('govuk_alpha.polish_federation.groups_not_available') }}</div>
    @else
        <form method="get" action="{{ route('govuk-alpha.federation.groups.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
            <div class="govuk-form-group">
                <label class="govuk-label" for="q">{{ __('govuk_alpha.polish_federation.groups_search_label') }}</label>
                <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha.polish_federation.groups_search_hint') }}</div>
                <input class="govuk-input govuk-!-width-two-thirds" id="q" name="q" type="search" value="{{ $query }}" aria-describedby="q-hint">
            </div>
            <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.actions.search') }}</button>
        </form>

        @if (empty($groups))
            <div class="govuk-inset-text">{{ __('govuk_alpha.polish_federation.groups_empty') }}</div>
        @else
            <div class="nexus-alpha-card-list">
                @foreach ($groups as $g)
                    @php
                        $gName = trim((string) ($g['name'] ?? '')) ?: __('govuk_alpha.polish_federation.groups_title');
                    @endphp
                    <article class="nexus-alpha-card">
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $gName }}</h2>
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.polish_federation.groups_community_label') }}: {{ $g['tenant_name'] ?? '' }}</p>
                        @if (trim((string) ($g['description'] ?? '')) !== '')
                            <p class="govuk-body govuk-!-margin-bottom-1">{{ \Illuminate\Support\Str::limit($g['description'], 160) }}</p>
                        @endif
                        <dl class="nexus-alpha-inline-list">
                            <div>
                                <dt>{{ __('govuk_alpha.polish_federation.groups_members_label') }}</dt>
                                <dd>{{ number_format((int) ($g['member_count'] ?? 0)) }}</dd>
                            </div>
                        </dl>
                    </article>
                @endforeach
            </div>

            @if (!empty($nextCursor))
                <p class="govuk-body govuk-!-margin-top-4">
                    <a class="govuk-link" href="{{ $moreHref() }}">{{ __('govuk_alpha.polish_federation.groups_load_more') }}</a>
                </p>
            @endif
        @endif
    @endif
@endsection
