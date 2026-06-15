{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
{{--
    A single opportunity card, shared by the browse and saved lists.
    Inputs: $job (enriched vacancy array), $tenantSlug.
    Optional: $showUnsave (bool) + $unsaveFrom (string) to render a remove-from-saved
    form; the save toggle itself lives on the detail page.
--}}
@php
    $jcId = (int) ($job['id'] ?? 0);
    $jcTitle = trim((string) ($job['title'] ?? '')) ?: __('govuk_alpha.jobs.title');
    $jcType = (string) ($job['type'] ?? 'volunteer');
    $jcTypeLabel = match ($jcType) {
        'paid' => __('govuk_alpha.jobs.type_paid'),
        'timebank' => __('govuk_alpha.jobs.type_timebank'),
        default => __('govuk_alpha.jobs.type_volunteer'),
    };
    $jcCommitment = (string) ($job['commitment'] ?? '');
    $jcCommitmentLabel = match ($jcCommitment) {
        'full_time' => __('govuk_alpha.jobs_t2.commitment_full_time'),
        'part_time' => __('govuk_alpha.jobs_t2.commitment_part_time'),
        'flexible' => __('govuk_alpha.jobs_t2.commitment_flexible'),
        'one_off' => __('govuk_alpha.jobs_t2.commitment_one_off'),
        default => '',
    };
    $jcRemote = (bool) ($job['is_remote'] ?? false);
    $jcLocation = $jcRemote ? __('govuk_alpha.jobs_t2.remote_tag') : trim((string) ($job['location'] ?? ''));
    $jcDeadline = !empty($job['deadline']) ? \Illuminate\Support\Carbon::parse($job['deadline'])->translatedFormat('j F Y') : null;
    $jcOrg = trim((string) ($job['organization']['name'] ?? ''));
    $jcPoster = $jcOrg !== '' ? $jcOrg : trim((string) ($job['creator']['name'] ?? ''));
    $jcViews = (int) ($job['views_count'] ?? 0);
    $jcApps = (int) ($job['applications_count'] ?? 0);
    $jcFeatured = (bool) ($job['is_featured'] ?? false);
    $jcApplied = (bool) ($job['has_applied'] ?? false);
    $jcSaved = (bool) ($job['is_saved'] ?? false);

    $jcCurrency = trim((string) ($job['salary_currency'] ?? ''));
    $jcMoney = fn ($v): string => trim($jcCurrency . ' ' . number_format((float) $v, 0));
    $jcSalary = null;
    $sMin = $job['salary_min'] ?? null;
    $sMax = $job['salary_max'] ?? null;
    if ($sMin !== null && $sMin !== '' && $sMax !== null && $sMax !== '') {
        $jcSalary = $jcMoney($sMin) . ' – ' . $jcMoney($sMax);
    } elseif ($sMin !== null && $sMin !== '') {
        $jcSalary = $jcMoney($sMin);
    } elseif ($sMax !== null && $sMax !== '') {
        $jcSalary = $jcMoney($sMax);
    }
@endphp
<article class="nexus-alpha-card">
    <div class="nexus-alpha-module-row">
        <h3 class="govuk-heading-s govuk-!-margin-bottom-1">
            <a class="govuk-link" href="{{ route('govuk-alpha.jobs.show', ['tenantSlug' => $tenantSlug, 'id' => $jcId]) }}">{{ $jcTitle }}</a>
        </h3>
        <strong class="govuk-tag govuk-tag--blue">{{ $jcTypeLabel }}</strong>
    </div>

    @if ($jcPoster !== '')
        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.jobs_t2.posted_by', ['name' => $jcPoster]) }}</p>
    @endif

    <ul class="govuk-list nexus-alpha-tag-list govuk-!-margin-bottom-1">
        @if ($jcCommitmentLabel !== '')
            <li><strong class="govuk-tag govuk-tag--grey">{{ $jcCommitmentLabel }}</strong></li>
        @endif
        @if ($jcRemote)
            <li><strong class="govuk-tag govuk-tag--turquoise">{{ __('govuk_alpha.jobs_t2.remote_tag') }}</strong></li>
        @endif
        @if ($jcFeatured)
            <li><strong class="govuk-tag govuk-tag--yellow">{{ __('govuk_alpha.jobs_t2.featured_tag') }}</strong></li>
        @endif
        @if ($jcApplied)
            <li><strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.jobs_t2.applied_tag') }}</strong></li>
        @endif
        @if ($jcSaved && empty($showUnsave))
            <li><strong class="govuk-tag govuk-tag--purple">{{ __('govuk_alpha.jobs_t2.saved_tag') }}</strong></li>
        @endif
    </ul>

    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">
        @if ($jcLocation !== ''){{ $jcLocation }}@endif
        @if ($jcSalary !== null) &middot; {{ $jcSalary }}@endif
        @if ($jcDeadline !== null) &middot; {{ __('govuk_alpha.jobs.deadline', ['date' => $jcDeadline]) }}@endif
    </p>

    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">
        {{ trans_choice('govuk_alpha.jobs_t2.views_count', $jcViews, ['count' => $jcViews]) }}
        &middot; {{ trans_choice('govuk_alpha.jobs_t2.applications_count', $jcApps, ['count' => $jcApps]) }}
    </p>

    @if (!empty($showUnsave))
        <form method="post" action="{{ route('govuk-alpha.jobs.unsave', ['tenantSlug' => $tenantSlug, 'id' => $jcId]) }}" class="govuk-!-margin-top-2">
            @csrf
            <input type="hidden" name="from" value="{{ $unsaveFrom ?? 'saved' }}">
            <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.jobs_t2.unsave_button') }}</button>
        </form>
    @endif
</article>
