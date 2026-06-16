{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $allowed = (bool) ($allowed ?? false);
        $groups = $groups ?? [];
        $query = trim((string) ($query ?? ''));
        $partnerId = (int) ($partnerId ?? 0);
        $partnerOptions = $partnerOptions ?? [];
        $loadError = (bool) ($loadError ?? false);
        $nextCursor = $nextCursor ?? null;

        $indexHref = route('govuk-alpha.federation.groups.index', ['tenantSlug' => $tenantSlug]);

        $moreHref = function () use ($tenantSlug, $query, $partnerId, $nextCursor): string {
            $params = ['tenantSlug' => $tenantSlug, 'cursor' => $nextCursor];
            if ($query !== '') { $params['q'] = $query; }
            if ($partnerId > 0) { $params['partner_id'] = $partnerId; }
            return route('govuk-alpha.federation.groups.index', $params);
        };
    @endphp

    <a href="{{ route('govuk-alpha.federation.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.polish_federation.groups_back') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha.polish_federation.groups_caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.polish_federation.groups_title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.polish_federation.groups_description') }}</p>

    @include('accessible-frontend::partials.federation-nav')

    @if (!$allowed)
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.polish_federation.groups_not_available') }}</p></div>
    @else
        <form method="get" action="{{ $indexHref }}" class="govuk-!-margin-bottom-6">
            <div class="govuk-form-group">
                <label class="govuk-label" for="q">{{ __('govuk_alpha.polish_federation.groups_search_label') }}</label>
                <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha.polish_federation.groups_search_hint') }}</div>
                <input class="govuk-input govuk-!-width-two-thirds" id="q" name="q" type="search" value="{{ $query }}" aria-describedby="q-hint">
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label" for="partner_id">{{ __('govuk_alpha.polish_federation.groups_filter_label') }}</label>
                <select class="govuk-select" id="partner_id" name="partner_id">
                    <option value="" @selected($partnerId === 0)>{{ __('govuk_alpha.polish_federation.groups_filter_all') }}</option>
                    @foreach ($partnerOptions as $partner)
                        <option value="{{ $partner['id'] ?? '' }}" @selected($partnerId === (int) ($partner['id'] ?? 0))>{{ $partner['name'] ?? '' }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.polish_federation.groups_apply_filters') }}</button>
        </form>

        @if ($loadError)
            <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                <div role="alert">
                    <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                    <div class="govuk-error-summary__body">
                        <p class="govuk-body">{{ __('govuk_alpha.polish_federation.groups_load_error') }}</p>
                        <ul class="govuk-list govuk-error-summary__list">
                            <li><a href="{{ $indexHref }}">{{ __('govuk_alpha.polish_federation.groups_try_again') }}</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        @elseif (empty($groups))
            <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.polish_federation.groups_empty') }}</p></div>
        @else
            <div class="nexus-alpha-card-list">
                @foreach ($groups as $g)
                    @php
                        $gName = trim((string) ($g['name'] ?? '')) ?: __('govuk_alpha.polish_federation.groups_title');
                        $gDescription = trim((string) ($g['description'] ?? ''));
                        $gIsPrivate = ((string) ($g['privacy'] ?? '')) === 'private';
                    @endphp
                    <article class="nexus-alpha-card">
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1">
                            {{ $gName }}
                            @if ($gIsPrivate)
                                <strong class="govuk-tag govuk-tag--yellow">{{ __('govuk_alpha.polish_federation.groups_private') }}</strong>
                            @endif
                        </h2>

                        @if ($gDescription !== '')
                            <p class="govuk-body govuk-!-margin-bottom-1">{{ \Illuminate\Support\Str::limit($gDescription, 200) }}</p>
                        @endif

                        <dl class="nexus-alpha-inline-list">
                            <div>
                                <dt>{{ __('govuk_alpha.polish_federation.groups_community_label') }}</dt>
                                <dd>{{ $g['tenant_name'] ?? '' }}</dd>
                            </div>
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
