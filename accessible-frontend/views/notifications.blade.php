{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $unreadTotal = (int) ($notificationCounts['total'] ?? 0);
        $dateFmt = fn ($v): ?string => $v ? \Illuminate\Support\Carbon::parse($v)->diffForHumans() : null;
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha.notifications.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.notifications.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.notifications.description') }}</p>

    @if (in_array($status, ['marked-read', 'notification-deleted'], true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-live="polite" aria-labelledby="notif-status">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="notif-status">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.notifications.states.' . $status) }}</p>
            </div>
        </div>
    @endif

    <div class="nexus-alpha-actions govuk-!-margin-bottom-4">
        <a class="govuk-link govuk-link--no-visited-state @if (!$notificationsUnreadOnly) govuk-!-font-weight-bold @endif" href="{{ route('govuk-alpha.notifications.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.notifications.all_filter') }}</a>
        <a class="govuk-link govuk-link--no-visited-state @if ($notificationsUnreadOnly) govuk-!-font-weight-bold @endif" href="{{ route('govuk-alpha.notifications.index', ['tenantSlug' => $tenantSlug, 'filter' => 'unread']) }}">{{ __('govuk_alpha.notifications.unread_filter') }}@if ($unreadTotal > 0) ({{ $unreadTotal }})@endif</a>
        @if ($unreadTotal > 0)
            <form method="post" action="{{ route('govuk-alpha.notifications.read-all', ['tenantSlug' => $tenantSlug]) }}" class="nexus-alpha-linkform">
                @csrf
                <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.notifications.mark_all_read') }}</button>
            </form>
        @endif
    </div>

    @if (empty($notifications))
        <p class="govuk-inset-text">{{ __('govuk_alpha.notifications.empty') }}</p>
    @else
        @foreach ($notifications as $n)
            @php
                $nId = (int) ($n['id'] ?? 0);
                $nText = trim((string) ($n['message'] ?? ($n['title'] ?? '')));
                $nUnread = !($n['is_read'] ?? false);
                $nWhen = $dateFmt($n['created_at'] ?? null);
            @endphp
            <div class="nexus-alpha-card govuk-!-margin-bottom-3">
                <div class="nexus-alpha-module-row">
                    <p class="govuk-body govuk-!-margin-bottom-1">
                        @if ($nUnread)<strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha.notifications.new_tag') }}</strong> @endif{{ $nText }}
                    </p>
                    <form method="post" action="{{ route('govuk-alpha.notifications.delete', ['tenantSlug' => $tenantSlug, 'id' => $nId]) }}" class="nexus-alpha-linkform">
                        @csrf
                        <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.notifications.delete') }}</button>
                    </form>
                </div>
                @if ($nWhen)
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">{{ $nWhen }}</p>
                @endif
            </div>
        @endforeach

        @if ($notificationsHasMore && $notificationsCursor)
            <p class="govuk-body govuk-!-margin-top-4">
                <a class="govuk-link" href="{{ route('govuk-alpha.notifications.index', array_filter(['tenantSlug' => $tenantSlug, 'filter' => $notificationsUnreadOnly ? 'unread' : null, 'cursor' => $notificationsCursor])) }}">{{ __('govuk_alpha.notifications.load_more') }}</a>
            </p>
        @endif
    @endif
@endsection
