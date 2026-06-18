{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $balance = (float) ($wallet['balance'] ?? 0);
        $earned = (float) ($wallet['total_earned'] ?? 0);
        $spent = (float) ($wallet['total_spent'] ?? 0);
        $fmtHours = fn ($v): string => number_format((float) $v, 2);
        $txnDate = fn ($v): ?string => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('j F Y, g:ia') : null;
        $errorMessages = [
            'invalid' => __('govuk_alpha.wallet.errors.invalid'),
            'insufficient' => __('govuk_alpha.wallet.errors.insufficient'),
            'not-found' => __('govuk_alpha.wallet.errors.not_found'),
            'self' => __('govuk_alpha.wallet.errors.self'),
            'inactive' => __('govuk_alpha.wallet.errors.inactive'),
            'too-large' => __('govuk_alpha.wallet.errors.too_large'),
            'decimals' => __('govuk_alpha.wallet.errors.decimals'),
            'failed' => __('govuk_alpha.wallet.errors.failed'),
        ];
        // WAVE T1-WALLET: donate error map + transaction-filter state.
        $donateErrorMessages = [
            'invalid' => __('govuk_alpha.wallet_t1.errors.invalid'),
            'insufficient' => __('govuk_alpha.wallet_t1.errors.insufficient'),
            'not-found' => __('govuk_alpha.wallet_t1.errors.not_found'),
            'self' => __('govuk_alpha.wallet_t1.errors.self'),
            'too-large' => __('govuk_alpha.wallet_t1.errors.too_large'),
            'decimals' => __('govuk_alpha.wallet_t1.errors.decimals'),
            'failed' => __('govuk_alpha.wallet_t1.errors.failed'),
        ];
        $activeFilter = $txFilter ?? 'all';
        $currentPage = (int) ($txPage ?? 1);
        $fundBalance = (float) ($fund['balance'] ?? 0);
        $fundDonated = (float) ($fund['total_donated'] ?? 0);
    @endphp

    <span class="govuk-caption-l">{{ __('govuk_alpha.wallet.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.wallet.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.wallet.description') }}</p>

    <p class="govuk-body">
        <a class="govuk-link" href="{{ route('govuk-alpha.wallet.manage', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_wallet.nav.manage') }}</a>
    </p>

    @if ($status === 'transfer-sent')
        <div class="govuk-panel govuk-panel--confirmation">
            <h2 class="govuk-panel__title">{{ __('govuk_alpha.states.success_title') }}</h2>
            <div class="govuk-panel__body">{{ __('govuk_alpha.wallet.sent') }}</div>
        </div>
    @elseif ($status === 'transfer-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        <li><a href="#transfer">{{ $errorMessages[$transferError ?? 'failed'] ?? $errorMessages['failed'] }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
    @elseif ($status === 'donate-sent')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="wallet-donate-status-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="wallet-donate-status-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.wallet_t1.donate_success') }}</p>
            </div>
        </div>
    @elseif ($status === 'donate-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        <li><a href="#donate">{{ $donateErrorMessages[$donateError ?? 'failed'] ?? $donateErrorMessages['failed'] }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
    @elseif ($status === 'export-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        <li><a href="#transactions">{{ __('govuk_alpha.wallet_t1.export_failed') }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <dl class="nexus-alpha-stat-grid">
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha.wallet.balance_label') }}</dt>
            <dd>{{ __('govuk_alpha.wallet.hours_value', ['value' => $fmtHours($balance)]) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha.wallet.earned_label') }}</dt>
            <dd>{{ __('govuk_alpha.wallet.hours_value', ['value' => $fmtHours($earned)]) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha.wallet.spent_label') }}</dt>
            <dd>{{ __('govuk_alpha.wallet.hours_value', ['value' => $fmtHours($spent)]) }}</dd>
        </div>
    </dl>

    {{-- WAVE T1-WALLET: community fund balance (read-only) --}}
    @if ($fund !== null)
        <section aria-labelledby="fund-heading" id="community-fund">
            <h2 class="govuk-heading-l govuk-!-margin-top-7" id="fund-heading">{{ __('govuk_alpha.wallet_t1.fund_heading') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha.wallet_t1.fund_description') }}</p>
            <dl class="govuk-summary-list">
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha.wallet_t1.fund_balance_label') }}</dt>
                    <dd class="govuk-summary-list__value">{{ __('govuk_alpha.wallet.hours_value', ['value' => $fmtHours($fundBalance)]) }}</dd>
                </div>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha.wallet_t1.fund_donated_label') }}</dt>
                    <dd class="govuk-summary-list__value">{{ __('govuk_alpha.wallet.hours_value', ['value' => $fmtHours($fundDonated)]) }}</dd>
                </div>
            </dl>
        </section>
    @endif

    {{-- WAVE T1-WALLET: donate to the community fund --}}
    <section aria-labelledby="donate-heading" id="donate">
        <h2 class="govuk-heading-l govuk-!-margin-top-7" id="donate-heading">{{ __('govuk_alpha.wallet_t1.donate_title') }}</h2>
        <p class="govuk-body">{{ __('govuk_alpha.wallet_t1.donate_description') }}</p>

        {{-- Crystal-clear that this donates TIME CREDITS to a shared community pool —
             it is NOT a money donation. --}}
        <div class="govuk-inset-text" role="note">
            <p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha.vol_clarity.donate_credits_not_money') }}</p>
        </div>

        <div class="govuk-warning-text">
            <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
            <strong class="govuk-warning-text__text">
                <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.warning') }}</span>
                {{ __('govuk_alpha.wallet_t1.donate_warning') }}
            </strong>
        </div>

        <form method="post" action="{{ route('govuk-alpha.wallet.donate', ['tenantSlug' => $tenantSlug]) }}">
            @csrf
            <input type="hidden" name="target" value="community_fund">
            <div class="govuk-form-group">
                <label class="govuk-label" for="donate-amount">{{ __('govuk_alpha.wallet_t1.donate_amount_label') }}</label>
                <div id="donate-amount-hint" class="govuk-hint">{{ __('govuk_alpha.wallet_t1.donate_amount_hint') }}</div>
                <input class="govuk-input govuk-input--width-5" id="donate-amount" name="amount" type="number" min="1" max="1000" step="1" inputmode="numeric" aria-describedby="donate-amount-hint" required>
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="donate-message">{{ __('govuk_alpha.wallet_t1.donate_message_label') }}</label>
                <div id="donate-message-hint" class="govuk-hint">{{ __('govuk_alpha.wallet_t1.donate_message_hint') }}</div>
                <input class="govuk-input" id="donate-message" name="message" type="text" maxlength="255" aria-describedby="donate-message-hint">
            </div>
            <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.wallet_t1.donate_button') }}</button>
        </form>
    </section>

    <section aria-labelledby="transfer-heading" id="transfer">
        <h2 class="govuk-heading-l govuk-!-margin-top-7" id="transfer-heading">{{ __('govuk_alpha.wallet.transfer_title') }}</h2>
        <p class="govuk-body">{{ __('govuk_alpha.wallet.transfer_description') }}</p>

        <form method="get" action="{{ route('govuk-alpha.wallet.index', ['tenantSlug' => $tenantSlug]) }}">
            <div class="govuk-form-group">
                <label class="govuk-label" for="recipient_q">{{ __('govuk_alpha.wallet.search_label') }}</label>
                <div id="recipient-q-hint" class="govuk-hint">{{ __('govuk_alpha.wallet.search_hint') }}</div>
                {{-- The JS enhancement mounts an accessible autocomplete into this
                     container and removes the plain input + button below. With no
                     JavaScript, the plain search input + button are the fallback. --}}
                <div data-alpha-recipient-autocomplete
                     data-source="{{ route('govuk-alpha.wallet.recipients', ['tenantSlug' => $tenantSlug]) }}"
                     data-target="{{ route('govuk-alpha.wallet.index', ['tenantSlug' => $tenantSlug]) }}"></div>
                <input class="govuk-input govuk-!-width-two-thirds" id="recipient_q" name="recipient_q" type="search" value="{{ $recipientQuery ?? '' }}" aria-describedby="recipient-q-hint">
            </div>
            <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button" data-alpha-recipient-submit>{{ __('govuk_alpha.actions.search') }}</button>
        </form>

        @if (!empty($recipientResults) || ($recipientQuery ?? '') !== '')
            @if (empty($recipientResults))
                <p class="govuk-body">{{ __('govuk_alpha.wallet.search_empty') }}</p>
            @else
                <h3 class="govuk-heading-m">{{ __('govuk_alpha.wallet.recipient_heading') }}</h3>
                @foreach ($recipientResults as $recipient)
                    @php
                        $recipientName = trim((string) ($recipient['name'] ?? '')) ?: __('govuk_alpha.members.unknown_member');
                        // Disambiguate same-named members: location + "Member since …".
                        $recipientLoc = trim((string) ($recipient['location'] ?? ''));
                        $recipientSince = !empty($recipient['created_at'])
                            ? \Illuminate\Support\Carbon::parse($recipient['created_at'])->translatedFormat('F Y')
                            : null;
                        $recipientMeta = array_values(array_filter([
                            $recipientLoc !== '' ? $recipientLoc : null,
                            $recipientSince ? __('govuk_alpha.wallet.member_since', ['date' => $recipientSince]) : null,
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
                            <div class="govuk-form-group govuk-!-margin-bottom-3">
                                <label class="govuk-label" for="amount-{{ $recipient['id'] }}">{{ __('govuk_alpha.wallet.amount_label') }}</label>
                                <div id="amount-hint-{{ $recipient['id'] }}" class="govuk-hint">{{ __('govuk_alpha.wallet.amount_hint') }}</div>
                                <input class="govuk-input govuk-input--width-5" id="amount-{{ $recipient['id'] }}" name="amount" type="number" min="0.25" max="1000" step="0.25" inputmode="decimal" aria-describedby="amount-hint-{{ $recipient['id'] }}" required>
                            </div>
                            <div class="govuk-form-group govuk-!-margin-bottom-3">
                                <label class="govuk-label" for="note-{{ $recipient['id'] }}">{{ __('govuk_alpha.wallet.note_label') }}</label>
                                <div id="note-hint-{{ $recipient['id'] }}" class="govuk-hint">{{ __('govuk_alpha.wallet.note_hint') }}</div>
                                <input class="govuk-input" id="note-{{ $recipient['id'] }}" name="note" type="text" maxlength="255" aria-describedby="note-hint-{{ $recipient['id'] }}">
                            </div>
                            <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.wallet.send_button', ['name' => $recipientName]) }}</button>
                        </form>
                    </div>
                @endforeach
            @endif
        @endif
    </section>

    <section aria-labelledby="transactions-heading" id="transactions">
        <h2 class="govuk-heading-l govuk-!-margin-top-7" id="transactions-heading">{{ __('govuk_alpha.wallet.history_title') }}</h2>

        {{-- WAVE T1-WALLET: export the caller's own history as CSV (no-JS download) --}}
        <p class="govuk-body">
            <a class="govuk-link" href="{{ route('govuk-alpha.wallet.export', ['tenantSlug' => $tenantSlug]) }}" download>{{ __('govuk_alpha.wallet_t1.export_button') }}</a>
            <span class="govuk-hint govuk-!-display-inline">— {{ __('govuk_alpha.wallet_t1.export_description') }}</span>
        </p>

        {{-- WAVE T1-WALLET: server-rendered filter tabs (plain links, no JS panels) --}}
        <div class="govuk-tabs govuk-!-margin-top-4" aria-label="{{ __('govuk_alpha.wallet_t1.filter_heading') }}">
            <h3 class="govuk-tabs__title">{{ __('govuk_alpha.wallet_t1.filter_heading') }}</h3>
            <ul class="govuk-tabs__list">
                @php
                    $filterTabs = [
                        'all' => __('govuk_alpha.wallet_t1.filter_all'),
                        'earned' => __('govuk_alpha.wallet_t1.filter_earned'),
                        'spent' => __('govuk_alpha.wallet_t1.filter_spent'),
                        'pending' => __('govuk_alpha.wallet_t1.filter_pending'),
                    ];
                @endphp
                @foreach ($filterTabs as $tabKey => $tabLabel)
                    <li class="govuk-tabs__list-item{{ $activeFilter === $tabKey ? ' govuk-tabs__list-item--selected' : '' }}">
                        <a class="govuk-tabs__tab" href="{{ route('govuk-alpha.wallet.index', $tabKey === 'all' ? ['tenantSlug' => $tenantSlug] : ['tenantSlug' => $tenantSlug, 'filter' => $tabKey]) }}#transactions" @if ($activeFilter === $tabKey) aria-current="page" @endif>{{ $tabLabel }}</a>
                    </li>
                @endforeach
            </ul>
        </div>

        @if (empty($transactions))
            <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.wallet.history_empty') }}</p></div>
        @else
            <table class="govuk-table">
                <caption class="govuk-table__caption govuk-table__caption--s govuk-visually-hidden">{{ __('govuk_alpha.wallet.history_title') }}</caption>
                <thead class="govuk-table__head">
                    <tr class="govuk-table__row">
                        <th scope="col" class="govuk-table__header">{{ __('govuk_alpha.wallet.when_label') }}</th>
                        <th scope="col" class="govuk-table__header">{{ __('govuk_alpha.wallet.with_label') }}</th>
                        <th scope="col" class="govuk-table__header">{{ __('govuk_alpha.wallet.description_label') }}</th>
                        <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha.wallet.amount_col') }}</th>
                    </tr>
                </thead>
                <tbody class="govuk-table__body">
                    @foreach ($transactions as $txn)
                        @php
                            $isCredit = ($txn['type'] ?? '') === 'credit';
                            $other = trim((string) ($txn['other_user']['name'] ?? '')) ?: __('govuk_alpha.members.unknown_member');
                            $amt = (float) ($txn['amount'] ?? 0);
                            $signed = ($isCredit ? '+' : '−') . $fmtHours($amt);
                            $direction = $isCredit ? __('govuk_alpha.wallet.received') : __('govuk_alpha.wallet.sent_short');
                        @endphp
                        <tr class="govuk-table__row">
                            <td class="govuk-table__cell">{{ $txnDate($txn['created_at'] ?? null) ?? '—' }}</td>
                            <td class="govuk-table__cell">{{ $other }}</td>
                            <td class="govuk-table__cell">{{ trim((string) ($txn['description'] ?? '')) !== '' ? $txn['description'] : '—' }}</td>
                            <td class="govuk-table__cell govuk-table__cell--numeric">
                                {{ $signed }}<span class="govuk-visually-hidden"> {{ $direction }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- WAVE T1-WALLET: GOV.UK pagination (previous / next page links, no JS) --}}
            @if ($currentPage > 1 || $txHasMore)
                <nav class="govuk-pagination" aria-label="{{ __('govuk_alpha.wallet.history_title') }}">
                    @if ($currentPage > 1)
                        <div class="govuk-pagination__prev">
                            <a class="govuk-link govuk-pagination__link" href="{{ route('govuk-alpha.wallet.index', array_filter(['tenantSlug' => $tenantSlug, 'filter' => $activeFilter === 'all' ? null : $activeFilter, 'page' => $currentPage - 1 > 1 ? $currentPage - 1 : null])) }}#transactions" rel="prev">
                                <svg class="govuk-pagination__icon govuk-pagination__icon--prev" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                                    <path d="m6.5938-0.0078125-6.7266 6.7266 6.7441 6.4062 1.377-1.449-4.1856-3.9768h12.896v-2h-12.984l4.2931-4.293-1.414-1.414z"></path>
                                </svg>
                                <span class="govuk-pagination__link-title">{{ __('govuk_alpha.wallet_t1.pagination_previous') }}</span>
                            </a>
                        </div>
                    @endif
                    @if ($txHasMore)
                        <div class="govuk-pagination__next">
                            <a class="govuk-link govuk-pagination__link" href="{{ route('govuk-alpha.wallet.index', array_filter(['tenantSlug' => $tenantSlug, 'filter' => $activeFilter === 'all' ? null : $activeFilter, 'page' => $currentPage + 1])) }}#transactions" rel="next">
                                <span class="govuk-pagination__link-title">{{ __('govuk_alpha.wallet_t1.pagination_next') }}</span>
                                <svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                                    <path d="m8.107-0.0078125-1.4136 1.414 4.2926 4.293h-12.986v2h12.896l-4.1855 3.9766 1.377 1.4492 6.7441-6.4062-6.7246-6.7266z"></path>
                                </svg>
                            </a>
                        </div>
                    @endif
                </nav>
            @endif
        @endif
    </section>
@endsection
