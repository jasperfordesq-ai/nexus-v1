{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $jobId = (int) ($job['id'] ?? 0);
        $jobTitle = trim((string) ($job['title'] ?? '')) ?: __('govuk_alpha.jobs.title');
        $statusLabel = function (?string $s): string {
            $s = (string) $s;
            $key = 'govuk_alpha.jobs_t2.app_status_' . $s;
            $label = __($key);
            return $label === $key ? ucfirst(str_replace('_', ' ', $s)) : $label;
        };
        $stageOptions = ['applied', 'pending', 'screening', 'reviewed', 'shortlisted', 'interview', 'offer', 'accepted', 'rejected'];
        $a = $analytics ?? null;
    @endphp

    <a href="{{ route('govuk-alpha.jobs.mine', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.jobs_t3.nav_mine') }}</a>

    <span class="govuk-caption-xl">{{ $jobTitle }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.jobs_t3.applicants_title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.jobs_t3.applicants_description') }}</p>

    @if (($status ?? null) === 'status-updated')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="ap-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="ap-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.jobs_t3.states.status-updated') }}</p></div>
        </div>
    @elseif (in_array(($status ?? null), ['status-failed', 'status-safeguarding-restricted', 'status-safeguarding-unavailable', 'export-failed'], true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert"><h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li>{{ match ($status) {
                    'export-failed' => __('govuk_alpha.jobs_t3.states.export-failed'),
                    'status-safeguarding-restricted' => __('safeguarding.errors.interaction_not_allowed'),
                    'status-safeguarding-unavailable' => __('safeguarding.errors.policy_unavailable'),
                    default => __('govuk_alpha.jobs_t3.states.status-failed'),
                } }}</li></ul></div></div>
        </div>
    @endif

    @if (is_array($a))
        <h2 class="govuk-heading-m">{{ __('govuk_alpha.jobs_t3.analytics_heading') }}</h2>
        <dl class="govuk-summary-list govuk-!-margin-bottom-6">
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.jobs_t3.stat_views') }}</dt>
                <dd class="govuk-summary-list__value">{{ (int) ($a['total_views'] ?? 0) }}</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.jobs_t3.stat_unique') }}</dt>
                <dd class="govuk-summary-list__value">{{ (int) ($a['unique_viewers'] ?? 0) }}</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.jobs_t3.stat_applications') }}</dt>
                <dd class="govuk-summary-list__value">{{ (int) ($a['total_applications'] ?? 0) }}</dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.jobs_t3.stat_conversion') }}</dt>
                <dd class="govuk-summary-list__value">{{ (float) ($a['conversion_rate'] ?? 0) }}%</dd>
            </div>
        </dl>
    @endif

    <p class="govuk-!-margin-bottom-6">
        <a class="govuk-button govuk-button--secondary" data-module="govuk-button" href="{{ route('govuk-alpha.jobs.applicants.export', ['tenantSlug' => $tenantSlug, 'id' => $jobId]) }}">{{ __('govuk_alpha.jobs_t3.export_button') }}</a>
    </p>

    @if (empty($applications))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.jobs_t3.applicants_empty') }}</p></div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($applications as $app)
                @php
                    $appId = (int) ($app['id'] ?? 0);
                    $applicantName = trim((string) ($app['applicant']['name'] ?? '')) ?: __('govuk_alpha.jobs_t3.applicant_anonymous');
                    $appStatus = (string) ($app['stage'] ?? $app['status'] ?? 'applied');
                    $appliedOn = !empty($app['created_at']) ? \Illuminate\Support\Carbon::parse($app['created_at'])->translatedFormat('j F Y') : null;
                    $coverLetter = trim((string) ($app['message'] ?? ''));
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $applicantName }}</h2>
                        <strong class="govuk-tag govuk-tag--blue">{{ $statusLabel($appStatus) }}</strong>
                    </div>
                    @if ($appliedOn)
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.jobs_t2.applied_on', ['date' => $appliedOn]) }}</p>
                    @endif
                    @if ($coverLetter !== '')
                        <div class="govuk-body govuk-!-margin-bottom-2">{!! nl2br(e($coverLetter)) !!}</div>
                    @endif

                    <form method="post" action="{{ route('govuk-alpha.jobs.applicants.status', ['tenantSlug' => $tenantSlug, 'id' => $jobId, 'appId' => $appId]) }}" class="govuk-!-margin-top-2">
                        @csrf
                        <div class="govuk-form-group govuk-!-margin-bottom-2">
                            <label class="govuk-label" for="app-status-{{ $appId }}">{{ __('govuk_alpha.jobs_t3.status_change_label') }}</label>
                            <select class="govuk-select" id="app-status-{{ $appId }}" name="app_status">
                                @foreach ($stageOptions as $opt)
                                    <option value="{{ $opt }}" @selected($appStatus === $opt)>{{ $statusLabel($opt) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="govuk-form-group govuk-!-margin-bottom-2">
                            <label class="govuk-label" for="app-notes-{{ $appId }}">{{ __('govuk_alpha.jobs_t3.notes_label') }}</label>
                            <input class="govuk-input govuk-!-width-two-thirds" id="app-notes-{{ $appId }}" name="notes" type="text" maxlength="2000">
                        </div>
                        <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.jobs_t3.status_change_button') }}</button>
                    </form>
                </article>
            @endforeach
        </div>
    @endif
@endsection
