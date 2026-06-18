{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $org = isset($organisation) && is_array($organisation) ? $organisation : [];
        $orgId = (int) ($org['id'] ?? 0);
        $oName = trim((string) ($org['name'] ?? '')) ?: __('govuk_alpha_organisations.jobs.title');
        $jobs = isset($orgJobs) && is_array($orgJobs) ? $orgJobs : [];

        // Schema.org Organization JSON-LD (parity with the React detail page Helmet).
        // Built from already-escaped scalar fields; emitted via e()-escaped JSON.
        $oDesc = trim((string) ($org['description'] ?? ''));
        $oWebsite = trim((string) ($org['website'] ?? ''));
        $oEmail = trim((string) ($org['email'] ?? ($org['contact_email'] ?? '')));
        $oLogo = trim((string) ($org['logo_url'] ?? ''));
        $ld = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $oName,
            'description' => $oDesc !== '' ? mb_substr($oDesc, 0, 300) : null,
            'logo' => $oLogo !== '' ? $oLogo : null,
            'url' => $oWebsite !== '' ? $oWebsite : null,
            'email' => $oEmail !== '' ? $oEmail : null,
        ], static fn ($v) => $v !== null);

        $typeLabel = function (?string $type): string {
            return match ($type) {
                'paid' => __('govuk_alpha_organisations.jobs.type_paid'),
                'timebank' => __('govuk_alpha_organisations.jobs.type_timebank'),
                'volunteer' => __('govuk_alpha_organisations.jobs.type_volunteer'),
                default => (string) $type,
            };
        };
        $typeTag = fn (?string $type): string => match ($type) {
            'paid' => 'govuk-tag--green',
            'timebank' => 'govuk-tag--yellow',
            default => 'govuk-tag--blue',
        };
    @endphp

    @push('alpha_head')
        <script type="application/ld+json">{!! e(json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !!}</script>
    @endpush

    @if ($orgId > 0)
        <a class="govuk-back-link" href="{{ route('govuk-alpha.organisations.show', ['tenantSlug' => $tenantSlug, 'id' => $orgId]) }}">{{ $oName }}</a>
    @else
        <a class="govuk-back-link" href="{{ route('govuk-alpha.organisations.browse', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_organisations.common.back_to_organisations') }}</a>
    @endif

    <span class="govuk-caption-xl">{{ __('govuk_alpha_organisations.common.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_organisations.jobs.heading', ['name' => $oName]) }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_organisations.jobs.description') }}</p>

    @if (empty($jobs))
        <div class="govuk-inset-text">{{ __('govuk_alpha_organisations.jobs.empty') }}</div>
    @else
        <p class="govuk-body">{{ trans_choice('govuk_alpha_organisations.jobs.count', count($jobs), ['count' => count($jobs)]) }}</p>
        <div class="nexus-alpha-card-list">
            @foreach ($jobs as $job)
                @php
                    $jobId = (int) ($job['id'] ?? 0);
                    $jobTitle = trim((string) ($job['title'] ?? '')) ?: __('govuk_alpha_organisations.jobs.title');
                    $jobType = (string) ($job['type'] ?? '');
                    $jobRemote = ! empty($job['is_remote']);
                    $jobLocation = trim((string) ($job['location'] ?? ''));
                    $jobDeadline = $job['deadline'] ?? null;
                @endphp
                @if ($jobId > 0)
                    <article class="nexus-alpha-card">
                        <h2 class="govuk-heading-m govuk-!-margin-bottom-2">
                            <a class="govuk-link" href="{{ route('govuk-alpha.jobs.show', ['tenantSlug' => $tenantSlug, 'id' => $jobId]) }}">{{ $jobTitle }}</a>
                        </h2>
                        <dl class="nexus-alpha-inline-list nexus-alpha-meta">
                            @if ($jobType !== '')
                                <div>
                                    <dt class="govuk-visually-hidden">{{ __('govuk_alpha_organisations.jobs.term_type') }}</dt>
                                    <dd><strong class="govuk-tag {{ $typeTag($jobType) }}">{{ $typeLabel($jobType) }}</strong></dd>
                                </div>
                            @endif
                            @if ($jobRemote)
                                <div>
                                    <dt class="govuk-visually-hidden">{{ __('govuk_alpha_organisations.jobs.term_location') }}</dt>
                                    <dd>{{ __('govuk_alpha_organisations.jobs.remote') }}</dd>
                                </div>
                            @elseif ($jobLocation !== '')
                                <div>
                                    <dt class="govuk-visually-hidden">{{ __('govuk_alpha_organisations.jobs.term_location') }}</dt>
                                    <dd>{{ $jobLocation }}</dd>
                                </div>
                            @endif
                            @if (!empty($jobDeadline))
                                <div>
                                    <dt class="govuk-visually-hidden">{{ __('govuk_alpha_organisations.jobs.term_deadline') }}</dt>
                                    <dd>{{ __('govuk_alpha_organisations.jobs.closes', ['date' => \Illuminate\Support\Carbon::parse($jobDeadline)->isoFormat('LL')]) }}</dd>
                                </div>
                            @endif
                        </dl>
                        <div class="nexus-alpha-actions">
                            <a class="govuk-link" href="{{ route('govuk-alpha.jobs.show', ['tenantSlug' => $tenantSlug, 'id' => $jobId]) }}">{{ __('govuk_alpha_organisations.jobs.view_job') }}</a>
                        </div>
                    </article>
                @endif
            @endforeach
        </div>
    @endif
@endsection
