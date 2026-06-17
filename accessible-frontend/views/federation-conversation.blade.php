{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $messages = $messages ?? [];
        $partnerId = (int) ($partnerId ?? 0);
        $partnerTenantId = (int) ($partnerTenantId ?? 0);
        $partnerName = trim((string) ($partnerName ?? '')) ?: __('govuk_alpha.fed2.messages.someone');
        $partnerTenantName = trim((string) ($partnerTenantName ?? ''));
        $canReply = (bool) ($canReply ?? false);
        $translateEnabled = (bool) ($translateEnabled ?? false);
        $translation = $translation ?? null;
        $translationId = is_array($translation) ? (int) ($translation['id'] ?? 0) : 0;

        $statusKey = (string) ($status ?? '');
        $statusText = $statusKey !== '' ? __('govuk_alpha.fed2.messages.status.' . $statusKey) : '';
        // translate-* failures and every message-* outcome except message-sent are errors.
        $statusIsError = ($statusKey !== '') && ($statusKey !== 'message-sent');

        // The reply threads onto the most recent message in the conversation.
        $lastMessage = !empty($messages) ? $messages[array_key_last($messages)] : null;
        $referenceMessageId = is_array($lastMessage) ? (int) ($lastMessage['id'] ?? 0) : 0;
    @endphp

    <a href="{{ route('govuk-alpha.federation.messages.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.fed2.messages.back_to_conversations') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha.fed2.messages.conversation_with', ['name' => $partnerName]) }}</span>
    <h1 class="govuk-heading-xl">{{ $partnerName }}</h1>
    @if ($partnerTenantName !== '')
        <p class="govuk-body-l govuk-!-margin-bottom-2">
            <strong class="govuk-tag govuk-tag--grey">{{ __('govuk_alpha.fed2.messages.community_label') }}: {{ $partnerTenantName }}</strong>
        </p>
    @endif

    @include('accessible-frontend::partials.federation-nav')

    @if ($statusText !== '')
        @if ($statusIsError)
            <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                <div role="alert">
                    <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                    <div class="govuk-error-summary__body">
                        <ul class="govuk-list govuk-error-summary__list">
                            <li><a href="#conversation-messages">{{ $statusText }}</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        @else
            <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="fed-conv-status">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="fed-conv-status">{{ __('govuk_alpha.states.success_title') }}</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading">{{ $statusText }}</p>
                </div>
            </div>
        @endif
    @endif

    @if (empty($messages))
        <div class="govuk-inset-text" id="conversation-messages"><p class="govuk-body">{{ __('govuk_alpha.fed2.messages.empty') }}</p></div>
    @else
        <div class="nexus-alpha-card-list" id="conversation-messages">
            @foreach ($messages as $m)
                @php
                    $mId = (int) ($m['id'] ?? 0);
                    $mSubject = trim((string) ($m['subject'] ?? ''));
                    $mBody = (string) ($m['body'] ?? '');
                    $mOutbound = (bool) ($m['outbound'] ?? false);
                    $mRead = (bool) ($m['read'] ?? false);
                    $mCreatedAt = $m['created_at'] ?? null;
                    $mCreatedLabel = $mCreatedAt ? \Illuminate\Support\Carbon::parse($mCreatedAt)->translatedFormat('j F Y, g:ia') : '';
                    $hasTranslation = is_array($translation) && $translationId === $mId;
                @endphp
                <article class="nexus-alpha-card">
                    <p class="govuk-body-s govuk-!-margin-bottom-1">
                        @if ($mOutbound)
                            <strong class="govuk-tag govuk-tag--grey">{{ __('govuk_alpha.fed2.messages.sent_label') }}</strong>
                        @else
                            <strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha.fed2.messages.received_label') }}</strong>
                        @endif
                    </p>

                    @if ($mSubject !== '')
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $mSubject }}</h2>
                    @else
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha.fed2.messages.no_subject') }}</h2>
                    @endif

                    <p class="govuk-body govuk-!-margin-bottom-1">{!! nl2br(e($mBody)) !!}</p>

                    @if ($mCreatedLabel !== '')
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">
                            <time datetime="{{ $mCreatedAt }}">{{ $mCreatedLabel }}</time>
                        </p>
                    @endif

                    @if ($mOutbound)
                        <p class="govuk-body-s govuk-!-margin-bottom-0">
                            @if ($mRead)
                                <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.fed2.messages.read_label') }}</strong>
                            @else
                                <strong class="govuk-tag govuk-tag--grey">{{ __('govuk_alpha.fed2.messages.delivered_label') }}</strong>
                            @endif
                        </p>
                    @endif

                    @if (!$mOutbound && $translateEnabled)
                        @if ($hasTranslation)
                            <div class="govuk-!-margin-top-2">
                                <p class="govuk-body-s govuk-!-margin-bottom-1">
                                    <strong class="govuk-tag govuk-tag--purple">{{ __('govuk_alpha.fed2.messages.translated_label') }}</strong>
                                </p>
                                <p class="govuk-body govuk-!-margin-bottom-1">{!! nl2br(e((string) ($translation['text'] ?? ''))) !!}</p>
                                <p class="govuk-body-s govuk-!-margin-bottom-0">
                                    <a class="govuk-link" href="{{ route('govuk-alpha.federation.messages.conversation', ['tenantSlug' => $tenantSlug, 'partnerId' => $partnerId, 'tenant_id' => $partnerTenantId]) }}">{{ __('govuk_alpha.fed2.messages.view_original') }}</a>
                                </p>
                            </div>
                        @else
                            <form method="post" action="{{ route('govuk-alpha.federation.messages.translate', ['tenantSlug' => $tenantSlug, 'id' => $mId]) }}" class="govuk-!-margin-top-2">
                                @csrf
                                <input type="hidden" name="partner_id" value="{{ $partnerId }}">
                                <input type="hidden" name="partner_tenant_id" value="{{ $partnerTenantId }}">
                                <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.fed2.messages.translate_button') }}</button>
                            </form>
                        @endif
                    @endif
                </article>
            @endforeach
        </div>
    @endif

    @if ($canReply)
        <form method="post" action="{{ route('govuk-alpha.federation.messages.store', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-top-6">
            @csrf
            <input type="hidden" name="receiver_id" value="{{ $partnerId }}">
            <input type="hidden" name="receiver_tenant_id" value="{{ $partnerTenantId }}">
            <input type="hidden" name="context" value="conversation">
            <input type="hidden" name="reference_message_id" value="{{ $referenceMessageId }}">
            <input type="hidden" name="subject" value="{{ __('govuk_alpha.fed2.messages.reply_subject_default') }}">

            <div class="govuk-form-group">
                <label class="govuk-label" for="body">{{ __('govuk_alpha.fed2.messages.reply_label') }}</label>
                <div id="body-hint" class="govuk-hint">{{ __('govuk_alpha.fed2.messages.reply_hint') }}</div>
                <textarea class="govuk-textarea" id="body" name="body" rows="4" maxlength="10000" aria-describedby="body-hint"></textarea>
            </div>

            <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.fed2.messages.reply_submit') }}</button>
        </form>
    @endif
@endsection
