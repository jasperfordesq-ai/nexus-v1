{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $gName = trim((string) ($group['name'] ?? '')) ?: __('govuk_alpha.groups.title');
        $gPrivate = ($group['visibility'] ?? 'public') !== 'public';
        $gCount = (int) ($group['member_count'] ?? count($groupMembers));
    @endphp

    @if (in_array($status, ['group-joined', 'group-left'], true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-live="polite" aria-labelledby="grp-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="grp-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.groups.states.' . $status) }}</p></div>
        </div>
    @elseif ($status === 'group-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert"><h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p class="govuk-body">{{ __('govuk_alpha.groups.states.group-failed') }}</p></div></div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ __('govuk_alpha.groups.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <div class="nexus-alpha-module-row">
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">{{ $gName }}</h1>
        <strong class="govuk-tag {{ $gPrivate ? 'govuk-tag--grey' : 'govuk-tag--green' }}">{{ $gPrivate ? __('govuk_alpha.groups.visibility_private') : __('govuk_alpha.groups.visibility_public') }}</strong>
    </div>
    <p class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha.groups.members_count', ['count' => $gCount]) }}</p>

    @if (trim((string) ($group['description'] ?? '')) !== '')
        <p class="govuk-body-l">{{ $group['description'] }}</p>
    @endif

    <div class="govuk-!-margin-bottom-6">
        @if ($isMember)
            <p class="govuk-inset-text">{{ __('govuk_alpha.groups.you_are_member') }}</p>
            <form method="post" action="{{ route('govuk-alpha.groups.leave', ['tenantSlug' => $tenantSlug, 'id' => $group['id']]) }}">
                @csrf
                <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.groups.leave') }}</button>
            </form>
        @else
            <form method="post" action="{{ route('govuk-alpha.groups.join', ['tenantSlug' => $tenantSlug, 'id' => $group['id']]) }}">
                @csrf
                <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.groups.join') }}</button>
            </form>
        @endif
    </div>

    @if (!empty($groupMembers))
        <h2 class="govuk-heading-l">{{ __('govuk_alpha.groups.members_title') }}</h2>
        <ul class="govuk-list">
            @foreach ($groupMembers as $m)
                @php
                    $mName = trim((string) ($m['name'] ?? '')) ?: trim((string) ($m['first_name'] ?? '') . ' ' . (string) ($m['last_name'] ?? ''));
                    $mId = (int) ($m['user_id'] ?? ($m['id'] ?? 0));
                @endphp
                @if ($mName !== '')
                    <li>@if ($mId > 0)<a class="govuk-link" href="{{ route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $mId]) }}">{{ $mName }}</a>@else{{ $mName }}@endif</li>
                @endif
            @endforeach
        </ul>
    @endif
@endsection
