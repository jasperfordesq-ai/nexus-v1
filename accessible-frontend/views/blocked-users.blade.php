{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <a class="govuk-back-link" href="{{ route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.blocked_users.back') }}</a>

            @if ($status === 'member-unblocked')
                <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="blocked-status-title">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="blocked-status-title">{{ __('govuk_alpha.states.success_title') }}</h2>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.blocked_users.unblocked') }}</p>
                    </div>
                </div>
            @endif

            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.blocked_users.title') }}</h1>
            <p class="govuk-body">{{ __('govuk_alpha.blocked_users.description') }}</p>

            @if (empty($blocked))
                <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.blocked_users.empty') }}</p></div>
            @else
                <ul class="govuk-list nexus-alpha-card-list">
                    @foreach ($blocked as $b)
                        @php
                            $bName = trim((string) ($b['name'] ?? '')) !== '' ? $b['name'] : __('govuk_alpha.members.unknown_member');
                            $bId = (int) ($b['user_id'] ?? 0);
                        @endphp
                        <li class="nexus-alpha-card">
                            <div class="nexus-alpha-card-head">
                                @if (!empty($b['avatar_url']))
                                    <img class="nexus-alpha-avatar" src="{{ $b['avatar_url'] }}" alt="" loading="lazy" decoding="async" width="48" height="48">
                                @else
                                    <span class="nexus-alpha-avatar nexus-alpha-avatar--placeholder" aria-hidden="true">{{ mb_strtoupper(mb_substr($bName, 0, 1)) }}</span>
                                @endif
                                <p class="govuk-body govuk-!-font-weight-bold govuk-!-margin-bottom-0">{{ $bName }}</p>
                            </div>
                            @if (trim((string) ($b['reason'] ?? '')) !== '')
                                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">{{ $b['reason'] }}</p>
                            @endif
                            @if ($bId > 0)
                                <form method="post" action="{{ route('govuk-alpha.members.unblock', ['tenantSlug' => $tenantSlug, 'id' => $bId]) }}">
                                    @csrf
                                    <input type="hidden" name="from" value="list">
                                    <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.blocked_users.unblock') }}</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endsection
