{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $fmtHours = fn ($v): string => number_format((float) $v, 2);

        $balance = (float) ($wallet['balance'] ?? 0);
        $earned = (float) ($wallet['total_earned'] ?? 0);
        $spent = (float) ($wallet['total_spent'] ?? 0);
        $pendingIn = (float) ($wallet['pending_in'] ?? ($wallet['pending_incoming'] ?? 0));
        $pendingOut = (float) ($wallet['pending_out'] ?? ($wallet['pending_outgoing'] ?? 0));
        $pendingTotal = $pendingIn + $pendingOut;
        // The badge count is rounded to a whole number for the trans_choice plural,
        // matching the React chip which shows a count.
        $pendingInCount = (int) round($pendingIn);

        $fundBalance = (float) ($fund['balance'] ?? 0);
        $fundDonated = (float) ($fund['total_donated'] ?? 0);

        $donateTarget = ($donateTarget ?? 'community_fund') === 'user' ? 'user' : 'community_fund';

        $errorMessages = [
            'invalid' => __('govuk_alpha_wallet.errors.invalid'),
            'insufficient' => __('govuk_alpha_wallet.errors.insufficient'),
            'not-found' => __('govuk_alpha_wallet.errors.not_found'),
            'self' => __('govuk_alpha_wallet.errors.self'),
            'inactive' => __('govuk_alpha_wallet.errors.inactive'),
            'too-large' => __('govuk_alpha_wallet.errors.too_large'),
            'decimals' => __('govuk_alpha_wallet.errors.decimals'),
            'failed' => __('govuk_alpha_wallet.errors.failed'),
        ];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.wallet.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_wallet.manage.back_to_wallet') }}</a>

    @if ($status === 'transfer-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_wallet.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        <li><a href="#transfer">{{ $errorMessages[$transferError ?? 'failed'] ?? $errorMessages['failed'] }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
    @elseif ($status === 'donate-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_wallet.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        <li><a href="#donate">{{ $errorMessages[$donateError ?? 'failed'] ?? $errorMessages['failed'] }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ __('govuk_alpha_wallet.manage.caption') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_wallet.manage.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_wallet.manage.description') }}</p>

    @if ($status === 'transfer-sent')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="wallet-manage-transfer-title">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="wallet-manage-transfer-title">{{ __('govuk_alpha_wallet.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_wallet.states.transfer_sent') }}</p></div>
        </div>
    @elseif ($status === 'donate-sent')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="wallet-manage-donate-title">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="wallet-manage-donate-title">{{ __('govuk_alpha_wallet.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_wallet.states.donate_sent') }}</p></div>
        </div>
    @endif

    {{-- Balance card with the live "pending in" badge (parity with WalletPage.tsx:298-307). --}}
    <section aria-labelledby="balance-heading" id="balance">
        <h2 class="govuk-heading-l" id="balance-heading">{{ __('govuk_alpha_wallet.balance.heading') }}</h2>
        <div class="nexus-alpha-card">
            <dl class="govuk-summary-list govuk-!-margin-bottom-2">
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_wallet.balance.label') }}</dt>
                    <dd class="govuk-summary-list__value">{{ __('govuk_alpha_wallet.hours_value', ['value' => $fmtHours($balance)]) }}</dd>
                </div>
            </dl>
            <p class="govuk-body govuk-!-margin-bottom-0">
                @if ($pendingInCount > 0)
                    <strong class="govuk-tag govuk-tag--yellow">{{ trans_choice('govuk_alpha_wallet.balance.pending_badge_in', $pendingInCount, ['count' => $pendingInCount]) }}</strong>
                @else
                    <strong class="govuk-tag govuk-tag--grey">{{ __('govuk_alpha_wallet.balance.no_pending') }}</strong>
                @endif
            </p>
        </div>
    </section>

    {{-- Full 3-up stat grid INCLUDING Pending (parity with WalletPage.tsx:350-372). --}}
    <section aria-labelledby="stats-heading" id="stats">
        <h2 class="govuk-heading-l govuk-!-margin-top-7" id="stats-heading">{{ __('govuk_alpha_wallet.stats.heading') }}</h2>
        <dl class="nexus-alpha-stat-grid">
            <div class="nexus-alpha-stat">
                <dt>{{ __('govuk_alpha_wallet.stats.earned') }}</dt>
                <dd>{{ __('govuk_alpha_wallet.stats.earned_value', ['value' => $fmtHours($earned)]) }}</dd>
            </div>
            <div class="nexus-alpha-stat">
                <dt>{{ __('govuk_alpha_wallet.stats.spent') }}</dt>
                <dd>{{ __('govuk_alpha_wallet.stats.spent_value', ['value' => $fmtHours($spent)]) }}</dd>
            </div>
            <div class="nexus-alpha-stat">
                <dt>{{ __('govuk_alpha_wallet.stats.pending') }}</dt>
                <dd>{{ __('govuk_alpha_wallet.stats.pending_value', ['value' => $fmtHours($pendingTotal)]) }}</dd>
            </div>
        </dl>
        <p class="govuk-hint">{{ __('govuk_alpha_wallet.stats.pending_hint') }}</p>
    </section>

    {{-- Send credits to a member, with no-JS ?to= recipient pre-fill
         (parity with WalletPage.tsx:61-129). The actual transfer POSTs to the
         existing govuk-alpha.wallet.transfer handler (WalletService::transfer). --}}
    <section aria-labelledby="transfer-heading" id="transfer">
        <h2 class="govuk-heading-l govuk-!-margin-top-7" id="transfer-heading">{{ __('govuk_alpha_wallet.transfer.heading') }}</h2>
        <p class="govuk-body">{{ __('govuk_alpha_wallet.transfer.description') }}</p>

        @if (!empty($prefillRecipientId) && !empty($recipientResults))
            <div class="govuk-inset-text" role="note">
                <p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha_wallet.transfer.prefill_notice') }}</p>
            </div>
        @endif

        <form method="get" action="{{ route('govuk-alpha.wallet.manage', ['tenantSlug' => $tenantSlug]) }}">
            <div class="govuk-form-group">
                <label class="govuk-label" for="recipient_q">{{ __('govuk_alpha_wallet.transfer.search_label') }}</label>
                <div id="recipient-q-hint" class="govuk-hint">{{ __('govuk_alpha_wallet.transfer.search_hint') }}</div>
                <input class="govuk-input govuk-!-width-two-thirds" id="recipient_q" name="recipient_q" type="search" value="{{ $recipientQuery ?? '' }}" aria-describedby="recipient-q-hint">
            </div>
            <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha_wallet.transfer.search_button') }}</button>
        </form>

        @if (!empty($recipientResults) || ($recipientQuery ?? '') !== '')
            @if (empty($recipientResults))
                <p class="govuk-body">{{ __('govuk_alpha_wallet.transfer.search_empty') }}</p>
            @else
                <h3 class="govuk-heading-m">{{ __('govuk_alpha_wallet.transfer.recipient_heading') }}</h3>
                @foreach ($recipientResults as $recipient)
                    @php
                        $recipientName = trim((string) ($recipient['name'] ?? ''));
                        if ($recipientName === '') {
                            $recipientName = __('govuk_alpha.members.unknown_member');
                        }
                        $recipientLoc = trim((string) ($recipient['location'] ?? ''));
                        $recipientSince = !empty($recipient['created_at'])
                            ? \Illuminate\Support\Carbon::parse($recipient['created_at'])->translatedFormat('F Y')
                            : null;
                        $recipientMeta = array_values(array_filter([
                            $recipientLoc !== '' ? $recipientLoc : null,
                            $recipientSince ? __('govuk_alpha_wallet.member_since', ['date' => $recipientSince]) : null,
                        ]));
                    @endphp
                    <div class="nexus-alpha-card govuk-!-margin-bottom-4">
                        <h4 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $recipientName }}</h4>
                        @if (!empty($recipientMeta))
                            <p class="govuk-hint govuk-!-font-size-16 govuk-!-margin-bottom-3">{{ implode(' · ', $recipientMeta) }}</p>
                        @endif
                        <form method="post" action="{{ route('govuk-alpha.wallet.transfer', ['tenantSlug' => $tenantSlug]) }}">
                            @csrf
                            <input type="hidden" name="recipient_id" value="{{ $recipient['id'] }}">
                            <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                            <div class="govuk-form-group govuk-!-margin-bottom-3">
                                <label class="govuk-label" for="amount-{{ $recipient['id'] }}">{{ __('govuk_alpha_wallet.transfer.amount_label') }}</label>
                                <div id="amount-hint-{{ $recipient['id'] }}" class="govuk-hint">{{ __('govuk_alpha_wallet.transfer.amount_hint') }}</div>
                                <input class="govuk-input govuk-input--width-5" id="amount-{{ $recipient['id'] }}" name="amount" type="number" min="0.25" max="1000" step="0.25" inputmode="decimal" aria-describedby="amount-hint-{{ $recipient['id'] }}" required>
                            </div>
                            <div class="govuk-form-group govuk-!-margin-bottom-3">
                                <label class="govuk-label" for="note-{{ $recipient['id'] }}">{{ __('govuk_alpha_wallet.transfer.note_label') }}</label>
                                <div id="note-hint-{{ $recipient['id'] }}" class="govuk-hint">{{ __('govuk_alpha_wallet.transfer.note_hint') }}</div>
                                <input class="govuk-input" id="note-{{ $recipient['id'] }}" name="note" type="text" maxlength="255" aria-describedby="note-hint-{{ $recipient['id'] }}">
                            </div>
                            <div class="govuk-form-group govuk-!-margin-bottom-3">
                                <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                                    <div class="govuk-checkboxes__item">
                                        <input class="govuk-checkboxes__input" id="confirm-{{ $recipient['id'] }}" name="confirm" type="checkbox" value="1" required>
                                        <label class="govuk-label govuk-checkboxes__label" for="confirm-{{ $recipient['id'] }}">{{ __('govuk_alpha.ux.transfer_confirm_label') }}</label>
                                    </div>
                                </div>
                            </div>
                            <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_wallet.transfer.send_button', ['name' => $recipientName]) }}</button>
                        </form>
                    </div>
                @endforeach
            @endif
        @endif
    </section>

    {{-- Donate, with a recipient-type toggle (community fund OR a member) —
         parity with DonateModal.tsx:36-39,102-170. The member branch POSTs the
         same target=user payload AlphaController::donateCredits already handles
         (CreditDonationService::donateToMember). --}}
    <section aria-labelledby="donate-heading" id="donate">
        <h2 class="govuk-heading-l govuk-!-margin-top-7" id="donate-heading">{{ __('govuk_alpha_wallet.donate.heading') }}</h2>
        <p class="govuk-body">{{ __('govuk_alpha_wallet.donate.description') }}</p>

        <div class="govuk-inset-text" role="note">
            <p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha_wallet.donate.credits_not_money') }}</p>
        </div>

        @if ($fund !== null)
            <dl class="govuk-summary-list">
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_wallet.donate.fund_balance_label') }}</dt>
                    <dd class="govuk-summary-list__value">{{ __('govuk_alpha_wallet.hours_value', ['value' => $fmtHours($fundBalance)]) }}</dd>
                </div>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_wallet.donate.fund_donated_label') }}</dt>
                    <dd class="govuk-summary-list__value">{{ __('govuk_alpha_wallet.hours_value', ['value' => $fmtHours($fundDonated)]) }}</dd>
                </div>
            </dl>
        @endif

        <div class="govuk-warning-text">
            <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
            <strong class="govuk-warning-text__text">
                <span class="govuk-visually-hidden">{{ __('govuk_alpha_wallet.states.warning') }}</span>
                {{ __('govuk_alpha_wallet.donate.warning') }}
            </strong>
        </div>

        <form method="post" action="{{ route('govuk-alpha.wallet.donate', ['tenantSlug' => $tenantSlug]) }}">
            @csrf

            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset" aria-describedby="donate-target-hint">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">{{ __('govuk_alpha_wallet.donate.target_legend') }}</legend>
                    <div id="donate-target-hint" class="govuk-hint">{{ __('govuk_alpha_wallet.donate.target_fund_hint') }}</div>
                    <div class="govuk-radios" data-module="govuk-radios">
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="donate-target-fund" name="target" type="radio" value="community_fund" @if ($donateTarget !== 'user') checked @endif>
                            <label class="govuk-label govuk-radios__label" for="donate-target-fund">{{ __('govuk_alpha_wallet.donate.target_fund') }}</label>
                        </div>
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="donate-target-member" name="target" type="radio" value="user" @if ($donateTarget === 'user') checked @endif @if (empty($recipientResults)) disabled @endif>
                            <label class="govuk-label govuk-radios__label" for="donate-target-member">{{ __('govuk_alpha_wallet.donate.target_member') }}</label>
                            <div class="govuk-hint govuk-radios__hint">{{ __('govuk_alpha_wallet.donate.target_member_hint') }}</div>
                        </div>
                    </div>
                </fieldset>
            </div>

            {{-- The member recipient is the single member resolved/searched above.
                 With no member in scope, the "member" radio is disabled, so only
                 the fund branch is selectable — mirroring the React modal which
                 hides the recipient picker until "member" is chosen. --}}
            @if (!empty($recipientResults))
                @php $donateRecipient = $recipientResults[0]; @endphp
                <input type="hidden" name="recipient_id" value="{{ $donateRecipient['id'] }}">
                <p class="govuk-body govuk-!-margin-bottom-4">{{ trim((string) ($donateRecipient['name'] ?? '')) !== '' ? $donateRecipient['name'] : __('govuk_alpha.members.unknown_member') }}</p>
            @else
                <p class="govuk-hint">{{ __('govuk_alpha_wallet.donate.recipient_required') }}</p>
            @endif

            <div class="govuk-form-group">
                <label class="govuk-label" for="donate-amount">{{ __('govuk_alpha_wallet.donate.amount_label') }}</label>
                <div id="donate-amount-hint" class="govuk-hint">{{ __('govuk_alpha_wallet.donate.amount_hint') }}</div>
                <input class="govuk-input govuk-input--width-5" id="donate-amount" name="amount" type="number" min="1" max="1000" step="1" inputmode="numeric" aria-describedby="donate-amount-hint" required>
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="donate-message">{{ __('govuk_alpha_wallet.donate.message_label') }}</label>
                <div id="donate-message-hint" class="govuk-hint">{{ __('govuk_alpha_wallet.donate.message_hint') }}</div>
                <input class="govuk-input" id="donate-message" name="message" type="text" maxlength="255" aria-describedby="donate-message-hint">
            </div>
            <button class="govuk-button" data-module="govuk-button">
                @if (!empty($recipientResults) && $donateTarget === 'user')
                    {{ __('govuk_alpha_wallet.donate.button_member', ['name' => trim((string) ($recipientResults[0]['name'] ?? '')) !== '' ? $recipientResults[0]['name'] : __('govuk_alpha.members.unknown_member')]) }}
                @else
                    {{ __('govuk_alpha_wallet.donate.button_fund') }}
                @endif
            </button>
        </form>
    </section>

    <p class="govuk-body govuk-!-margin-top-7">
        <a class="govuk-link" href="{{ route('govuk-alpha.wallet.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_wallet.footer.wallet_link') }}</a>
    </p>
@endsection
