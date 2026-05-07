{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $formatDate = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y, g:ia') : null;
    @endphp

    <span class="govuk-caption-l">{{ __('govuk_alpha.nav.messages') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.messages.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.messages.description') }}</p>

    @if ($status === 'conversation-archived' || $status === 'conversation-restored')
        <div class="govuk-notification-banner govuk-notification-banner--success" role="region" aria-labelledby="messages-status-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="messages-status-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ $status === 'conversation-archived' ? __('govuk_alpha.messages.conversation_archived') : __('govuk_alpha.messages.conversation_restored') }}</p>
            </div>
        </div>
    @endif

    @if (!$directMessagingEnabled)
        <div class="govuk-notification-banner" role="region" aria-labelledby="messages-disabled-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="messages-disabled-title">{{ __('govuk_alpha.messages.disabled_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-body">{{ __('govuk_alpha.messages.disabled_detail') }}</p>
            </div>
        </div>
    @elseif (!empty($restriction['messaging_disabled']))
        <div class="govuk-notification-banner" role="region" aria-labelledby="messages-restricted-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="messages-restricted-title">{{ __('govuk_alpha.messages.disabled_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-body">{{ __('govuk_alpha.messages.restricted_detail') }}</p>
            </div>
        </div>
    @endif

    <div class="govuk-tabs">
        <h2 class="govuk-tabs__title">{{ __('govuk_alpha.messages.tabs_title') }}</h2>
        <ul class="govuk-tabs__list">
            <li class="govuk-tabs__list-item{{ !$showArchived ? ' govuk-tabs__list-item--selected' : '' }}">
                <a class="govuk-tabs__tab" href="{{ route('govuk-alpha.messages.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.messages.inbox') }}</a>
            </li>
            <li class="govuk-tabs__list-item{{ $showArchived ? ' govuk-tabs__list-item--selected' : '' }}">
                <a class="govuk-tabs__tab" href="{{ route('govuk-alpha.messages.index', ['tenantSlug' => $tenantSlug, 'archived' => 1]) }}">{{ __('govuk_alpha.messages.archived') }}</a>
            </li>
        </ul>
    </div>

    @if (empty($items))
        <div class="govuk-inset-text">
            <h2 class="govuk-heading-m">{{ __('govuk_alpha.states.empty_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha.messages.empty') }}</p>
        </div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($items as $conversation)
                @php
                    $otherUser = $conversation['other_user'] ?? [];
                    $name = $otherUser['name'] ?? trim(($otherUser['first_name'] ?? '') . ' ' . ($otherUser['last_name'] ?? '')) ?: __('govuk_alpha.members.unknown_member');
                    $lastMessage = $conversation['last_message'] ?? [];
                    $senderLabel = (int) ($lastMessage['sender_id'] ?? 0) === $currentUserId ? __('govuk_alpha.messages.sent_by_you') : $name;
                    $created = $formatDate($lastMessage['created_at'] ?? $conversation['created_at'] ?? null);
                    $unreadCount = (int) ($conversation['unread_count'] ?? 0);
                @endphp
                <article class="nexus-alpha-card">
                    <h2 class="govuk-heading-m govuk-!-margin-bottom-2">
                        <a class="govuk-link" href="{{ route('govuk-alpha.messages.show', ['tenantSlug' => $tenantSlug, 'userId' => $conversation['id']]) }}">{{ __('govuk_alpha.messages.conversation_title', ['name' => $name]) }}</a>
                    </h2>
                    @if ($unreadCount > 0)
                        <strong class="govuk-tag govuk-tag--blue">{{ trans_choice('govuk_alpha.messages.unread_count', $unreadCount, ['count' => $unreadCount]) }}</strong>
                    @endif
                    <p class="govuk-body govuk-!-margin-top-3">
                        <strong>{{ __('govuk_alpha.messages.last_message_label') }}:</strong>
                        {{ $senderLabel }}:
                        {{ \Illuminate\Support\Str::limit((string) ($lastMessage['body'] ?? ''), 180) }}
                    </p>
                    @if ($created)
                        <p class="govuk-hint">{{ __('govuk_alpha.messages.sent_label') }} {{ $created }}</p>
                    @endif
                    @if ($showArchived)
                        <form method="post" action="{{ route('govuk-alpha.messages.restore', ['tenantSlug' => $tenantSlug, 'userId' => $conversation['id']]) }}">
                            @csrf
                            <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.actions.restore_conversation') }}</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
        @if (!empty($meta['has_more']))
            <nav class="govuk-pagination govuk-pagination--block govuk-!-margin-top-6" aria-label="{{ __('govuk_alpha.messages.pagination_label') }}">
                <div class="govuk-pagination__next">
                    <a class="govuk-link govuk-pagination__link" href="{{ route('govuk-alpha.messages.index', ['tenantSlug' => $tenantSlug, 'archived' => $showArchived ? 1 : null, 'cursor' => $meta['cursor']]) }}" rel="next">
                        <svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                            <path d="m8.107-0.0078125-1.4136 1.414 4.2926 4.293h-12.986v2h12.896l-4.1855 3.9766 1.377 1.4492 6.7441-6.4062-6.7246-6.7266z"></path>
                        </svg>
                        <span class="govuk-pagination__link-title">{{ __('govuk_alpha.actions.load_more') }}</span>
                        <span class="govuk-visually-hidden">:</span>
                        <span class="govuk-pagination__link-label">{{ __('govuk_alpha.messages.more_results_label') }}</span>
                    </a>
                </div>
            </nav>
        @endif
    @endif
@endsection
