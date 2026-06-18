{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $formatDate = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y, g:ia') : null;
        $groupName = trim((string) ($conversation['group_name'] ?? '')) !== '' ? (string) $conversation['group_name'] : __('govuk_alpha_messages.groups.untitled');
        $conversationId = (int) ($conversation['id'] ?? 0);
        $isAdmin = ($viewerRole ?? 'member') === 'admin';
        $canSend = $directMessagingEnabled && empty($restriction['messaging_disabled']);
        $searchQuery = trim((string) ($searchQuery ?? ''));
        $reactions = $reactions ?? [];
        $reactionEmojis = $reactionEmojis ?? [];
        // Filter to matching messages when a search term is present (server-side).
        $allMessages = $messages ?? [];
        $visibleMessages = $allMessages;
        if ($searchQuery !== '') {
            $needle = mb_strtolower($searchQuery);
            $visibleMessages = array_values(array_filter($allMessages, static function (array $m) use ($needle): bool {
                return str_contains(mb_strtolower((string) ($m['body'] ?? '')), $needle);
            }));
        }
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.messages.groups.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_messages.conversation.back') }}</a>

    @include('accessible-frontend::partials.messages-status', ['status' => $status ?? null])

    <span class="govuk-caption-l">{{ __('govuk_alpha_messages.conversation.caption') }}</span>
    <h1 class="govuk-heading-xl">{{ $groupName }}</h1>

    @if (!$directMessagingEnabled)
        <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="group-conv-disabled-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="group-conv-disabled-title">{{ __('govuk_alpha.messages.disabled_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-body">{{ __('govuk_alpha.messages.disabled_detail') }}</p>
            </div>
        </div>
    @elseif (!empty($restriction['messaging_disabled']))
        <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="group-conv-restricted-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="group-conv-restricted-title">{{ __('govuk_alpha.messages.disabled_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-body">{{ __('govuk_alpha.messages.restricted_detail') }}</p>
            </div>
        </div>
    @endif

    {{-- Members panel --}}
    <h2 class="govuk-heading-m">{{ __('govuk_alpha_messages.conversation.members_heading') }}</h2>
    <ul class="govuk-list">
        @foreach (($participants ?? []) as $participant)
            @php
                $pId = (int) ($participant['id'] ?? 0);
                $pName = trim((string) ($participant['name'] ?? '')) !== '' ? (string) $participant['name'] : __('govuk_alpha.members.unknown_member');
                $pIsAdmin = ($participant['role'] ?? 'member') === 'admin';
                $pIsYou = $pId === (int) $currentUserId;
            @endphp
            <li class="govuk-!-margin-bottom-2" style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap">
                <span>{{ $pName }}</span>
                @if ($pIsYou)
                    <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha_messages.conversation.you_badge') }}</strong>
                @endif
                @if ($pIsAdmin)
                    <strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha_messages.conversation.admin_badge') }}</strong>
                @endif
                @if (!empty($participant['is_online']))
                    <strong class="govuk-tag govuk-tag--turquoise">{{ __('govuk_alpha_messages.conversation.online_badge') }}</strong>
                @endif
                @if ($isAdmin && !$pIsYou)
                    <form method="post" action="{{ route('govuk-alpha.messages.groups.members.remove', ['tenantSlug' => $tenantSlug, 'conversationId' => $conversationId, 'targetUserId' => $pId]) }}" style="display:inline">
                        @csrf
                        <button type="submit" class="govuk-link" style="background:none;border:0;padding:0;cursor:pointer;text-decoration:underline;color:#d4351c">{{ __('govuk_alpha_messages.manage.remove_button') }}<span class="govuk-visually-hidden"> {{ __('govuk_alpha_messages.manage.remove_hidden', ['name' => $pName]) }}</span></button>
                    </form>
                @endif
            </li>
        @endforeach
    </ul>

    {{-- In-conversation search (server-side filter + highlight) --}}
    <div class="nexus-alpha-card govuk-!-margin-bottom-4">
        <form method="get" action="{{ route('govuk-alpha.messages.groups.show', ['tenantSlug' => $tenantSlug, 'conversationId' => $conversationId]) }}">
            <div class="govuk-form-group govuk-!-margin-bottom-2">
                <label class="govuk-label" for="group-conv-search">{{ __('govuk_alpha_messages.conversation.search_label') }}</label>
                <div id="group-conv-search-hint" class="govuk-hint">{{ __('govuk_alpha_messages.conversation.search_hint') }}</div>
                <input class="govuk-input govuk-!-width-two-thirds" id="group-conv-search" name="q" type="search" value="{{ $searchQuery }}" aria-describedby="group-conv-search-hint">
            </div>
            <div class="govuk-button-group">
                <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha_messages.conversation.search_button') }}</button>
                @if ($searchQuery !== '')
                    <a class="govuk-link" href="{{ route('govuk-alpha.messages.groups.show', ['tenantSlug' => $tenantSlug, 'conversationId' => $conversationId]) }}">{{ __('govuk_alpha_messages.conversation.search_clear') }}</a>
                @endif
            </div>
        </form>
        @if ($searchQuery !== '')
            <p class="govuk-body govuk-!-margin-top-2 govuk-!-margin-bottom-0">{{ trans_choice('govuk_alpha_messages.conversation.search_results', count($visibleMessages), ['count' => count($visibleMessages), 'query' => $searchQuery]) }}</p>
        @endif
    </div>

    @if ($searchQuery === '' && !empty($meta['has_more']) && !empty($meta['cursor']))
        <nav class="govuk-pagination govuk-pagination--block govuk-!-margin-bottom-4" aria-label="{{ __('govuk_alpha_messages.conversation.older_pagination_label') }}">
            <div class="govuk-pagination__prev">
                <a class="govuk-link govuk-pagination__link" href="{{ route('govuk-alpha.messages.groups.show', ['tenantSlug' => $tenantSlug, 'conversationId' => $conversationId, 'cursor' => $meta['cursor']]) }}" rel="prev">
                    <span class="govuk-pagination__link-title">{{ __('govuk_alpha_messages.conversation.show_older') }}</span>
                </a>
            </div>
        </nav>
    @endif

    @if (empty($visibleMessages))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_messages.conversation.empty') }}</p></div>
    @else
        <ol class="govuk-list govuk-list--spaced">
            @foreach ($visibleMessages as $message)
                @php
                    $messageId = (int) ($message['id'] ?? 0);
                    $senderId = (int) ($message['sender_id'] ?? 0);
                    $isOwn = $senderId === (int) $currentUserId;
                    $senderName = $isOwn
                        ? __('govuk_alpha_messages.conversation.sent_by_you')
                        : (trim((string) ($message['sender']['name'] ?? '')) !== '' ? (string) $message['sender']['name'] : __('govuk_alpha.members.unknown_member'));
                    $sentAt = $formatDate($message['created_at'] ?? null);
                    $isDeleted = (bool) ($message['is_deleted'] ?? false);
                    $senderAvatar = $message['sender']['avatar_url'] ?? null;
                    $msgReactions = $reactions[$messageId] ?? ['counts' => [], 'mine' => []];
                    $myEmojis = $msgReactions['mine'] ?? [];
                @endphp
                <li class="nexus-alpha-card" id="m-{{ $messageId }}">
                    <div class="nexus-alpha-card-head">
                        @if (!empty($senderAvatar))
                            <img class="nexus-alpha-avatar" src="{{ $senderAvatar }}" alt="" loading="lazy" decoding="async" width="48" height="48">
                        @else
                            <span class="nexus-alpha-avatar nexus-alpha-avatar--placeholder" aria-hidden="true">{{ mb_strtoupper(mb_substr($senderName, 0, 1)) }}</span>
                        @endif
                        <p class="govuk-body govuk-!-font-weight-bold govuk-!-margin-bottom-0">{{ $senderName }}</p>
                    </div>
                    @if ($sentAt)
                        <p class="govuk-hint govuk-!-margin-bottom-2">{{ __('govuk_alpha_messages.conversation.sent_label') }} {{ $sentAt }}</p>
                    @endif
                    @if ($isDeleted)
                        <p class="govuk-body govuk-hint">{{ __('govuk_alpha_messages.conversation.deleted_placeholder') }}</p>
                    @else
                        @php
                            $body = (string) ($message['body'] ?? '');
                        @endphp
                        @if ($searchQuery !== '')
                            {{-- Highlight the match safely: escape first, then wrap the term in <mark>. --}}
                            @php
                                $escaped = e($body);
                                $escapedNeedle = preg_quote(e($searchQuery), '/');
                                $highlighted = preg_replace('/(' . $escapedNeedle . ')/iu', '<mark class="nexus-alpha-search-match">$1</mark>', $escaped);
                                $highlighted = nl2br($highlighted ?? $escaped);
                            @endphp
                            <div class="govuk-body">{!! $highlighted !!}</div>
                        @else
                            <div class="govuk-body">{!! nl2br(e($body)) !!}</div>
                        @endif
                    @endif

                    {{-- Reaction counts --}}
                    @if (!empty($msgReactions['counts']))
                        <p class="govuk-body govuk-!-margin-top-2 govuk-!-margin-bottom-2">
                            @foreach ($msgReactions['counts'] as $emoji => $count)
                                <span class="govuk-tag govuk-tag--grey govuk-!-margin-right-1">{{ __('govuk_alpha_messages.reactions.count_label', ['emoji' => $emoji, 'count' => (int) $count]) }}</span>
                            @endforeach
                        </p>
                    @endif

                    {{-- Reaction controls: a fixed HTML emoji row (no JS picker) --}}
                    @if (!$isDeleted && $canSend)
                        <details class="govuk-details" data-module="govuk-details">
                            <summary class="govuk-details__summary">
                                <span class="govuk-details__summary-text">{{ __('govuk_alpha_messages.reactions.heading') }}</span>
                            </summary>
                            <div class="govuk-details__text">
                                <fieldset class="govuk-fieldset">
                                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_messages.reactions.add_legend') }}</legend>
                                    <div class="govuk-button-group">
                                        @foreach ($reactionEmojis as $emoji)
                                            @php
                                                $mine = in_array($emoji, $myEmojis, true);
                                            @endphp
                                            <form method="post" action="{{ route('govuk-alpha.messages.groups.react', ['tenantSlug' => $tenantSlug, 'conversationId' => $conversationId, 'messageId' => $messageId]) }}" style="display:inline">
                                                @csrf
                                                <input type="hidden" name="emoji" value="{{ $emoji }}">
                                                <button type="submit" class="govuk-button {{ $mine ? '' : 'govuk-button--secondary' }} govuk-!-margin-bottom-0" data-module="govuk-button">
                                                    <span aria-hidden="true">{{ $emoji }}</span>
                                                    <span class="govuk-visually-hidden">{{ $mine ? __('govuk_alpha_messages.reactions.remove_with', ['emoji' => $emoji]) : __('govuk_alpha_messages.reactions.react_with', ['emoji' => $emoji]) }}</span>
                                                </button>
                                            </form>
                                        @endforeach
                                    </div>
                                </fieldset>
                            </div>
                        </details>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif

    {{-- Reply form --}}
    @if ($canSend)
        <form method="post" action="{{ route('govuk-alpha.messages.groups.message', ['tenantSlug' => $tenantSlug, 'conversationId' => $conversationId]) }}" class="govuk-!-margin-top-6">
            @csrf
            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--m" for="group-body">{{ __('govuk_alpha_messages.conversation.reply_label') }}</label>
                <div id="group-body-hint" class="govuk-hint">{{ __('govuk_alpha_messages.conversation.reply_hint') }}</div>
                <textarea class="govuk-textarea" id="group-body" name="body" rows="5" aria-describedby="group-body-hint" required></textarea>
            </div>
            <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_messages.conversation.send_button') }}</button>
        </form>
    @endif

    {{-- Manage members (admin) --}}
    @if ($isAdmin && $canSend)
        <div class="nexus-alpha-card govuk-!-margin-top-6">
            <h2 class="govuk-heading-m">{{ __('govuk_alpha_messages.manage.heading') }}</h2>
            <form method="post" action="{{ route('govuk-alpha.messages.groups.members.add', ['tenantSlug' => $tenantSlug, 'conversationId' => $conversationId]) }}">
                @csrf
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_messages.manage.add_legend') }}</legend>
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="add-user-id">{{ __('govuk_alpha_messages.manage.add_user_label') }}</label>
                        <div id="add-user-hint" class="govuk-hint">{{ __('govuk_alpha_messages.manage.add_hint') }}</div>
                        <input class="govuk-input govuk-input--width-10" id="add-user-id" name="user_id" type="number" min="1" inputmode="numeric" aria-describedby="add-user-hint" required>
                    </div>
                    <p class="govuk-body govuk-!-margin-bottom-2">
                        <a class="govuk-link" href="{{ route('govuk-alpha.members.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_messages.manage.add_via_directory') }}</a>
                    </p>
                    <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_messages.manage.add_button') }}</button>
                </fieldset>
            </form>
        </div>
    @else
        <p class="govuk-hint govuk-!-margin-top-4">{{ __('govuk_alpha_messages.manage.admin_only_notice') }}</p>
    @endif

    {{-- Leave group --}}
    <div class="govuk-!-margin-top-6">
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_messages.manage.leave_heading') }}</h2>
        <div class="govuk-warning-text">
            <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
            <strong class="govuk-warning-text__text">
                <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.warning') }}</span>
                {{ __('govuk_alpha_messages.manage.leave_warning') }}
            </strong>
        </div>
        <form method="post" action="{{ route('govuk-alpha.messages.groups.members.remove', ['tenantSlug' => $tenantSlug, 'conversationId' => $conversationId, 'targetUserId' => (int) $currentUserId]) }}">
            @csrf
            <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_messages.manage.leave_button') }}</button>
        </form>
    </div>
@endsection
