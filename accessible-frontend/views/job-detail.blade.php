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
        $jCommitment = (string) ($job['commitment'] ?? '');
        $commitmentLabel = match ($jCommitment) {
            'full_time' => __('govuk_alpha.jobs_t2.commitment_full_time'),
            'part_time' => __('govuk_alpha.jobs_t2.commitment_part_time'),
            'flexible' => __('govuk_alpha.jobs_t2.commitment_flexible'),
            'one_off' => __('govuk_alpha.jobs_t2.commitment_one_off'),
            default => '',
        };
        $jOrg = trim((string) ($job['organization']['name'] ?? ''));
        $jPoster = $jOrg !== '' ? $jOrg : trim((string) ($job['creator']['name'] ?? ''));
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
        $isSaved = (bool) ($job['is_saved'] ?? false);
        $isOwner = (bool) ($isJobOwner ?? false);
        $jViews = (int) ($job['views_count'] ?? 0);
        $jApps = (int) ($job['applications_count'] ?? 0);
        $match = $jobMatch ?? null;
        $similar = $similarJobs ?? [];
    @endphp

    <a href="{{ route('govuk-alpha.jobs.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.jobs.back') }}</a>

    @if ($status === 'applied')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="job-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="job-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.jobs.states.applied') }}</p></div>
        </div>
    @elseif ($status === 'saved')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="job-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="job-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.jobs_t2.states.saved') }}</p></div>
        </div>
    @elseif ($status === 'unsaved')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="job-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="job-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.jobs_t2.states.unsaved') }}</p></div>
        </div>
    @elseif (in_array($status, ['created', 'updated', 'renewed'], true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="job-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="job-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.jobs_t3.states.' . $status) }}</p></div>
        </div>
    @elseif (in_array($status, ['apply-failed', 'apply-safeguarding-restricted', 'apply-safeguarding-unavailable', 'save-failed', 'renew-failed', 'cv-invalid', 'cv-too-large', 'cover-required'], true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert"><h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li>
                    @if ($status === 'save-failed'){{ __('govuk_alpha.jobs_t2.states.save-failed') }}
                    @elseif ($status === 'renew-failed'){{ __('govuk_alpha.jobs_t3.states.renew-failed') }}
                    @elseif ($status === 'cv-invalid'){{ __('govuk_alpha.jobs.states.cv-invalid') }}
                    @elseif ($status === 'cv-too-large'){{ __('govuk_alpha.jobs.states.cv-too-large') }}
                    @elseif ($status === 'cover-required'){{ __('govuk_alpha.jobs.states.cover-required') }}
                    @elseif ($status === 'apply-safeguarding-restricted'){{ __('safeguarding.errors.interaction_not_allowed') }}
                    @elseif ($status === 'apply-safeguarding-unavailable'){{ __('safeguarding.errors.policy_unavailable') }}
                    @else{{ __('govuk_alpha.jobs.states.apply-failed') }}@endif
                </li></ul></div></div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ $jPoster !== '' ? $jPoster : ($tenant['name'] ?? $tenantSlug) }}</span>
    <div class="nexus-alpha-module-row">
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">{{ $jTitle }}</h1>
        <strong class="govuk-tag govuk-tag--blue">{{ $typeLabel }}</strong>
    </div>

    {{-- Save / unsave toggle (members only; the owner manages, not saves, their role). --}}
    @unless ($isOwner)
        @if ($isSaved)
            <form method="post" action="{{ route('govuk-alpha.jobs.unsave', ['tenantSlug' => $tenantSlug, 'id' => $job['id']]) }}" class="govuk-!-margin-bottom-4">
                @csrf
                <input type="hidden" name="from" value="detail">
                <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.jobs_t2.unsave_button') }}</button>
            </form>
        @else
            <form method="post" action="{{ route('govuk-alpha.jobs.save', ['tenantSlug' => $tenantSlug, 'id' => $job['id']]) }}" class="govuk-!-margin-bottom-4">
                @csrf
                <input type="hidden" name="from" value="detail">
                <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.jobs_t2.save_button') }}</button>
            </form>
        @endif
    @endunless

    <dl class="govuk-summary-list govuk-!-margin-bottom-6">
        @if ($commitmentLabel !== '')
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.jobs_t2.commitment_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $commitmentLabel }}</dd>
            </div>
        @endif
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
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.jobs_t2.views_label') }}</dt>
            <dd class="govuk-summary-list__value">{{ $jViews }}</dd>
        </div>
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.jobs_t2.applications_label') }}</dt>
            <dd class="govuk-summary-list__value">{{ $jApps }}</dd>
        </div>
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

    {{-- Skills match for the viewer. --}}
    @if (is_array($match) && !empty($match['required_skills']))
        <h2 class="govuk-heading-m govuk-!-margin-top-6">{{ __('govuk_alpha.jobs_t2.match_heading') }}</h2>
        @php $pct = (int) ($match['percentage'] ?? 0); @endphp
        <p class="govuk-body govuk-!-font-weight-bold">{{ __('govuk_alpha.jobs_t2.match_percent', ['percent' => $pct]) }}</p>
        <progress max="100" value="{{ $pct }}" aria-label="{{ __('govuk_alpha.jobs_t2.match_percent', ['percent' => $pct]) }}">{{ $pct }}%</progress>
        @if (!empty($match['matched']))
            <h3 class="govuk-heading-s govuk-!-margin-top-3">{{ __('govuk_alpha.jobs_t2.match_have') }}</h3>
            <ul class="govuk-list nexus-alpha-tag-list">
                @foreach ($match['matched'] as $skill)
                    <li><strong class="govuk-tag govuk-tag--green">{{ $skill }}</strong></li>
                @endforeach
            </ul>
        @endif
        @if (!empty($match['missing']))
            <h3 class="govuk-heading-s govuk-!-margin-top-3">{{ __('govuk_alpha.jobs_t2.match_missing') }}</h3>
            <ul class="govuk-list nexus-alpha-tag-list">
                @foreach ($match['missing'] as $skill)
                    <li><strong class="govuk-tag govuk-tag--orange">{{ $skill }}</strong></li>
                @endforeach
            </ul>
        @endif
    @endif

    {{-- Apply / owner / already-applied. --}}
    @if ($isOwner)
        <div class="govuk-inset-text govuk-!-margin-top-6">{{ __('govuk_alpha.jobs_t2.owner_notice') }}</div>
        <div class="nexus-alpha-actions govuk-!-margin-bottom-2">
            <a class="govuk-link" href="{{ route('govuk-alpha.jobs.applicants', ['tenantSlug' => $tenantSlug, 'id' => $job['id']]) }}">{{ __('govuk_alpha.jobs_t3.manage_button') }}</a>
            <a class="govuk-link" href="{{ route('govuk-alpha.jobs.edit', ['tenantSlug' => $tenantSlug, 'id' => $job['id']]) }}">{{ __('govuk_alpha.jobs_t3.edit_button') }}</a>
        </div>
        <form method="post" action="{{ route('govuk-alpha.jobs.renew', ['tenantSlug' => $tenantSlug, 'id' => $job['id']]) }}">
            @csrf
            <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.jobs_t3.renew_button') }}</button>
        </form>
    @else
        <h2 class="govuk-heading-l govuk-!-margin-top-6" id="apply">{{ __('govuk_alpha.jobs.apply_title') }}</h2>
        @if ($hasApplied)
            <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.jobs.already_applied') }}</p></div>
        @else
            <form method="post" action="{{ route('govuk-alpha.jobs.apply', ['tenantSlug' => $tenantSlug, 'id' => $job['id']]) }}" enctype="multipart/form-data">
                @csrf
                <div class="govuk-form-group">
                    <label class="govuk-label" for="cover_letter">{{ __('govuk_alpha.jobs.cover_letter_label') }}</label>
                    <textarea class="govuk-textarea" id="cover_letter" name="cover_letter" rows="5" maxlength="5000"></textarea>
                </div>
                @if (!empty($cvUploadEnabled))
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="cv">{{ __('govuk_alpha.jobs.cv_label') }}</label>
                        <div id="cv-hint" class="govuk-hint">{{ __('govuk_alpha.jobs.cv_hint') }}</div>
                        <input class="govuk-file-upload" id="cv" name="cv" type="file" accept=".pdf,.doc,.docx" aria-describedby="cv-hint">
                    </div>
                @endif
                <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.jobs.apply_button') }}</button>
            </form>
        @endif
    @endif

    {{-- Similar opportunities. --}}
    @if (!empty($similar))
        <h2 class="govuk-heading-l govuk-!-margin-top-6">{{ __('govuk_alpha.jobs_t2.similar_heading') }}</h2>
        <ul class="govuk-list">
            @foreach ($similar as $s)
                <li class="govuk-!-margin-bottom-2">
                    <a class="govuk-link" href="{{ route('govuk-alpha.jobs.show', ['tenantSlug' => $tenantSlug, 'id' => $s['id']]) }}">{{ $s['title'] }}</a>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
