{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <span class="govuk-caption-xl">{{ __('govuk_alpha.matches.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.matches.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.matches.description') }}</p>

    @if (empty($matches))
        <div class="govuk-inset-text">{{ __('govuk_alpha.matches.empty') }}</div>
        <a class="govuk-button" href="{{ route('govuk-alpha.listings.create', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.matches.add_listing') }}</a>
    @else
        <div class="nexus-alpha-card-list govuk-!-margin-top-4">
            @foreach ($matches as $m)
                @php
                    $score = (float) ($m['match_score'] ?? 0);
                    // Regular matches score 0–1; cold-start matches use a 0–100 scale.
                    $pct = $score > 1 ? min(100, (int) round($score)) : (int) round($score * 100);
                    $listingType = ($m['type'] ?? 'offer') === 'request' ? 'request' : 'offer';
                    $owner = trim((string) ($m['user_name'] ?? '')) ?: __('govuk_alpha.members.unknown_member');
                    $ownerLoc = trim((string) ($m['author_location'] ?? ''));
                    $reasons = array_values(array_filter(is_array($m['match_reasons'] ?? null) ? $m['match_reasons'] : []));
                    $listingId = (int) ($m['id'] ?? 0);
                    $listingTitle = trim((string) ($m['title'] ?? '')) ?: __('govuk_alpha.matches.view_listing');
                    $category = trim((string) ($m['category_name'] ?? ''));
                    $metaParts = array_values(array_filter([
                        __('govuk_alpha.matches.by_label', ['name' => $owner]),
                        $ownerLoc !== '' ? $ownerLoc : null,
                        $category !== '' ? $category : null,
                    ]));
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h2 class="govuk-heading-m govuk-!-margin-bottom-1">
                            @if ($listingId > 0)
                                <a class="govuk-link" href="{{ route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $listingId]) }}">{{ $listingTitle }}</a>
                            @else
                                {{ $listingTitle }}
                            @endif
                        </h2>
                        <strong class="govuk-tag {{ $listingType === 'request' ? 'govuk-tag--purple' : 'govuk-tag--blue' }}">
                            {{ $listingType === 'request' ? __('govuk_alpha.matches.type_request') : __('govuk_alpha.matches.type_offer') }}
                        </strong>
                    </div>
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">{{ implode(' · ', $metaParts) }}</p>
                    <p class="govuk-body govuk-!-margin-bottom-2">
                        <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.matches.match_label', ['percent' => $pct]) }}</strong>
                    </p>
                    @if (!empty($reasons))
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha.matches.reasons_label') }}</h3>
                        <ul class="govuk-list govuk-list--bullet govuk-!-margin-bottom-0">
                            @foreach (array_slice($reasons, 0, 4) as $reason)
                                <li>{{ $reason }}</li>
                            @endforeach
                        </ul>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
