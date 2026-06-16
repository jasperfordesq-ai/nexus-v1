{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $gId = (int) ($group['id'] ?? 0);
        $gName = trim((string) ($group['name'] ?? ''));
        $successStates = ['member-promoted', 'member-demoted', 'member-removed', 'request-approved', 'request-rejected'];
        $failStates = ['member-failed', 'request-failed'];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.groups.show', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}">{{ __('govuk_alpha.groups.back_to_group') }}</a>

    <span class="govuk-caption-l">{{ $gName }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.groups.manage.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.groups.manage.description') }}</p>

    @if (in_array($status, $successStates, true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-live="polite" aria-labelledby="manage-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="manage-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.groups.states.' . $status) }}</p></div>
        </div>
    @elseif (in_array($status, $failStates, true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert"><h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p class="govuk-body">{{ __('govuk_alpha.groups.states.' . $status) }}</p></div></div>
        </div>
    @endif

    @if ($isPrivate)
        <h2 class="govuk-heading-l">{{ __('govuk_alpha.groups.manage.requests_title') }}</h2>
        @if (empty($pendingRequests))
            <p class="govuk-inset-text">{{ __('govuk_alpha.groups.manage.requests_empty') }}</p>
        @else
            <p class="govuk-body">{{ __('govuk_alpha.groups.manage.requests_description') }}</p>
            <div class="nexus-alpha-card-list">
                @foreach ($pendingRequests as $r)
                    @php $rName = trim((string) ($r['name'] ?? '')); $rId = (int) ($r['id'] ?? 0); @endphp
                    @if ($rId > 0)
                        <article class="nexus-alpha-card">
                            <div class="nexus-alpha-module-row">
                                <h3 class="govuk-heading-s govuk-!-margin-bottom-0">{{ $rName !== '' ? $rName : '#' . $rId }}</h3>
                            </div>
                            <div class="govuk-button-group govuk-!-margin-top-2">
                                <form method="post" action="{{ route('govuk-alpha.groups.requests.handle', ['tenantSlug' => $tenantSlug, 'id' => $gId, 'requesterId' => $rId]) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="accept">
                                    <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.groups.manage.approve') }}</button>
                                </form>
                                <form method="post" action="{{ route('govuk-alpha.groups.requests.handle', ['tenantSlug' => $tenantSlug, 'id' => $gId, 'requesterId' => $rId]) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="reject">
                                    <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.groups.manage.reject') }}</button>
                                </form>
                            </div>
                        </article>
                    @endif
                @endforeach
            </div>
        @endif
    @endif

    <h2 class="govuk-heading-l">{{ __('govuk_alpha.groups.members_title') }}</h2>
    @php
        $others = collect($groupMembers)->filter(fn ($m) => (int) ($m['id'] ?? ($m['user_id'] ?? 0)) !== (int) $currentUserId)->values();
    @endphp
    @if ($others->isEmpty())
        <p class="govuk-inset-text">{{ __('govuk_alpha.groups.manage.members_empty') }}</p>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($others as $m)
                @php
                    $mId = (int) ($m['id'] ?? ($m['user_id'] ?? 0));
                    $mName = trim((string) ($m['name'] ?? ''));
                    $mRole = (string) ($m['role'] ?? 'member');
                    $isOwner = $mId === (int) $ownerId;
                    $roleKey = $mRole === 'owner' ? 'role_owner' : ($mRole === 'admin' ? 'role_admin' : 'role_member');
                @endphp
                @if ($mId > 0)
                    <article class="nexus-alpha-card">
                        <div class="nexus-alpha-module-row">
                            <h3 class="govuk-heading-s govuk-!-margin-bottom-1">
                                <a class="govuk-link" href="{{ route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $mId]) }}">{{ $mName !== '' ? $mName : '#' . $mId }}</a>
                            </h3>
                            <strong class="govuk-tag {{ $isOwner || $mRole === 'admin' ? 'govuk-tag--blue' : 'govuk-tag--grey' }}">{{ __('govuk_alpha.groups.manage.' . $roleKey) }}</strong>
                        </div>
                        @unless ($isOwner)
                            <div class="govuk-button-group govuk-!-margin-top-2">
                                @if ($mRole === 'admin')
                                    <form method="post" action="{{ route('govuk-alpha.groups.members.update', ['tenantSlug' => $tenantSlug, 'id' => $gId, 'memberId' => $mId]) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="demote">
                                        <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.groups.manage.demote') }}</button>
                                    </form>
                                @else
                                    <form method="post" action="{{ route('govuk-alpha.groups.members.update', ['tenantSlug' => $tenantSlug, 'id' => $gId, 'memberId' => $mId]) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="promote">
                                        <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.groups.manage.promote') }}</button>
                                    </form>
                                @endif
                                <form method="post" action="{{ route('govuk-alpha.groups.members.update', ['tenantSlug' => $tenantSlug, 'id' => $gId, 'memberId' => $mId]) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="remove">
                                    <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.groups.manage.remove') }}</button>
                                </form>
                            </div>
                        @endunless
                    </article>
                @endif
            @endforeach
        </div>
    @endif
@endsection
