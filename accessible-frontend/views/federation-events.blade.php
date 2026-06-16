{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $allowed = (bool) ($allowed ?? false);
        $events = $events ?? [];
        $query = trim((string) ($query ?? ''));
        $partnerId = (int) ($partnerId ?? 0);
        $upcoming = (bool) ($upcoming ?? true);
        $partnerOptions = $partnerOptions ?? [];
        $loadError = (bool) ($loadError ?? false);
        $nextCursor = $nextCursor ?? null;

        $indexHref = route('govuk-alpha.federation.events.index', ['tenantSlug' => $tenantSlug]);

        // Date-time formatter (guard null first).
        $dateTimeFmt = fn ($v): ?string => $v
            ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('j F Y, g:ia')
            : null;

        // "Load more" carries the cursor + active filters as query params. When the
        // upcoming filter is OFF we must explicitly pass upcoming=false (the controller
        // reads ?upcoming and treats anything != 'false' as ON).
        $moreHref = function () use ($tenantSlug, $nextCursor, $query, $partnerId, $upcoming): string {
            $params = ['tenantSlug' => $tenantSlug, 'cursor' => $nextCursor];
            if ($query !== '') { $params['q'] = $query; }
            if ($partnerId > 0) { $params['partner_id'] = $partnerId; }
            if (!$upcoming) { $params['upcoming'] = 'false'; }
            return route('govuk-alpha.federation.events.index', $params);
        };
    @endphp

    <a href="{{ route('govuk-alpha.federation.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.federation.events_browse.back') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha.federation.events_browse.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.federation.events_browse.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.federation.events_browse.description') }}</p>

    @include('accessible-frontend::partials.federation-nav')

    @if (!$allowed)
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.federation.events_browse.not_available') }}</p></div>
    @else
        <form method="get" action="{{ $indexHref }}" class="govuk-!-margin-bottom-6">
            <fieldset class="govuk-fieldset">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">{{ __('govuk_alpha.federation.events_browse.filters_legend') }}</legend>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="q">{{ __('govuk_alpha.federation.events_browse.search_label') }}</label>
                    <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha.federation.events_browse.search_hint') }}</div>
                    <input class="govuk-input govuk-!-width-two-thirds" id="q" name="q" type="search" value="{{ $query }}" aria-describedby="q-hint">
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="partner_id">{{ __('govuk_alpha.federation.events_browse.filter_by_community') }}</label>
                    <select class="govuk-select" id="partner_id" name="partner_id">
                        <option value="" @selected($partnerId === 0)>{{ __('govuk_alpha.federation.events_browse.all_communities') }}</option>
                        @foreach ($partnerOptions as $partner)
                            <option value="{{ $partner['id'] }}" @selected($partnerId === (int) ($partner['id'] ?? 0))>{{ $partner['name'] ?? '' }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Upcoming filter defaults ON. The hidden input submits upcoming=false
                     when the checkbox is unchecked; when checked, the checkbox's own
                     value=1 appears LAST and wins. So unchecked => 'false' (OFF),
                     checked => '1' (ON). --}}
                <div class="govuk-form-group">
                    <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                        <input type="hidden" name="upcoming" value="false">
                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="upcoming" name="upcoming" type="checkbox" value="1" @checked($upcoming)>
                            <label class="govuk-label govuk-checkboxes__label" for="upcoming">{{ __('govuk_alpha.federation.events_browse.upcoming_only') }}</label>
                        </div>
                    </div>
                </div>

                <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.federation.events_browse.apply_filters') }}</button>
            </fieldset>
        </form>

        @if ($loadError)
            <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                <div role="alert">
                    <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.federation.events_browse.unable_to_load') }}</h2>
                    <div class="govuk-error-summary__body">
                        <ul class="govuk-list govuk-error-summary__list">
                            <li><a href="{{ $indexHref }}">{{ __('govuk_alpha.federation.events_browse.try_again') }}</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        @elseif (empty($events))
            @if ($upcoming)
                <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.federation.events_browse.empty_upcoming') }}</p></div>
            @else
                <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.federation.events_browse.empty_general') }}</p></div>
            @endif
        @else
            <div class="nexus-alpha-card-list">
                @foreach ($events as $e)
                    @php
                        $eTitle = trim((string) ($e['title'] ?? '')) ?: __('govuk_alpha.federation.events_browse.title');
                        $eStart = $e['start_date'] ?? null;
                        $when = $dateTimeFmt($eStart);
                        $loc = trim((string) ($e['location'] ?? ''));
                        $isOnline = (bool) ($e['is_online'] ?? false);
                        $cover = trim((string) ($e['cover_image'] ?? ''));
                        $organiser = trim((string) ($e['organiser_name'] ?? ''));
                        $eDesc = trim((string) ($e['description'] ?? ''));
                        $isPast = (!$upcoming && $eStart && \Illuminate\Support\Carbon::parse($eStart)->isPast());
                    @endphp
                    <article class="nexus-alpha-card">
                        @if ($cover !== '')
                            <img class="nexus-alpha-card-image govuk-!-margin-bottom-2" src="{{ $cover }}" alt="">
                        @endif

                        <div class="nexus-alpha-module-row">
                            <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $eTitle }}</h2>
                            @if ($isPast)<strong class="govuk-tag govuk-tag--grey">{{ __('govuk_alpha.federation.events_browse.past_label') }}</strong>@endif
                            @if ($isOnline)<strong class="govuk-tag govuk-tag--turquoise">{{ __('govuk_alpha.federation.events_browse.online_label') }}</strong>@endif
                        </div>

                        @if ($when !== null)
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ $when }}</p>
                        @endif

                        @if ($organiser !== '')
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.federation.events_browse.organiser_label') }}: {{ $organiser }}</p>
                        @endif

                        <p class="govuk-body-s govuk-!-margin-bottom-1">
                            <strong class="govuk-tag govuk-tag--grey">{{ $e['tenant_name'] ?? '' }}</strong>
                        </p>

                        @if ($eDesc !== '')
                            <p class="govuk-body govuk-!-margin-bottom-1">{{ \Illuminate\Support\Str::limit($eDesc, 160) }}</p>
                        @endif

                        <dl class="nexus-alpha-inline-list">
                            @if ($loc !== '' && !$isOnline)
                                <div><dt>{{ __('govuk_alpha.federation.location_label') }}</dt><dd>{{ $loc }}</dd></div>
                            @endif
                            <div><dt>{{ __('govuk_alpha.federation.events_browse.attendees_label') }}</dt><dd>{{ __('govuk_alpha.federation.events_browse.attendees_count', ['count' => number_format((int) ($e['attendees_count'] ?? 0))]) }}</dd></div>
                        </dl>
                    </article>
                @endforeach
            </div>

            @if (!empty($nextCursor))
                <p class="govuk-body govuk-!-margin-top-4">
                    <a class="govuk-link" href="{{ $moreHref() }}">{{ __('govuk_alpha.federation.events_browse.load_more') }}</a>
                </p>
            @endif
        @endif
    @endif
@endsection
