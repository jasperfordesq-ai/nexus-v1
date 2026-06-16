{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $asUrl = fn (string $p): string => $p === '' ? '' : (\Illuminate\Support\Str::startsWith($p, ['http://', 'https://', '/']) ? $p : '/' . ltrim($p, '/'));
        $mName = trim((string) ($member['name'] ?? '')) ?: __('govuk_alpha.federation.member.caption');
        $avatar = $asUrl(trim((string) ($member['avatar'] ?? '')));
        $loc = trim((string) ($member['location'] ?? ''));
        $skills = (array) ($member['skills'] ?? []);
        $reviews = $reviews ?? [];
        $connectionStatus = $connectionStatus ?? ['status' => 'none', 'connection_id' => null];
        $viewerOptedIn = (bool) ($viewerOptedIn ?? false);
        $viewerMessagingEnabled = (bool) ($viewerMessagingEnabled ?? false);
        $viewerTransactionsEnabled = (bool) ($viewerTransactionsEnabled ?? false);
        $canMessage = (bool) ($member['messaging_enabled'] ?? false);
        $canTransfer = (bool) ($member['transactions_enabled'] ?? false);
        $memberId = (int) ($member['id'] ?? 0);
        $memberTenantId = (int) ($member['tenant_id'] ?? 0);
        $connStatus = (string) ($connectionStatus['status'] ?? 'none');

        // Status banner: surface the result of a connect / message / transfer
        // action via the ?status= query param. Errors render an error-summary,
        // successes a notification-banner.
        $bannerMap = [
            'connect-sent' => ['success', __('govuk_alpha.fed2.member_actions.connect_pending')],
            'connect-failed' => ['error', __('govuk_alpha.fed2.connections.status.connection-action-failed')],
            'message-sent' => ['success', __('govuk_alpha.fed2.messages.status.message-sent')],
            'message-empty' => ['error', __('govuk_alpha.fed2.messages.status.message-empty')],
            'message-too-long' => ['error', __('govuk_alpha.fed2.messages.status.message-too-long')],
            'message-failed' => ['error', __('govuk_alpha.fed2.messages.status.message-failed')],
            'message-not-enabled' => ['error', __('govuk_alpha.fed2.messages.status.message-not-enabled')],
            'message-recipient-unavailable' => ['error', __('govuk_alpha.fed2.messages.status.message-recipient-unavailable')],
            'transfer-sent' => ['success', __('govuk_alpha.fed2.transfer.status.transfer-sent')],
        ];
        $banner = $bannerMap[$status ?? ''] ?? null;
    @endphp

    <a href="{{ route('govuk-alpha.federation.members.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.federation.member.back') }}</a>

    @if ($banner)
        @if ($banner[0] === 'error')
            <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                <div role="alert">
                    <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                    <div class="govuk-error-summary__body">
                        <ul class="govuk-list govuk-error-summary__list">
                            <li><a href="#fed-actions">{{ $banner[1] }}</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        @else
            <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="fed-member-status">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="fed-member-status">{{ __('govuk_alpha.states.success_title') }}</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading">{{ $banner[1] }}</p>
                </div>
            </div>
        @endif
    @endif

    <span class="govuk-caption-xl">{{ __('govuk_alpha.federation.member.caption') }}</span>
    <div class="nexus-alpha-module-row">
        @if ($avatar !== '')
            <img class="nexus-alpha-card-thumb" src="{{ $avatar }}" alt="{{ $mName }}" width="80" height="80" loading="lazy" decoding="async">
        @endif
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">{{ $mName }}</h1>
    </div>

    <p class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha.federation.member.community_label') }}: {{ $member['tenant_name'] ?? '' }}</p>

    @if (trim((string) ($member['bio'] ?? '')) !== '')
        <h2 class="govuk-heading-l">{{ __('govuk_alpha.federation.member.about_label') }}</h2>
        <div class="govuk-body">{!! nl2br(e($member['bio'])) !!}</div>
    @endif

    <dl class="govuk-summary-list">
        @if ($loc !== '')
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.federation.member.location_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $loc }}</dd>
            </div>
        @endif
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.federation.member.skills_label') }}</dt>
            <dd class="govuk-summary-list__value">
                @if (empty($skills))
                    {{ __('govuk_alpha.federation.member.no_skills') }}
                @else
                    @foreach ($skills as $skill)
                        <strong class="govuk-tag govuk-tag--grey govuk-!-margin-right-1">{{ $skill }}</strong>
                    @endforeach
                @endif
            </dd>
        </div>
    </dl>

    {{-- ===== WAVE FED2: connect / message / transfer actions ===== --}}
    <section id="fed-actions" class="govuk-!-margin-top-6" aria-labelledby="fed-actions-heading">
        <h2 id="fed-actions-heading" class="govuk-heading-l">{{ __('govuk_alpha.fed2.member_actions.heading') }}</h2>

        @if (!$viewerOptedIn)
            <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.fed2.member_actions.not_opted_in') }}</p></div>
            <a class="govuk-button" data-module="govuk-button" href="{{ route('govuk-alpha.federation.opt-in', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.fed2.member_actions.optin_link') }}</a>
        @else
            {{-- Connection action: depends on the existing connection status. --}}
            @if ($connStatus === 'accepted')
                <p class="govuk-body"><strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.fed2.member_actions.connect_accepted') }}</strong></p>
            @elseif ($connStatus === 'pending')
                <p class="govuk-body"><strong class="govuk-tag govuk-tag--yellow">{{ __('govuk_alpha.fed2.member_actions.connect_pending') }}</strong></p>
            @else
                <form method="post" action="{{ route('govuk-alpha.federation.connections.store', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
                    @csrf
                    <input type="hidden" name="receiver_id" value="{{ $memberId }}">
                    <input type="hidden" name="receiver_tenant_id" value="{{ $memberTenantId }}">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="connect-message">{{ __('govuk_alpha.fed2.member_actions.message_label') }}</label>
                        <div id="connect-message-hint" class="govuk-hint">{{ __('govuk_alpha.fed2.member_actions.message_hint') }}</div>
                        <input class="govuk-input govuk-!-width-two-thirds" id="connect-message" name="message" type="text" maxlength="1000" aria-describedby="connect-message-hint">
                    </div>
                    <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.fed2.member_actions.connect') }}</button>
                </form>
            @endif

            {{-- Messaging action --}}
            @if ($canMessage && $viewerMessagingEnabled)
                <form method="post" action="{{ route('govuk-alpha.federation.messages.store', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
                    @csrf
                    <input type="hidden" name="receiver_id" value="{{ $memberId }}">
                    <input type="hidden" name="receiver_tenant_id" value="{{ $memberTenantId }}">
                    <h3 class="govuk-heading-m">{{ __('govuk_alpha.fed2.member_actions.message') }}</h3>
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="message-subject">{{ __('govuk_alpha.polish_federation.message_subject_label') }}</label>
                        <input class="govuk-input govuk-!-width-two-thirds" id="message-subject" name="subject" type="text" maxlength="255">
                    </div>
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="message-body">{{ __('govuk_alpha.fed2.member_actions.message') }}</label>
                        <textarea class="govuk-textarea" id="message-body" name="body" rows="4" maxlength="10000"></textarea>
                    </div>
                    <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.fed2.member_actions.message') }}</button>
                </form>
            @elseif (!$canMessage)
                <p class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha.fed2.member_actions.messaging_off') }}</p>
            @endif

            {{-- Hour transfer action: links to the confirm form (warning + amount). --}}
            @if ($canTransfer && $viewerTransactionsEnabled)
                <div class="govuk-button-group">
                    <a class="govuk-button govuk-button--warning" data-module="govuk-button" href="{{ route('govuk-alpha.federation.transfer', ['tenantSlug' => $tenantSlug, 'id' => $memberId, 'tenant_id' => $memberTenantId]) }}">{{ __('govuk_alpha.fed2.member_actions.transfer') }}</a>
                    <a class="govuk-link" href="{{ route('govuk-alpha.federation.members.show', ['tenantSlug' => $tenantSlug, 'id' => $memberId, 'tenant_id' => $memberTenantId]) }}">{{ __('govuk_alpha.polish_federation.transfer_cancel') }}</a>
                </div>
            @elseif (!$canTransfer)
                <p class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha.fed2.member_actions.transactions_off') }}</p>
            @endif
        @endif
    </section>

    {{-- ===== WAVE FED2: reviews ===== --}}
    @if (!empty($reviews))
        <section class="govuk-!-margin-top-6" aria-labelledby="fed-reviews-heading">
            <h2 id="fed-reviews-heading" class="govuk-heading-l">{{ __('govuk_alpha.fed2.reviews.heading') }}</h2>
            <div class="nexus-alpha-card-list">
                @foreach ($reviews as $review)
                    @php
                        $rating = (int) ($review['rating'] ?? 0);
                        $reviewerName = trim((string) ($review['reviewer_name'] ?? '')) ?: __('govuk_alpha.fed2.reviews.anonymous');
                        $comment = trim((string) ($review['comment'] ?? ''));
                    @endphp
                    <article class="nexus-alpha-card">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $reviewerName }}</h3>
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.fed2.reviews.rating_label') }}: {{ $rating }}/5</p>
                        @if ($comment !== '')
                            <p class="govuk-body govuk-!-margin-bottom-0">{{ $comment }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif
@endsection
