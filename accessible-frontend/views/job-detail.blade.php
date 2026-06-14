{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $jTitle = trim((string) ($job['title'] ?? '')) ?: __('govuk_alpha.jobs.title');
        $jType = (string) ($job['type'] ?? 'volunteer');
        $typeLabel = match ($jType) {
            'paid' => __('govuk_alpha.jobs.type_paid'),
            'timebank' => __('govuk_alpha.jobs.type_timebank'),
            default => __('govuk_alpha.jobs.type_volunteer'),
        };
        $jOrg = trim((string) ($job['organization']['name'] ?? ''));
        $jLocation = (bool) ($job['is_remote'] ?? false)
            ? __('govuk_alpha.jobs.remote')
            : trim((string) ($job['location'] ?? ''));
        $deadlineRaw = $job['deadline'] ?? null;
        $jDeadline = $deadlineRaw ? \Illuminate\Support\Carbon::parse($deadlineRaw)->translatedFormat('j F Y') : null;
        $salaryMin = $job['salary_min'] ?? null;
        $salaryMax = $job['salary_max'] ?? null;
        $currency = trim((string) ($job['salary_currency'] ?? ''));
        $fmtMoney = fn ($v): string => trim($currency . ' ' . number_format((float) $v, 0));
        $jSalary = null;
        if ($salaryMin !== null && $salaryMax !== null && $salaryMin !== '' && $salaryMax !== '') {
            $jSalary = $fmtMoney($salaryMin) . ' – ' . $fmtMoney($salaryMax);
        } elseif ($salaryMin !== null && $salaryMin !== '') {
            $jSalary = $fmtMoney($salaryMin);
        } elseif ($salaryMax !== null && $salaryMax !== '') {
            $jSalary = $fmtMoney($salaryMax);
        }
        $jSkills = is_array($job['skills'] ?? null) ? array_filter($job['skills']) : [];
        $hasApplied = (bool) ($job['has_applied'] ?? false);
    @endphp

    <a href="{{ route('govuk-alpha.jobs.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.jobs.back') }}</a>

    @if ($status === 'applied')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-live="polite" aria-labelledby="job-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="job-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.jobs.states.applied') }}</p></div>
        </div>
    @elseif ($status === 'apply-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert"><h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p class="govuk-body">{{ __('govuk_alpha.jobs.states.apply-failed') }}</p></div></div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ $jOrg !== '' ? $jOrg : ($tenant['name'] ?? $tenantSlug) }}</span>
    <div class="nexus-alpha-module-row">
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">{{ $jTitle }}</h1>
        <strong class="govuk-tag govuk-tag--blue">{{ $typeLabel }}</strong>
    </div>

    <dl class="govuk-summary-list govuk-!-margin-bottom-6">
        @if ($jLocation !== '')
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.jobs.location_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $jLocation }}</dd>
            </div>
        @endif
        @if ($jSalary !== null)
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.jobs.salary_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $jSalary }}</dd>
            </div>
        @endif
        @if ($jDeadline !== null)
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.jobs.deadline_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $jDeadline }}</dd>
            </div>
        @endif
    </dl>

    @if (trim((string) ($job['description'] ?? '')) !== '')
        <h2 class="govuk-heading-l">{{ __('govuk_alpha.jobs.about_label') }}</h2>
        <div class="govuk-body">{!! nl2br(e($job['description'])) !!}</div>
    @endif

    @if (!empty($jSkills))
        <h2 class="govuk-heading-m">{{ __('govuk_alpha.jobs.skills_label') }}</h2>
        <ul class="govuk-list nexus-alpha-tag-list">
            @foreach ($jSkills as $skill)
                <li><strong class="govuk-tag govuk-tag--grey">{{ $skill }}</strong></li>
            @endforeach
        </ul>
    @endif

    <h2 class="govuk-heading-l govuk-!-margin-top-6" id="apply">{{ __('govuk_alpha.jobs.apply_title') }}</h2>
    @if ($hasApplied)
        <p class="govuk-inset-text">{{ __('govuk_alpha.jobs.already_applied') }}</p>
    @else
        <form method="post" action="{{ route('govuk-alpha.jobs.apply', ['tenantSlug' => $tenantSlug, 'id' => $job['id']]) }}">
            @csrf
            <div class="govuk-form-group">
                <label class="govuk-label" for="cover_letter">{{ __('govuk_alpha.jobs.cover_letter_label') }}</label>
                <textarea class="govuk-textarea" id="cover_letter" name="cover_letter" rows="5" maxlength="5000"></textarea>
            </div>
            <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.jobs.apply_button') }}</button>
        </form>
    @endif
@endsection
