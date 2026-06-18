{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
<section>
    @if (!empty($showHeading))
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_search.results.section_listings') }}</h2>
    @endif
    <div class="nexus-alpha-card-list">
        @foreach ($items as $listing)
            @php
                $lTitle = trim((string) ($listing['title'] ?? '')) ?: __('govuk_alpha_search.results.section_listings');
                $lId = (int) ($listing['id'] ?? 0);
                $lType = (string) ($listing['listing_type'] ?? ($listing['type'] ?? ''));
                $lHref = ($lId > 0 && \Illuminate\Support\Facades\Route::has('govuk-alpha.listings.show'))
                    ? route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $lId])
                    : null;
                $lImage = trim((string) ($listing['image_url'] ?? ''));
                $lHours = $listing['hours_estimate'] ?? ($listing['estimated_hours'] ?? null);
            @endphp
            <article class="nexus-alpha-card">
                @if ($lImage !== '')
                    <img class="nexus-alpha-card-thumb" src="{{ $lImage }}" alt="{{ __('govuk_alpha_search.results.image_alt', ['title' => $lTitle]) }}" width="120" height="90" loading="lazy" decoding="async">
                @endif
                <div class="nexus-alpha-module-row">
                    <h3 class="govuk-heading-s govuk-!-margin-bottom-1">
                        @if ($lHref)<a class="govuk-link" href="{{ $lHref }}">{{ $lTitle }}</a>@else{{ $lTitle }}@endif
                    </h3>
                    @if ($lType === 'offer')
                        <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha_search.results.listing_offering') }}</strong>
                    @elseif ($lType === 'request')
                        <strong class="govuk-tag govuk-tag--orange">{{ __('govuk_alpha_search.results.listing_requesting') }}</strong>
                    @endif
                </div>

                @if (!empty($listing['is_featured']))
                    <p class="govuk-!-margin-bottom-2"><strong class="govuk-tag govuk-tag--yellow">{{ __('govuk_alpha_search.results.featured') }}</strong></p>
                @endif

                @if (trim((string) ($listing['description'] ?? '')) !== '')
                    <p class="govuk-body govuk-!-margin-bottom-2">{{ \Illuminate\Support\Str::limit((string) $listing['description'], 200) }}</p>
                @endif

                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">
                    {{ $lHours ? __('govuk_alpha_search.results.hours_estimate', ['hours' => $lHours]) : __('govuk_alpha_search.results.hours_unknown') }}
                </p>

                @if ($lHref)
                    <p class="govuk-body govuk-!-margin-bottom-0">
                        <a class="govuk-link govuk-link--no-visited-state" href="{{ $lHref }}">{{ __('govuk_alpha_search.results.view_listing') }}</a>
                    </p>
                @endif
            </article>
        @endforeach
    </div>
</section>
