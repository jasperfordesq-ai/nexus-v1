{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $otherUser = $conversation['other_user'] ?? [];
        $otherName = $otherUser['name'] ?? trim(($otherUser['first_name'] ?? '') . ' ' . ($otherUser['last_name'] ?? '')) ?: __('govuk_alpha.members.unknown_member');
        $formatDate = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y, g:ia') : null;
        $canSend = $directMessagingEnabled && empty($restriction['messaging_disabled']);
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.messages.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_messages') }}</a>

    @if ($status === 'message-sent')
        <div class="govuk-notification-banner govuk-notification-banner--success" role="region" aria-labelledby="message-success-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="message-success-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.messages.sent') }}</p>
            </div>
        </div>
    @elseif (in_array($status, ['message-failed', 'message-empty', 'message-disabled'], true))
        <div class="govuk-error-summary" data-module="govuk-error-summary">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p>
                        @if ($status === 'message-empty')
                            {{ __('govuk_alpha.messages.empty_message') }}
                        @elseif ($status === 'message-disabled')
                            {{ __('govuk_alpha.messages.disabled_detail') }}
                        @else
                            {{ __('govuk_alpha.messages.failed') }}
                        @endif
                    </p>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ __('govuk_alpha.messages.title') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.messages.conversation_title', ['name' => $otherName]) }}</h1>

    @if ($listing)
        <div class="govuk-inset-text">
            <a class="govuk-link" href="{{ route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $listing['id']]) }}">{{ __('govuk_alpha.messages.listing_context', ['title' => $listing['title']]) }}</a>
        </div>
    @endif

    @if (!$directMessagingEnabled)
        <div class="govuk-notification-banner" role="region" aria-labelledby="conversation-disabled-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="conversation-disabled-title">{{ __('govuk_alpha.messages.disabled_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-body">{{ __('govuk_alpha.messages.disabled_detail') }}</p>
            </div>
        </div>
    @elseif (!empty($restriction['messaging_disabled']))
        <div class="govuk-notification-banner" role="region" aria-labelledby="conversation-restricted-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="conversation-restricted-title">{{ __('govuk_alpha.messages.disabled_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-body">{{ __('govuk_alpha.messages.restricted_detail') }}</p>
            </div>
        </div>
    @endif

    @if (empty($messages))
        <div class="govuk-inset-text">{{ __('govuk_alpha.messages.empty') }}</div>
    @else
        <ol class="govuk-list govuk-list--spaced">
            @foreach ($messages as $message)
                @php
                    $isOwn = (int) ($message['sender_id'] ?? 0) === $currentUserId;
                    $senderName = $isOwn ? __('govuk_alpha.messages.sent_by_you') : $otherName;
                    $sentAt = $formatDate($message['created_at'] ?? null);
                @endphp
                <li class="nexus-alpha-card">
                    <p class="govuk-body govuk-!-font-weight-bold govuk-!-margin-bottom-1">{{ $senderName }}</p>
                    @if ($sentAt)
                        <p class="govuk-hint govuk-!-margin-bottom-2">{{ __('govuk_alpha.messages.sent_label') }} {{ $sentAt }}</p>
                    @endif
                    <div class="govuk-body">{!! nl2br(e((string) ($message['body'] ?? ''))) !!}</div>
                </li>
            @endforeach
        </ol>
    @endif

    @if ($canSend)
        <form method="post" action="{{ route('govuk-alpha.messages.store', ['tenantSlug' => $tenantSlug, 'userId' => $conversation['id']]) }}" class="govuk-!-margin-top-7">
            @csrf
            @if ($listing)
                <input type="hidden" name="context_type" value="listing">
                <input type="hidden" name="context_id" value="{{ $listing['id'] }}">
            @endif
            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--m" for="body">{{ __('govuk_alpha.messages.message_label') }}</label>
                <div id="body-hint" class="govuk-hint">{{ __('govuk_alpha.messages.message_hint') }}</div>
                <textarea class="govuk-textarea" id="body" name="body" rows="5" aria-describedby="body-hint" required></textarea>
            </div>
            <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.actions.reply') }}</button>
        </form>
    @endif

    <form method="post" action="{{ route('govuk-alpha.messages.archive', ['tenantSlug' => $tenantSlug, 'userId' => $conversation['id']]) }}" class="govuk-!-margin-top-4">
        @csrf
        <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.actions.archive_conversation') }}</button>
    </form>
@endsection
