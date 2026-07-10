{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $jobId = (int) ($job['id'] ?? 0);
        $jobTitle = trim((string) ($job['title'] ?? '')) ?: __('govuk_alpha.jobs.title');
        $columns = $pipeline ?? [];
        $stageOptions = ['applied', 'screening', 'interview', 'offer', 'accepted', 'rejected'];

        $stageLabel = function (string $s): string {
            $key = 'govuk_alpha_jobs.stage.' . $s;
            $label = __($key);
            return $label === $key ? ucfirst(str_replace('_', ' ', $s)) : $label;
        };
        $columnLabel = function (string $c): string {
            $key = 'govuk_alpha_jobs.pipeline.column_' . $c;
            $label = __($key);
            return $label === $key ? ucfirst($c) : $label;
        };
    @endphp

    <a href="{{ route('govuk-alpha.jobs.applicants', ['tenantSlug' => $tenantSlug, 'id' => $jobId]) }}" class="govuk-back-link">{{ __('govuk_alpha_jobs.shared.back_to_my_postings') }}</a>

    <span class="govuk-caption-xl">{{ $jobTitle }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_jobs.pipeline.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_jobs.pipeline.description') }}</p>

    @if (($status ?? null) === 'status-updated')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="pl-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="pl-status">{{ __('govuk_alpha_jobs.pipeline.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_jobs.pipeline.status_moved') }}</p></div>
        </div>
    @elseif (($status ?? null) === 'status-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_jobs.pipeline.error_title') }}</h2>
                <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li>{{ __('govuk_alpha_jobs.pipeline.status_failed') }}</li></ul></div>
            </div>
        </div>
    @endif

    <p class="govuk-!-margin-bottom-6">
        <a class="govuk-link" href="{{ route('govuk-alpha.jobs.applicants', ['tenantSlug' => $tenantSlug, 'id' => $jobId]) }}">{{ __('govuk_alpha_jobs.pipeline.view_full_list') }}</a>
    </p>

    @php
        $anyApplicants = false;
        foreach ($columns as $apps) { if (!empty($apps)) { $anyApplicants = true; break; } }
    @endphp

    @unless ($anyApplicants)
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_jobs.pipeline.empty') }}</p></div>
    @endunless

    {{-- Always render the stage columns (mirrors the React kanban board), so the
         pipeline structure — and each stage heading — is visible even before any
         candidate has applied. --}}
        @foreach ($columns as $colKey => $apps)
            <section class="govuk-!-margin-bottom-6" aria-labelledby="pl-col-{{ $colKey }}">
                <h2 class="govuk-heading-m govuk-!-margin-bottom-2" id="pl-col-{{ $colKey }}">
                    {{ $columnLabel((string) $colKey) }}
                    <span class="govuk-caption-m">{{ trans_choice('govuk_alpha_jobs.pipeline.count_in_stage', count($apps), ['count' => count($apps)]) }}</span>
                </h2>

                @if (empty($apps))
                    <p class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha_jobs.shared.none_yet') }}</p>
                @else
                    <div class="nexus-alpha-card-list">
                        @foreach ($apps as $app)
                            @php
                                $appId = (int) ($app['id'] ?? 0);
                                $applicantName = trim((string) ($app['applicant']['name'] ?? '')) ?: __('govuk_alpha_jobs.shared.anonymous');
                                $appStage = (string) ($app['stage'] ?? $app['status'] ?? 'applied');
                                $appliedOn = !empty($app['created_at']) ? \Illuminate\Support\Carbon::parse($app['created_at'])->translatedFormat('j F Y') : null;
                                $cvName = trim((string) ($app['cv_filename'] ?? ''));
                            @endphp
                            <article class="nexus-alpha-card">
                                <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $applicantName }}</h3>
                                @if ($appliedOn)
                                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha_jobs.pipeline.applied_on', ['date' => $appliedOn]) }}</p>
                                @endif
                                @if ($cvName !== '' && $appId > 0)
                                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">
                                        <a class="govuk-link" href="{{ route('govuk-alpha.jobs.applications.cv', ['tenantSlug' => $tenantSlug, 'applicationId' => $appId]) }}">{{ __('govuk_alpha_jobs.pipeline.download_cv') }}</a>: {{ $cvName }}
                                    </p>
                                @endif

                                <form method="post" action="{{ route('govuk-alpha.jobs.applicants.status', ['tenantSlug' => $tenantSlug, 'id' => $jobId, 'appId' => $appId]) }}" class="govuk-!-margin-top-2">
                                    @csrf
                                    <div class="govuk-form-group govuk-!-margin-bottom-2">
                                        <label class="govuk-label govuk-label--s" for="pl-status-{{ $appId }}">{{ __('govuk_alpha_jobs.pipeline.move_to_label') }}</label>
                                        <select class="govuk-select" id="pl-status-{{ $appId }}" name="app_status">
                                            @foreach ($stageOptions as $opt)
                                                <option value="{{ $opt }}" @selected($appStage === $opt)>{{ $stageLabel($opt) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_jobs.pipeline.move_button') }}</button>
                                </form>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
@endsection
