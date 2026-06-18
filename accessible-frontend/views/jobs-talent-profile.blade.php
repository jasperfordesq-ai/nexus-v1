{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $c = $candidate ?? [];
        $cName = trim((string) ($c['name'] ?? '')) ?: __('govuk_alpha_jobs.shared.anonymous');
        $cHeadline = trim((string) ($c['headline'] ?? ''));
        $cLocation = trim((string) ($c['location'] ?? ''));
        $cAvatar = trim((string) ($c['avatar_url'] ?? ''));
        $cSummary = trim((string) ($c['summary'] ?? ''));
        $cBio = trim((string) ($c['bio'] ?? ''));
        $cSkills = is_array($c['skills'] ?? null) ? array_filter($c['skills']) : [];
        $cActive = !empty($c['last_active']) ? \Illuminate\Support\Carbon::parse($c['last_active'])->translatedFormat('j F Y') : null;
        $cSince = !empty($c['member_since']) ? \Illuminate\Support\Carbon::parse($c['member_since'])->translatedFormat('F Y') : null;
    @endphp

    <a href="{{ route('govuk-alpha.jobs.talent', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha_jobs.talent.title') }}</a>

    <div class="nexus-alpha-module-row govuk-!-margin-bottom-2">
        @if ($cAvatar !== '')
            <img class="nexus-alpha-avatar" src="{{ $cAvatar }}" alt="" aria-hidden="true" loading="lazy" decoding="async" width="64" height="64">
        @else
            <span class="nexus-alpha-avatar nexus-alpha-avatar--placeholder" aria-hidden="true">{{ mb_strtoupper(mb_substr($cName, 0, 1)) }}</span>
        @endif
        <div>
            <h1 class="govuk-heading-xl govuk-!-margin-bottom-1">{{ $cName }}</h1>
            <p class="govuk-body-l govuk-!-margin-bottom-0">{{ $cHeadline !== '' ? $cHeadline : __('govuk_alpha_jobs.talent.headline_none') }}</p>
        </div>
    </div>

    <p class="govuk-body-s nexus-alpha-meta">
        @if ($cLocation !== ''){{ $cLocation }}@endif
        @if ($cActive !== null) &middot; {{ __('govuk_alpha_jobs.talent.last_active', ['date' => $cActive]) }}@endif
        @if ($cSince !== null) &middot; {{ __('govuk_alpha_jobs.talent.member_since', ['date' => $cSince]) }}@endif
    </p>

    @if (!empty($cSkills))
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_jobs.talent.skills_heading') }}</h2>
        <ul class="govuk-list nexus-alpha-tag-list govuk-!-margin-bottom-6">
            @foreach ($cSkills as $sk)
                <li><strong class="govuk-tag govuk-tag--grey">{{ $sk }}</strong></li>
            @endforeach
        </ul>
    @else
        <p class="govuk-body">{{ __('govuk_alpha_jobs.talent.no_skills') }}</p>
    @endif

    @if ($cSummary !== '')
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_jobs.talent.summary_heading') }}</h2>
        <div class="govuk-body govuk-!-margin-bottom-6">{!! nl2br(e($cSummary)) !!}</div>
    @endif

    @if ($cBio !== '')
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_jobs.talent.about_heading') }}</h2>
        <div class="govuk-body govuk-!-margin-bottom-6">{!! nl2br(e($cBio)) !!}</div>
    @endif
@endsection
