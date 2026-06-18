{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $allowed = (bool) ($allowed ?? false);
        $threads = $threads ?? [];
        $query = (string) ($query ?? '');
        $viewerOptedIn = (bool) ($viewerOptedIn ?? false);
        $viewerCanMessage = (bool) ($viewerCanMessage ?? false);
        $loadError = (bool) ($loadError ?? false);
        $statusKey = (string) ($status ?? '');
        $statusText = $statusKey !== '' ? __('govuk_alpha.fed2.messages.status.' . $statusKey) : '';
        $statusIsError = $statusKey !== '' && $statusKey !== 'message-sent';
    @endphp

    <a href="{{ route('govuk-alpha.federation.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.fed2.messages.back') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha.fed2.messages.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.fed2.messages.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.fed2.messages.description') }}</p>

    @include('accessible-frontend::partials.federation-nav')

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
        @if (!$viewerCanMessage)
            {{-- Distinguish "not opted in" from "feature off": if the viewer simply
                 has not opted in, offer the opt-in route; otherwise the feature is
                 not enabled for this community at all. --}}
            <div class="govuk-inset-text">
                <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha.fed2.messages.optin_required') }}</h2>
                <p class="govuk-body">{{ __('govuk_alpha.fed2.messages.optin_required_description') }}</p>
            </div>
            <a href="{{ route('govuk-alpha.federation.opt-in', ['tenantSlug' => $tenantSlug]) }}" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.fed2.messages.optin_cta') }}</a>
        @else
            <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.fed2.messages.feature_not_enabled') }}</p></div>
        @endif
    @else
        @if (!$viewerCanMessage)
            {{-- Allowed at the community level but the viewer still needs to opt in:
                 show the opt-in notice above the thread list. --}}
            <div class="govuk-inset-text">
                <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha.fed2.messages.optin_required') }}</h2>
                <p class="govuk-body">{{ __('govuk_alpha.fed2.messages.optin_required_description') }}</p>
            </div>
            <a href="{{ route('govuk-alpha.federation.opt-in', ['tenantSlug' => $tenantSlug]) }}" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.fed2.messages.optin_cta') }}</a>
        @endif

        <form method="get" action="{{ route('govuk-alpha.federation.messages.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-4">
            <div class="govuk-form-group govuk-!-margin-bottom-2">
                <label class="govuk-label" for="q">{{ __('govuk_alpha.fed2.messages.search_label') }}</label>
                <input class="govuk-input govuk-input--width-20" id="q" name="q" type="text" value="{{ $query }}" placeholder="{{ __('govuk_alpha.fed2.messages.search_placeholder') }}">
            </div>
            <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.actions.search') }}</button>
        </form>

        <p class="govuk-body">
            <a class="govuk-link" href="{{ route('govuk-alpha.federation.members.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.fed2.messages.compose_link') }}</a>
        </p>
        <p class="govuk-hint">{{ __('govuk_alpha.fed2.messages.compose_hint') }}</p>

        <h2 class="govuk-heading-m">{{ __('govuk_alpha.fed2.messages.conversations_heading') }}</h2>

        <div id="messages-list">
            @if ($loadError)
                <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                    </div>
                </div>
            @elseif (empty($threads))
                @if ($query !== '')
                    <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.fed2.messages.no_conversations_match') }}</p></div>
                @else
                    <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.fed2.messages.empty') }}</p></div>
                @endif
            @else
                <div class="nexus-alpha-card-list">
                    @foreach ($threads as $t)
                        @php
                            $partnerName = trim((string) ($t['partner_name'] ?? '')) ?: __('govuk_alpha.fed2.messages.someone');
                            $partnerTenantName = (string) ($t['partner_tenant_name'] ?? '');
                            $partnerUserId = (int) ($t['partner_user_id'] ?? 0);
                            $partnerTenantId = (int) ($t['partner_tenant_id'] ?? 0);
                            $unreadCount = (int) ($t['unread_count'] ?? 0);
                            $lastOutbound = (bool) ($t['last_outbound'] ?? false);
                            $lastPreview = trim((string) ($t['last_preview'] ?? ''));
                            $lastCreatedAt = $t['last_created_at'] ?? null;
                            $conversationHref = route('govuk-alpha.federation.messages.conversation', [
                                'tenantSlug' => $tenantSlug,
                                'partnerId' => $partnerUserId,
                                'tenant_id' => $partnerTenantId,
                            ]);
                        @endphp
                        <article class="nexus-alpha-card">
                            <h3 class="govuk-heading-s govuk-!-margin-bottom-1">
                                <a class="govuk-link" href="{{ $conversationHref }}" title="{{ __('govuk_alpha.fed2.messages.open_conversation') }}">{{ $partnerName }}</a>
                            </h3>
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">
                                <strong class="govuk-tag govuk-tag--grey">{{ $partnerTenantName }}</strong>
                                @if ($unreadCount > 0)
                                    <strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha.fed2.messages.unread_count', ['count' => $unreadCount]) }}</strong>
                                    <span class="govuk-visually-hidden">{{ trans_choice('govuk_alpha.fed2.messages.unread_aria', $unreadCount, ['count' => $unreadCount]) }}</span>
                                @endif
                            </p>
                            @if ($lastPreview !== '')
                                <p class="govuk-body-s govuk-!-margin-bottom-1">@if ($lastOutbound){{ __('govuk_alpha.fed2.messages.you_prefix') }}@endif{{ $lastPreview }}</p>
                            @endif
                            @if ($lastCreatedAt)
                                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">{{ \Illuminate\Support\Carbon::parse($lastCreatedAt)->translatedFormat('j F Y') }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
@endsection
