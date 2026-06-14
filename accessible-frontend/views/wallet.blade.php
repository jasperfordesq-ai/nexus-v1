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
    @endphp

    <span class="govuk-caption-l">{{ __('govuk_alpha.wallet.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.wallet.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.wallet.description') }}</p>

    @if ($status === 'transfer-sent')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-live="polite" aria-labelledby="wallet-status-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="wallet-status-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.wallet.sent') }}</p>
            </div>
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
        @if (empty($transactions))
            <p class="govuk-inset-text">{{ __('govuk_alpha.wallet.history_empty') }}</p>
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
        @endif
    </section>
@endsection
