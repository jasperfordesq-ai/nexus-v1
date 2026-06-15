{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <span class="govuk-caption-xl">{{ __('govuk_alpha.matches.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.matches.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.matches.description') }}</p>

    @php
        $activeSource = request()->query('source', 'all');
        $allMatchItems = $matches; // full set from controller (already source-filtered if ?source= passed)
        // Compute stats from the full set (passed as $matchStats if available, else compute inline)
        $totalMatches = $matchStats['total'] ?? count($allMatchItems);
        $avgScore = $matchStats['avg_score'] ?? (count($allMatchItems) > 0
            ? (int) round(array_sum(array_map(fn($m) => (float)($m['match_score'] ?? 0) > 1 ? (float)($m['match_score'] ?? 0) : (float)($m['match_score'] ?? 0) * 100, $allMatchItems)) / count($allMatchItems))
            : 0);
        $sourceTabs = [
            'all'         => __('govuk_alpha.polish_listings.matches_source_all'),
            'listing'     => __('govuk_alpha.polish_listings.matches_source_listing'),
            'group'       => __('govuk_alpha.polish_listings.matches_source_group'),
            'volunteering'=> __('govuk_alpha.polish_listings.matches_source_volunteering'),
            'event'       => __('govuk_alpha.polish_listings.matches_source_event'),
        ];
    @endphp

    @if (!empty($allMatchItems))
        {{-- Stats summary --}}
        <dl class="govuk-summary-list govuk-!-margin-bottom-6">
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.polish_listings.matches_total_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $totalMatches }}</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.polish_listings.matches_avg_score_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $avgScore }}%</dd>
            </div>
        </dl>

        {{-- Source filter tabs --}}
        <nav aria-label="{{ __('govuk_alpha.polish_listings.matches_source_filter_legend') }}" class="govuk-!-margin-bottom-6">
            <ul class="govuk-list" style="display:flex;gap:0.5rem;flex-wrap:wrap;list-style:none;padding:0;margin:0;">
                @foreach ($sourceTabs as $srcKey => $srcLabel)
                    <li>
                        <a class="govuk-link{{ $activeSource === $srcKey ? ' govuk-link--no-visited-state' : '' }}"
                           href="{{ route('govuk-alpha.matches.index', ['tenantSlug' => $tenantSlug, 'source' => $srcKey]) }}"
                           @if ($activeSource === $srcKey) aria-current="page" @endif>{{ $srcLabel }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif

    @if (empty($allMatchItems))
        <div class="govuk-inset-text">{{ __('govuk_alpha.matches.empty') }}</div>
        <a class="govuk-button" href="{{ route('govuk-alpha.listings.create', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.matches.add_listing') }}</a>
    @else
        <div class="nexus-alpha-card-list govuk-!-margin-top-4">
            @foreach ($allMatchItems as $m)
                @php
                    $score = (float) ($m['match_score'] ?? 0);
                    // Regular matches score 0–1; cold-start matches use a 0–100 scale.
                    $pct = $score > 1 ? min(100, (int) round($score)) : (int) round($score * 100);
                    $matchModule = $m['module'] ?? 'listing';
                    $listingType = ($m['type'] ?? 'offer') === 'request' ? 'request' : 'offer';
                    $owner = trim((string) ($m['user_name'] ?? '')) ?: __('govuk_alpha.members.unknown_member');
                    $ownerLoc = trim((string) ($m['author_location'] ?? ''));
                    $reasons = array_values(array_filter(is_array($m['match_reasons'] ?? null) ? $m['match_reasons'] : []));
                    $listingId = (int) ($m['id'] ?? $m['listing_id'] ?? 0);
                    $listingTitle = trim((string) ($m['title'] ?? '')) ?: __('govuk_alpha.matches.view_listing');
                    $category = trim((string) ($m['category_name'] ?? ''));
                    $metaParts = array_values(array_filter([
                        __('govuk_alpha.matches.by_label', ['name' => $owner]),
                        $ownerLoc !== '' ? $ownerLoc : null,
                        $category !== '' ? $category : null,
                    ]));
                    // Module label for cross-module tag
                    $moduleTagKey = 'govuk_alpha.polish_listings.matches_module_' . $matchModule;
                    $moduleLabel = \Illuminate\Support\Facades\Lang::has($moduleTagKey) ? __($moduleTagKey) : null;
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h2 class="govuk-heading-m govuk-!-margin-bottom-1">
                            @if ($listingId > 0 && $matchModule === 'listing')
                                <a class="govuk-link" href="{{ route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $listingId]) }}">{{ $listingTitle }}</a>
                            @else
                                {{ $listingTitle }}
                            @endif
                        </h2>
                        @if ($moduleLabel)
                            <strong class="govuk-tag govuk-tag--grey">{{ $moduleLabel }}</strong>
                        @endif
                        @if ($matchModule === 'listing')
                            <strong class="govuk-tag {{ $listingType === 'request' ? 'govuk-tag--purple' : 'govuk-tag--blue' }}">
                                {{ $listingType === 'request' ? __('govuk_alpha.matches.type_request') : __('govuk_alpha.matches.type_offer') }}
                            </strong>
                        @endif
                    </div>
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">{{ implode(' · ', $metaParts) }}</p>
                    <p class="govuk-body govuk-!-margin-bottom-2">
                        <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.matches.match_label', ['percent' => $pct]) }}</strong>
                    </p>
                    @if (!empty($reasons))
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha.matches.reasons_label') }}</h3>
                        <ul class="govuk-list govuk-list--bullet govuk-!-margin-bottom-2">
                            @foreach (array_slice($reasons, 0, 4) as $reason)
                                <li>{{ $reason }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($matchModule === 'listing' && $listingId > 0)
                        <form method="post" action="{{ route('govuk-alpha.matches.dismiss', ['tenantSlug' => $tenantSlug, 'id' => $listingId]) }}" class="govuk-!-margin-top-2">
                            @csrf
                            <input type="hidden" name="reason" value="not_relevant">
                            <button class="govuk-button govuk-button--secondary govuk-!-font-size-16" data-module="govuk-button" title="{{ __('govuk_alpha.polish_listings.matches_dismiss_hint') }}">
                                {{ __('govuk_alpha.polish_listings.matches_dismiss_label') }}
                                <span class="govuk-visually-hidden">{{ __('govuk_alpha.polish_listings.matches_dismiss_sr', ['title' => $listingTitle]) }}</span>
                            </button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
