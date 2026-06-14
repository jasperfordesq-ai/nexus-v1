{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $asUrl = fn (string $p): string => $p === '' ? '' : (\Illuminate\Support\Str::startsWith($p, ['http://', 'https://', '/']) ? $p : '/' . ltrim($p, '/'));
        $mName = trim((string) ($member['name'] ?? '')) ?: __('govuk_alpha.federation.member.caption');
        $avatar = $asUrl(trim((string) ($member['avatar'] ?? '')));
        $loc = trim((string) ($member['location'] ?? ''));
        $skills = (array) ($member['skills'] ?? []);
    @endphp

    <a href="{{ route('govuk-alpha.federation.members.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.federation.member.back') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha.federation.member.caption') }}</span>
    <div class="nexus-alpha-module-row">
        @if ($avatar !== '')
            <img class="nexus-alpha-card-thumb" src="{{ $avatar }}" alt="{{ $mName }}" width="80" height="80" loading="lazy" decoding="async">
        @endif
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">{{ $mName }}</h1>
    </div>

    <p class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha.federation.member.community_label') }}: {{ $member['tenant_name'] ?? '' }}</p>

    @if (trim((string) ($member['bio'] ?? '')) !== '')
        <h2 class="govuk-heading-l">{{ __('govuk_alpha.federation.member.about_label') }}</h2>
        <div class="govuk-body">{!! nl2br(e($member['bio'])) !!}</div>
    @endif

    <dl class="govuk-summary-list">
        @if ($loc !== '')
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.federation.member.location_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $loc }}</dd>
            </div>
        @endif
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.federation.member.skills_label') }}</dt>
            <dd class="govuk-summary-list__value">
                @if (empty($skills))
                    {{ __('govuk_alpha.federation.member.no_skills') }}
                @else
                    @foreach ($skills as $skill)
                        <strong class="govuk-tag govuk-tag--grey govuk-!-margin-right-1">{{ $skill }}</strong>
                    @endforeach
                @endif
            </dd>
        </div>
    </dl>
@endsection
