{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $orgId = (int) ($orgId ?? 0);
        $orgName = (string) ($orgName ?? '');
        $summary = $summary ?? [];
        $transactions = $transactions ?? [];
        $autoPayEnabled = (bool) ($autoPayEnabled ?? false);
        $status = $status ?? null;
        $formatDateTime = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y, g:ia') : null;
        $successStates = [
            'deposit-made' => 'govuk_alpha_volunteering.org_wallet.deposit_made',
            'autopay-enabled' => 'govuk_alpha_volunteering.org_wallet.autopay_enabled',
            'autopay-disabled' => 'govuk_alpha_volunteering.org_wallet.autopay_disabled',
        ];
        $errorStates = [
            'deposit-failed' => 'govuk_alpha_volunteering.org_wallet.deposit_failed',
            'deposit-amount-invalid' => 'govuk_alpha_volunteering.org_wallet.deposit_amount_invalid',
            'autopay-failed' => 'govuk_alpha_volunteering.org_wallet.autopay_failed',
        ];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.volunteering.org.dashboard', ['tenantSlug' => $tenantSlug, 'id' => $orgId]) }}">{{ __('govuk_alpha_volunteering.shared.back_to_dashboard') }}</a>

    @if (isset($successStates[$status]))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="wallet-success-title">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="wallet-success-title">{{ __('govuk_alpha_volunteering.shared.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __($successStates[$status]) }}</p></div>
        </div>
    @elseif (isset($errorStates[$status]))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_volunteering.shared.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p>{{ __($errorStates[$status]) }}</p></div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ $orgName }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_volunteering.org_wallet.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_volunteering.org_wallet.description') }}</p>

    {{-- Balance summary --}}
    <dl class="nexus-alpha-stat-grid">
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_volunteering.org_wallet.balance') }}</dt>
            <dd>{{ number_format((float) ($summary['balance'] ?? 0), 1) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_volunteering.org_wallet.total_deposited') }}</dt>
            <dd>{{ number_format((float) ($summary['total_deposited'] ?? 0), 1) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_volunteering.org_wallet.total_paid_out') }}</dt>
            <dd>{{ number_format((float) ($summary['total_paid_out'] ?? 0), 1) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_volunteering.org_wallet.pending_hours_value') }}</dt>
            <dd>{{ number_format((float) ($summary['pending_hours_value'] ?? 0), 1) }}</dd>
        </div>
    </dl>

    {{-- Auto-pay toggle --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha_volunteering.org_wallet.auto_pay_title') }}</h2>
    <p class="govuk-body">{{ __('govuk_alpha_volunteering.org_wallet.auto_pay_hint') }}</p>
    <p class="govuk-body">
        <strong class="govuk-tag {{ $autoPayEnabled ? 'govuk-tag--green' : 'govuk-tag--grey' }}">
            {{ $autoPayEnabled ? __('govuk_alpha_volunteering.org_wallet.auto_pay_on') : __('govuk_alpha_volunteering.org_wallet.auto_pay_off') }}
        </strong>
    </p>
    <form method="post" action="{{ route('govuk-alpha.volunteering.org.wallet.auto-pay', ['tenantSlug' => $tenantSlug, 'id' => $orgId]) }}" class="govuk-!-margin-bottom-8">
        @csrf
        <input type="hidden" name="enabled" value="{{ $autoPayEnabled ? '0' : '1' }}">
        <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">
            {{ $autoPayEnabled ? __('govuk_alpha_volunteering.org_wallet.auto_pay_disable') : __('govuk_alpha_volunteering.org_wallet.auto_pay_enable') }}
        </button>
    </form>

    {{-- Deposit form --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha_volunteering.org_wallet.deposit_title') }}</h2>
    <div class="govuk-warning-text">
        <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
        <strong class="govuk-warning-text__text">
            <span class="govuk-visually-hidden">{{ __('govuk_alpha_volunteering.shared.error_title') }}</span>
            {{ __('govuk_alpha_volunteering.org_wallet.deposit_warning') }}
        </strong>
    </div>
    <form method="post" action="{{ route('govuk-alpha.volunteering.org.wallet.deposit', ['tenantSlug' => $tenantSlug, 'id' => $orgId]) }}" class="govuk-!-margin-bottom-8">
        @csrf
        <div class="govuk-form-group">
            <label class="govuk-label" for="amount">{{ __('govuk_alpha_volunteering.org_wallet.deposit_amount_label') }}</label>
            <div id="amount-hint" class="govuk-hint">{{ __('govuk_alpha_volunteering.org_wallet.deposit_amount_hint') }}</div>
            <input class="govuk-input govuk-input--width-5" id="amount" name="amount" type="text" inputmode="numeric" pattern="[0-9]*" aria-describedby="amount-hint" required>
        </div>
        <div class="govuk-form-group">
            <label class="govuk-label" for="note">{{ __('govuk_alpha_volunteering.org_wallet.deposit_note_label') }} {{ __('govuk_alpha_volunteering.shared.optional') }}</label>
            <div id="note-hint" class="govuk-hint">{{ __('govuk_alpha_volunteering.org_wallet.deposit_note_hint') }}</div>
            <input class="govuk-input" id="note" name="note" type="text" maxlength="255" aria-describedby="note-hint">
        </div>
        <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_volunteering.org_wallet.deposit_button') }}</button>
    </form>

    {{-- Transactions --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha_volunteering.org_wallet.transactions_title') }}</h2>
    @if (empty($transactions))
        <div class="govuk-inset-text">{{ __('govuk_alpha_volunteering.org_wallet.transactions_empty') }}</div>
    @else
        <table class="govuk-table">
            <caption class="govuk-visually-hidden">{{ __('govuk_alpha_volunteering.org_wallet.transactions_title') }}</caption>
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_volunteering.org_wallet.transaction_when') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_volunteering.org_wallet.transaction_type') }}</th>
                    <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_volunteering.org_wallet.transaction_amount') }}</th>
                    <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_volunteering.org_wallet.transaction_balance_after') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_volunteering.org_wallet.transaction_description') }}</th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                @foreach ($transactions as $txn)
                    <tr class="govuk-table__row">
                        <td class="govuk-table__cell">{{ $formatDateTime($txn['created_at'] ?? null) ?? '—' }}</td>
                        <td class="govuk-table__cell">{{ \Illuminate\Support\Str::headline((string) ($txn['type'] ?? '')) }}</td>
                        <td class="govuk-table__cell govuk-table__cell--numeric">{{ number_format((float) ($txn['amount'] ?? 0), 1) }}</td>
                        <td class="govuk-table__cell govuk-table__cell--numeric">{{ number_format((float) ($txn['balance_after'] ?? 0), 1) }}</td>
                        <td class="govuk-table__cell">{{ $txn['description'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
