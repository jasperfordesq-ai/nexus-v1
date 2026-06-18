{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $e = $employer ?? [];
        $eName = trim((string) ($e['name'] ?? '')) ?: __('govuk_alpha_jobs.employer.title');
        $eAvatar = trim((string) ($e['avatar_url'] ?? ''));
        $eHeadline = trim((string) ($e['headline'] ?? ''));
        $eBio = trim((string) ($e['bio'] ?? ''));
        $eLocation = trim((string) ($e['location'] ?? ''));
        $eSince = !empty($e['member_since']) ? \Illuminate\Support\Carbon::parse($e['member_since'])->translatedFormat('F Y') : null;

        $jobs = $openJobs ?? [];
        $reviews = $employerReviews ?? [];
        $stats = $reviewStats ?? ['average_rating' => null, 'total_reviews' => 0, 'dimensions' => []];
        $avg = $stats['average_rating'] ?? null;
        $totalReviews = (int) ($stats['total_reviews'] ?? 0);
        $dims = is_array($stats['dimensions'] ?? null) ? $stats['dimensions'] : [];

        $dimLabel = function (string $d): string {
            $key = 'govuk_alpha_jobs.employer.dimension_' . $d;
            $label = __($key);
            return $label === $key ? ucfirst($d) : $label;
        };
    @endphp

    <a href="{{ route('govuk-alpha.jobs.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha_jobs.shared.back_to_jobs') }}</a>

    <div class="nexus-alpha-module-row govuk-!-margin-bottom-2">
        @if ($eAvatar !== '')
            <img class="nexus-alpha-avatar" src="{{ $eAvatar }}" alt="" aria-hidden="true" loading="lazy" decoding="async" width="64" height="64">
        @else
            <span class="nexus-alpha-avatar nexus-alpha-avatar--placeholder" aria-hidden="true">{{ mb_strtoupper(mb_substr($eName, 0, 1)) }}</span>
        @endif
        <div>
            <h1 class="govuk-heading-xl govuk-!-margin-bottom-1">{{ $eName }}</h1>
            @if ($eHeadline !== '')
                <p class="govuk-body-l govuk-!-margin-bottom-0">{{ $eHeadline }}</p>
            @endif
        </div>
    </div>

    <p class="govuk-body-s nexus-alpha-meta">
        @if ($eLocation !== ''){{ $eLocation }}@endif
        @if ($eSince !== null) &middot; {{ __('govuk_alpha_jobs.employer.member_since', ['date' => $eSince]) }}@endif
    </p>

    @if ($eBio !== '')
        <div class="govuk-body govuk-!-margin-bottom-6">{!! nl2br(e($eBio)) !!}</div>
    @endif

    {{-- Open opportunities --}}
    <h2 class="govuk-heading-m">{{ __('govuk_alpha_jobs.employer.open_jobs_heading') }}</h2>
    <p class="govuk-body-s nexus-alpha-meta">{{ trans_choice('govuk_alpha_jobs.employer.open_jobs_count', count($jobs), ['count' => count($jobs)]) }}</p>
    @if (empty($jobs))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_jobs.employer.no_open_jobs') }}</p></div>
    @else
        <div class="nexus-alpha-card-list govuk-!-margin-bottom-6">
            @foreach ($jobs as $job)
                @include('accessible-frontend::partials.job-card', ['job' => $job, 'tenantSlug' => $tenantSlug])
            @endforeach
        </div>
    @endif

    {{-- Employer reviews --}}
    <h2 class="govuk-heading-m">{{ __('govuk_alpha_jobs.employer.reviews_heading') }}</h2>
    @if ($totalReviews === 0)
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_jobs.employer.no_reviews') }}</p></div>
    @else
        @if ($avg !== null)
            <p class="govuk-body">
                <strong>{{ __('govuk_alpha_jobs.employer.average_rating') }}:</strong>
                {{ (float) $avg }} / 5
                <progress value="{{ (float) $avg }}" max="5" aria-label="{{ __('govuk_alpha_jobs.employer.rating_aria', ['rating' => (float) $avg]) }}">{{ (float) $avg }} / 5</progress>
                <span class="govuk-body-s nexus-alpha-meta">{{ trans_choice('govuk_alpha_jobs.employer.reviews_count', $totalReviews, ['count' => $totalReviews]) }}</span>
            </p>
        @endif

        @if (!empty($dims))
            <h3 class="govuk-heading-s">{{ __('govuk_alpha_jobs.employer.dimensions_heading') }}</h3>
            <table class="govuk-table govuk-!-margin-bottom-6">
                <caption class="govuk-table__caption govuk-visually-hidden">{{ __('govuk_alpha_jobs.employer.dimensions_heading') }}</caption>
                <tbody class="govuk-table__body">
                    @foreach ($dims as $dimKey => $dimScore)
                        <tr class="govuk-table__row">
                            <th scope="row" class="govuk-table__header govuk-!-font-weight-regular">{{ $dimLabel((string) $dimKey) }}</th>
                            <td class="govuk-table__cell">
                                <progress value="{{ (float) $dimScore }}" max="5" aria-label="{{ __('govuk_alpha_jobs.employer.dimension_aria', ['dimension' => $dimLabel((string) $dimKey), 'score' => (float) $dimScore]) }}">{{ (float) $dimScore }} / 5</progress>
                            </td>
                            <td class="govuk-table__cell govuk-table__cell--numeric">{{ (float) $dimScore }} / 5</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="nexus-alpha-card-list">
            @foreach ($reviews as $r)
                @php
                    $rRating = (int) ($r['rating'] ?? 0);
                    $rComment = trim((string) ($r['comment'] ?? ''));
                    $rReviewer = trim((string) ($r['reviewer_name'] ?? ''));
                    $rDate = !empty($r['created_at']) ? \Illuminate\Support\Carbon::parse($r['created_at'])->translatedFormat('j F Y') : null;
                @endphp
                <article class="nexus-alpha-card">
                    <p class="govuk-body govuk-!-margin-bottom-1">
                        <strong>{{ $rRating }} / 5</strong>
                        <progress value="{{ $rRating }}" max="5" aria-label="{{ __('govuk_alpha_jobs.employer.review_rating_aria', ['rating' => $rRating]) }}">{{ $rRating }} / 5</progress>
                    </p>
                    @if ($rComment !== '')
                        <div class="govuk-body govuk-!-margin-bottom-1">{!! nl2br(e($rComment)) !!}</div>
                    @endif
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">
                        @if ($rReviewer !== ''){{ $rReviewer }}@endif
                        @if ($rDate !== null) &middot; {{ $rDate }}@endif
                    </p>
                </article>
            @endforeach
        </div>
    @endif
@endsection
