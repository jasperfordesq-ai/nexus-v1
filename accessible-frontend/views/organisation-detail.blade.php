{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $oName = trim((string) ($organisation['name'] ?? '')) ?: __('govuk_alpha.organisations.title');
        $oWebsite = trim((string) ($organisation['website'] ?? ''));
        $oEmail = trim((string) ($organisation['email'] ?? ($organisation['contact_email'] ?? '')));

        // WAVE O: depth data (opportunities, reviews, stats). Always arrays.
        $opportunities = isset($orgOpportunities) && is_array($orgOpportunities) ? $orgOpportunities : [];
        $reviews = isset($orgReviews) && is_array($orgReviews) ? $orgReviews : [];
        $stats = isset($orgStats) && is_array($orgStats) ? $orgStats : [];

        // Volunteer count comes from the organisation profile (getOrganisationById
        // computes it); the stats helper supplies hours / rating / reviews.
        $volunteerCount = $organisation['volunteer_count'] ?? ($stats['volunteer_count'] ?? null);
        $openOpps = (int) ($stats['opportunity_count'] ?? count($opportunities));
        $totalHours = (float) ($stats['total_hours'] ?? 0);
        $reviewCount = (int) ($stats['review_count'] ?? count($reviews));
        $avgRating = (float) ($stats['average_rating'] ?? 0);
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.organisations.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.organisations.title') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha.organisations.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ $oName }}</h1>

    @if (trim((string) ($organisation['description'] ?? '')) !== '')
        <p class="govuk-body-l">{{ $organisation['description'] }}</p>
    @endif

    @if ($oWebsite !== '' || $oEmail !== '')
        <dl class="govuk-summary-list">
            @if ($oEmail !== '')
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha.organisations.email_label') }}</dt>
                    <dd class="govuk-summary-list__value"><a class="govuk-link" href="mailto:{{ $oEmail }}">{{ $oEmail }}</a></dd>
                </div>
            @endif
            @if ($oWebsite !== '')
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha.organisations.website_label') }}</dt>
                    <dd class="govuk-summary-list__value"><a class="govuk-link" href="{{ \Illuminate\Support\Str::startsWith($oWebsite, ['http://', 'https://']) ? $oWebsite : 'https://' . $oWebsite }}" rel="nofollow noopener noreferrer" target="_blank">{{ $oWebsite }}<span class="govuk-visually-hidden"> {{ __('govuk_alpha.opens_new_tab') }}</span></a></dd>
                </div>
            @endif
        </dl>
    @endif

    @if ((int) ($organisation['id'] ?? 0) > 0)
        <p class="govuk-body">
            <a class="govuk-link" href="{{ route('govuk-alpha.organisations.jobs', ['tenantSlug' => $tenantSlug, 'id' => (int) $organisation['id']]) }}">{{ __('govuk_alpha_organisations.nav.jobs') }}</a>
        </p>
    @endif

    {{-- WAVE O: Organisation stats -------------------------------------------------- --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha.org_depth.stats_heading') }}</h2>
    <dl class="nexus-alpha-inline-list">
        <div>
            <dt>{{ __('govuk_alpha.org_depth.stat_opportunities') }}</dt>
            <dd>{{ number_format($openOpps) }}</dd>
        </div>
        @if ($volunteerCount !== null)
            <div>
                <dt>{{ __('govuk_alpha.org_depth.stat_volunteers') }}</dt>
                <dd>{{ number_format((int) $volunteerCount) }}</dd>
            </div>
        @endif
        <div>
            <dt>{{ __('govuk_alpha.org_depth.stat_hours') }}</dt>
            <dd>{{ number_format($totalHours, ($totalHours == (int) $totalHours) ? 0 : 1) }}</dd>
        </div>
        <div>
            <dt>{{ __('govuk_alpha.org_depth.stat_reviews') }}</dt>
            <dd>{{ number_format($reviewCount) }}</dd>
        </div>
        @if ($reviewCount > 0)
            <div>
                <dt>{{ __('govuk_alpha.org_depth.stat_rating') }}</dt>
                <dd>
                    <progress max="5" value="{{ number_format($avgRating, 1, '.', '') }}" aria-label="{{ __('govuk_alpha.org_depth.stat_rating_value', ['rating' => number_format($avgRating, 1)]) }}">{{ __('govuk_alpha.org_depth.stat_rating_value', ['rating' => number_format($avgRating, 1)]) }}</progress>
                    <span class="govuk-!-margin-left-2">{{ __('govuk_alpha.org_depth.stat_rating_value', ['rating' => number_format($avgRating, 1)]) }}</span>
                </dd>
            </div>
        @endif
    </dl>

    {{-- WAVE O: Volunteering opportunities ------------------------------------------ --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha.org_depth.opportunities_heading') }}</h2>
    @if (empty($opportunities))
        <div class="govuk-inset-text">{{ __('govuk_alpha.org_depth.opportunities_empty') }}</div>
    @else
        <p class="govuk-body">{{ __('govuk_alpha.org_depth.opportunities_summary', ['name' => $oName]) }}</p>
        <div class="nexus-alpha-card-list">
            @foreach ($opportunities as $opp)
                @php
                    $oppId = (int) ($opp['id'] ?? 0);
                    $oppTitle = trim((string) ($opp['title'] ?? '')) ?: __('govuk_alpha.organisations.title');
                    $oppExcerpt = \Illuminate\Support\Str::limit(trim((string) ($opp['description'] ?? '')), 180);
                    $oppRemote = ! empty($opp['is_remote']);
                @endphp
                @if ($oppId > 0)
                    <article class="nexus-alpha-card">
                        <h3 class="govuk-heading-m govuk-!-margin-bottom-2">
                            <a class="govuk-link" href="{{ route('govuk-alpha.volunteering.show', ['tenantSlug' => $tenantSlug, 'id' => $oppId]) }}">{{ $oppTitle }}</a>
                        </h3>
                        @if ($oppRemote)
                            <p class="govuk-!-margin-bottom-2"><strong class="govuk-tag govuk-tag--turquoise">{{ __('govuk_alpha.org_depth.opportunity_remote') }}</strong></p>
                        @endif
                        @if ($oppExcerpt !== '')
                            <p class="govuk-body">{{ $oppExcerpt }}</p>
                        @endif
                        <div class="nexus-alpha-actions">
                            <a class="govuk-link" href="{{ route('govuk-alpha.volunteering.show', ['tenantSlug' => $tenantSlug, 'id' => $oppId]) }}">{{ __('govuk_alpha.org_depth.opportunity_view') }}</a>
                            <a class="govuk-link" href="{{ route('govuk-alpha.organisations.apply.form', ['tenantSlug' => $tenantSlug, 'id' => $oppId]) }}">{{ __('govuk_alpha_organisations.nav.apply') }}</a>
                        </div>
                    </article>
                @endif
            @endforeach
        </div>
    @endif

    {{-- WAVE O: Volunteer reviews --------------------------------------------------- --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha.org_depth.reviews_heading') }}</h2>
    @if (empty($reviews))
        <div class="govuk-inset-text">{{ __('govuk_alpha.org_depth.reviews_empty') }}</div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($reviews as $review)
                @php
                    $rRating = max(0, min(5, (int) ($review['rating'] ?? 0)));
                    $rComment = trim((string) ($review['comment'] ?? ''));
                    $rAuthor = trim((string) ($review['author']['name'] ?? '')) ?: __('emails.common.fallback_someone');
                    $rAvatar = $review['author']['avatar'] ?? null;
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-card-head">
                        @if (! empty($rAvatar))
                            <img class="nexus-alpha-avatar" src="{{ $rAvatar }}" alt="" width="48" height="48" loading="lazy" decoding="async">
                        @else
                            <span class="nexus-alpha-avatar nexus-alpha-avatar--placeholder" aria-hidden="true">{{ mb_strtoupper(mb_substr($rAuthor, 0, 1)) }}</span>
                        @endif
                        <span class="govuk-body govuk-!-margin-bottom-0 govuk-!-font-weight-bold">{{ $rAuthor }}</span>
                    </div>
                    <p class="govuk-!-margin-bottom-2">
                        <progress max="5" value="{{ $rRating }}" aria-label="{{ __('govuk_alpha.org_depth.review_rating_label', ['rating' => $rRating]) }}">{{ __('govuk_alpha.org_depth.review_rating_label', ['rating' => $rRating]) }}</progress>
                        <span class="govuk-!-margin-left-2">{{ __('govuk_alpha.org_depth.review_rating_label', ['rating' => $rRating]) }}</span>
                    </p>
                    @if ($rComment !== '')
                        <div class="govuk-body">{!! nl2br(e($rComment)) !!}</div>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
