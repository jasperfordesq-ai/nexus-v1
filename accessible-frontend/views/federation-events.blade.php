{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $allowed = (bool) ($allowed ?? false);
        $events = $events ?? [];
        $dateFmt = fn ($v): ?string => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('j F Y, g:ia') : null;
        $moreHref = function () use ($tenantSlug, $nextCursor): string {
            return route('govuk-alpha.federation.events.index', ['tenantSlug' => $tenantSlug, 'cursor' => $nextCursor]);
        };
    @endphp

    <a href="{{ route('govuk-alpha.federation.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.federation.events_browse.back') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha.federation.events_browse.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.federation.events_browse.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.federation.events_browse.description') }}</p>

    @if (!$allowed)
        <p class="govuk-inset-text">{{ __('govuk_alpha.federation.events_browse.not_available') }}</p>
    @elseif (empty($events))
        <p class="govuk-inset-text">{{ __('govuk_alpha.federation.events_browse.empty') }}</p>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($events as $e)
                @php
                    $eTitle = trim((string) ($e['title'] ?? '')) ?: __('govuk_alpha.federation.events_browse.title');
                    $when = $dateFmt($e['start_date'] ?? null);
                    $loc = trim((string) ($e['location'] ?? ''));
                    $isOnline = (bool) ($e['is_online'] ?? false);
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $eTitle }}</h2>
                        @if ($isOnline)<strong class="govuk-tag govuk-tag--turquoise">{{ __('govuk_alpha.federation.events_browse.online_label') }}</strong>@endif
                    </div>
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.federation.events_browse.community_label') }}: {{ $e['tenant_name'] ?? '' }}</p>
                    @if ($when !== null)
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ $when }}</p>
                    @endif
                    @if (trim((string) ($e['description'] ?? '')) !== '')
                        <p class="govuk-body govuk-!-margin-bottom-1">{{ \Illuminate\Support\Str::limit($e['description'], 160) }}</p>
                    @endif
                    <dl class="nexus-alpha-inline-list">
                        @if ($loc !== '')
                            <div><dt>{{ __('govuk_alpha.federation.location_label') }}</dt><dd>{{ $loc }}</dd></div>
                        @endif
                        <div><dt>{{ __('govuk_alpha.federation.events_browse.attendees_label') }}</dt><dd>{{ number_format((int) ($e['attendees_count'] ?? 0)) }}</dd></div>
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
@endsection
