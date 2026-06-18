{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $formatDate = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y, g:ia') : null;
        $canStart = $directMessagingEnabled
            && empty($restriction['messaging_disabled'])
            && \App\Core\TenantContext::hasFeature('connections');
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.messages.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_messages.groups.back_to_messages') }}</a>

    @include('accessible-frontend::partials.messages-status', ['status' => $status ?? null])

    <span class="govuk-caption-l">{{ __('govuk_alpha_messages.groups.caption') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_messages.groups.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_messages.groups.description') }}</p>

    @include('accessible-frontend::partials.messages-subnav', ['tenantSlug' => $tenantSlug, 'messagesActive' => 'groups'])

    @if (!$directMessagingEnabled)
        <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="groups-disabled-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="groups-disabled-title">{{ __('govuk_alpha.messages.disabled_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-body">{{ __('govuk_alpha.messages.disabled_detail') }}</p>
            </div>
        </div>
    @elseif (!empty($restriction['messaging_disabled']))
        <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="groups-restricted-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="groups-restricted-title">{{ __('govuk_alpha.messages.disabled_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-body">{{ __('govuk_alpha.messages.restricted_detail') }}</p>
            </div>
        </div>
    @endif

    @if ($canStart)
        <p class="govuk-body">
            <a class="govuk-button" data-module="govuk-button" href="{{ route('govuk-alpha.messages.groups.create', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_messages.groups.new_button') }}</a>
        </p>
    @endif

    @if (!empty($error))
        <div class="govuk-inset-text"><p class="govuk-body">{{ $error }}</p></div>
    @elseif (empty($groups))
        <div class="govuk-inset-text">
            <h2 class="govuk-heading-m">{{ __('govuk_alpha_messages.groups.empty_title') }}</h2>
            <p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha_messages.groups.empty_body') }}</p>
        </div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($groups as $group)
                @php
                    $groupId = (int) ($group['id'] ?? 0);
                    $groupName = trim((string) ($group['group_name'] ?? '')) !== '' ? (string) $group['group_name'] : __('govuk_alpha_messages.groups.untitled');
                    $participantCount = (int) ($group['participant_count'] ?? 0);
                    $lastMessage = $group['last_message'] ?? null;
                    $unread = (int) ($group['unread_count'] ?? 0);
                    $lastAt = $formatDate($lastMessage['created_at'] ?? $group['updated_at'] ?? null);
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-card-head">
                        @if (!empty($group['group_avatar_url']))
                            <img class="nexus-alpha-avatar" src="{{ $group['group_avatar_url'] }}" alt="" loading="lazy" decoding="async" width="48" height="48">
                        @else
                            <span class="nexus-alpha-avatar nexus-alpha-avatar--placeholder" aria-hidden="true">{{ mb_strtoupper(mb_substr($groupName, 0, 1)) }}</span>
                        @endif
                        <h2 class="govuk-heading-m govuk-!-margin-bottom-0">
                            <a class="govuk-link" href="{{ route('govuk-alpha.messages.groups.show', ['tenantSlug' => $tenantSlug, 'conversationId' => $groupId]) }}">{{ $groupName }}</a>
                        </h2>
                    </div>
                    <p class="govuk-hint govuk-!-margin-top-2 govuk-!-margin-bottom-2">{{ trans_choice('govuk_alpha_messages.groups.members_count', $participantCount, ['count' => $participantCount]) }}</p>
                    @if ($unread > 0)
                        <strong class="govuk-tag govuk-tag--blue">{{ trans_choice('govuk_alpha_messages.groups.unread_count', $unread, ['count' => $unread]) }}</strong>
                    @endif
                    @if ($lastMessage && trim((string) ($lastMessage['body'] ?? '')) !== '')
                        <p class="govuk-body govuk-!-margin-top-3">
                            <strong>{{ __('govuk_alpha_messages.groups.last_message_label') }}:</strong>
                            {{ trim((string) ($lastMessage['sender_name'] ?? '')) }}@if (trim((string) ($lastMessage['sender_name'] ?? '')) !== ''):@endif
                            {{ \Illuminate\Support\Str::limit((string) ($lastMessage['body'] ?? ''), 180) }}
                        </p>
                    @else
                        <p class="govuk-hint govuk-!-margin-top-3">{{ __('govuk_alpha_messages.groups.no_messages_yet') }}</p>
                    @endif
                    @if ($lastAt)
                        <p class="govuk-hint">{{ __('govuk_alpha_messages.conversation.sent_label') }} {{ $lastAt }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
