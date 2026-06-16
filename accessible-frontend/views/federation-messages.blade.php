{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $allowed = (bool) ($allowed ?? false);
        $messages = $messages ?? [];
        $statusKey = (string) ($status ?? '');
        $statusText = $statusKey !== '' ? __('govuk_alpha.fed2.messages.status.' . $statusKey) : '';
        $statusIsError = $statusKey !== '' && $statusKey !== 'message-sent';
    @endphp

    <a href="{{ route('govuk-alpha.federation.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.fed2.messages.back') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha.fed2.messages.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.fed2.messages.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.fed2.messages.description') }}</p>

    @if ($statusText !== '')
        @if ($statusIsError)
            <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                <div role="alert">
                    <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                    <div class="govuk-error-summary__body">
                        <ul class="govuk-list govuk-error-summary__list">
                            <li><a href="#messages-list">{{ $statusText }}</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        @else
            <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="fed-msg-status">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="fed-msg-status">{{ __('govuk_alpha.states.success_title') }}</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading">{{ $statusText }}</p>
                </div>
            </div>
        @endif
    @endif

    @if (!$allowed)
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.fed2.messages.not_available') }}</p></div>
    @else
        <div id="messages-list">
            @if (empty($messages))
                <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.fed2.messages.empty') }}</p></div>
            @else
                <div class="nexus-alpha-card-list">
                    @foreach ($messages as $msg)
                        @php
                            $direction = (string) ($msg['direction'] ?? 'inbound');
                            $isOutbound = $direction === 'outbound';
                            $subject = trim((string) ($msg['subject'] ?? '')) ?: __('govuk_alpha.fed2.messages.no_subject');
                            $body = (string) ($msg['body'] ?? '');
                            $counterpartName = $isOutbound
                                ? (trim((string) ($msg['receiver_name'] ?? '')) ?: __('govuk_alpha.fed2.messages.someone'))
                                : (trim((string) ($msg['sender_name'] ?? '')) ?: __('govuk_alpha.fed2.messages.someone'));
                            $counterpartTenant = $isOutbound ? (string) ($msg['receiver_tenant_name'] ?? '') : (string) ($msg['sender_tenant_name'] ?? '');
                            $isUnread = !$isOutbound && (string) ($msg['status'] ?? '') === 'unread';
                            $dirLabel = $isOutbound ? __('govuk_alpha.fed2.messages.sent_label') : __('govuk_alpha.fed2.messages.received_label');
                            $partyLabel = $isOutbound ? __('govuk_alpha.fed2.messages.to_label') : __('govuk_alpha.fed2.messages.from_label');
                        @endphp
                        <article class="nexus-alpha-card">
                            <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $subject }}</h2>
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">
                                <strong class="govuk-tag govuk-tag--grey">{{ $dirLabel }}</strong>
                                @if ($isUnread)
                                    <strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha.fed2.messages.unread_label') }}</strong>
                                @endif
                            </p>
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ $partyLabel }}: {{ $counterpartName }} ({{ $counterpartTenant }})</p>
                            @if (trim($body) !== '')
                                {{-- Body is stored htmlspecialchars-encoded by storeFederationMessage (mirrors the API),
                                     so it is already entity-safe; only add line breaks. Do NOT re-escape (would double-encode). --}}
                                <div class="govuk-body govuk-!-margin-bottom-0">{!! nl2br($body, false) !!}</div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
@endsection
