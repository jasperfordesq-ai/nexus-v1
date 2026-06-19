{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $expenses = $expenses ?? [];
        $organizations = $organizations ?? [];
        $expenseTypes = $expenseTypes ?? ['travel', 'meals', 'supplies', 'equipment', 'parking', 'other'];
        $stats = $stats ?? ['total_claimed' => 0, 'total_approved' => 0, 'total_paid' => 0];
        $status = $status ?? null;
        $formatDate = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y') : null;
        $money = fn ($value): string => number_format((float) $value, 2);

        $typeLabelKey = [
            'travel' => 'govuk_alpha_volunteering.expenses.type_travel',
            'meals' => 'govuk_alpha_volunteering.expenses.type_meals',
            'supplies' => 'govuk_alpha_volunteering.expenses.type_supplies',
            'equipment' => 'govuk_alpha_volunteering.expenses.type_equipment',
            'parking' => 'govuk_alpha_volunteering.expenses.type_parking',
            'other' => 'govuk_alpha_volunteering.expenses.type_other',
        ];
        $statusLabelKey = [
            'pending' => 'govuk_alpha_volunteering.expenses.status_pending',
            'approved' => 'govuk_alpha_volunteering.expenses.status_approved',
            'rejected' => 'govuk_alpha_volunteering.expenses.status_rejected',
            'paid' => 'govuk_alpha_volunteering.expenses.status_paid',
        ];
        $statusTag = [
            'pending' => 'govuk-tag--yellow',
            'approved' => 'govuk-tag--green',
            'rejected' => 'govuk-tag--red',
            'paid' => 'govuk-tag--blue',
        ];

        $successMsg = [
            'expense-submitted' => 'govuk_alpha_volunteering.expenses.success_submitted',
        ];
        $errorMsg = [
            'expense-org-required' => 'govuk_alpha_volunteering.expenses.error_org_required',
            'expense-amount-invalid' => 'govuk_alpha_volunteering.expenses.error_amount_invalid',
            'expense-description-required' => 'govuk_alpha_volunteering.expenses.error_description_required',
            'expense-validation' => 'govuk_alpha_volunteering.expenses.error_validation',
            'expense-forbidden' => 'govuk_alpha_volunteering.expenses.error_forbidden',
            'expense-not-found' => 'govuk_alpha_volunteering.expenses.error_not_found',
            'expense-failed' => 'govuk_alpha_volunteering.expenses.error_failed',
        ];
        $orgError = $status === 'expense-org-required';
        $amountError = $status === 'expense-amount-invalid';
        $descriptionError = $status === 'expense-description-required';
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_volunteering') }}</a>

    @if (isset($successMsg[$status]))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="expenses-success-title">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="expenses-success-title">{{ __('govuk_alpha_volunteering.shared.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __($successMsg[$status]) }}</p></div>
        </div>
    @elseif (isset($errorMsg[$status]))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_volunteering.shared.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        @if ($orgError)
                            <li><a href="#organization_id">{{ __($errorMsg[$status]) }}</a></li>
                        @elseif ($amountError)
                            <li><a href="#amount">{{ __($errorMsg[$status]) }}</a></li>
                        @elseif ($descriptionError)
                            <li><a href="#description">{{ __($errorMsg[$status]) }}</a></li>
                        @else
                            <li>{{ __($errorMsg[$status]) }}</li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ __('govuk_alpha_volunteering.expenses.caption') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_volunteering.expenses.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_volunteering.expenses.description') }}</p>

    {{-- Totals --}}
    @if (!empty($expenses))
        <h2 class="govuk-heading-l">{{ __('govuk_alpha_volunteering.expenses.stats_title') }}</h2>
        <dl class="nexus-alpha-stat-grid govuk-!-margin-bottom-6">
            <div class="nexus-alpha-stat">
                <dt>{{ __('govuk_alpha_volunteering.expenses.stat_total_claimed') }}</dt>
                <dd>{{ $money($stats['total_claimed'] ?? 0) }}</dd>
            </div>
            <div class="nexus-alpha-stat">
                <dt>{{ __('govuk_alpha_volunteering.expenses.stat_approved') }}</dt>
                <dd>{{ $money($stats['total_approved'] ?? 0) }}</dd>
            </div>
            <div class="nexus-alpha-stat">
                <dt>{{ __('govuk_alpha_volunteering.expenses.stat_paid') }}</dt>
                <dd>{{ $money($stats['total_paid'] ?? 0) }}</dd>
            </div>
        </dl>
    @endif

    {{-- Submit form --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha_volunteering.expenses.form_title') }}</h2>
    <p class="govuk-body">{{ __('govuk_alpha_volunteering.expenses.form_intro') }}</p>

    @if (empty($organizations))
        <div class="govuk-inset-text">{{ __('govuk_alpha_volunteering.expenses.no_organisation') }}</div>
    @else
        <form method="post" action="{{ route('govuk-alpha.volunteering.expenses.submit', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-8">
            @csrf

            <div class="govuk-form-group{{ $orgError ? ' govuk-form-group--error' : '' }}">
                <label class="govuk-label" for="organization_id">{{ __('govuk_alpha_volunteering.expenses.org_label') }}</label>
                <div id="organization_id-hint" class="govuk-hint">{{ __('govuk_alpha_volunteering.expenses.org_hint') }}</div>
                @if ($orgError)
                    <p id="organization_id-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_volunteering.shared.error_title') }}:</span> {{ __('govuk_alpha_volunteering.expenses.error_org_required') }}</p>
                @endif
                <select class="govuk-select" id="organization_id" name="organization_id" aria-describedby="organization_id-hint{{ $orgError ? ' organization_id-error' : '' }}" required>
                    <option value="">{{ __('govuk_alpha_volunteering.expenses.org_select') }}</option>
                    @foreach ($organizations as $org)
                        <option value="{{ (int) ($org['id'] ?? 0) }}">{{ $org['name'] ?? '' }}</option>
                    @endforeach
                </select>
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label" for="expense_type">{{ __('govuk_alpha_volunteering.expenses.type_label') }}</label>
                <select class="govuk-select" id="expense_type" name="expense_type">
                    @foreach ($expenseTypes as $type)
                        <option value="{{ $type }}">{{ isset($typeLabelKey[$type]) ? __($typeLabelKey[$type]) : \Illuminate\Support\Str::headline($type) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="govuk-form-group{{ $amountError ? ' govuk-form-group--error' : '' }}">
                <label class="govuk-label" for="amount">{{ __('govuk_alpha_volunteering.expenses.amount_label') }}</label>
                <div id="amount-hint" class="govuk-hint">{{ __('govuk_alpha_volunteering.expenses.amount_hint') }}</div>
                @if ($amountError)
                    <p id="amount-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_volunteering.shared.error_title') }}:</span> {{ __('govuk_alpha_volunteering.expenses.error_amount_invalid') }}</p>
                @endif
                <input class="govuk-input govuk-input--width-10" id="amount" name="amount" type="number" min="0.01" step="0.01" inputmode="decimal" aria-describedby="amount-hint{{ $amountError ? ' amount-error' : '' }}" required>
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label" for="currency">{{ __('govuk_alpha_volunteering.expenses.currency_label') }} {{ __('govuk_alpha_volunteering.shared.optional') }}</label>
                <div id="currency-hint" class="govuk-hint">{{ __('govuk_alpha_volunteering.expenses.currency_hint') }}</div>
                <input class="govuk-input govuk-input--width-5" id="currency" name="currency" type="text" maxlength="10" aria-describedby="currency-hint">
            </div>

            <div class="govuk-form-group{{ $descriptionError ? ' govuk-form-group--error' : '' }}">
                <label class="govuk-label" for="description">{{ __('govuk_alpha_volunteering.expenses.description_label') }}</label>
                <div id="description-hint" class="govuk-hint">{{ __('govuk_alpha_volunteering.expenses.description_hint') }}</div>
                @if ($descriptionError)
                    <p id="description-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_volunteering.shared.error_title') }}:</span> {{ __('govuk_alpha_volunteering.expenses.error_description_required') }}</p>
                @endif
                <textarea class="govuk-textarea" id="description" name="description" rows="3" aria-describedby="description-hint{{ $descriptionError ? ' description-error' : '' }}" required></textarea>
            </div>

            <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_volunteering.expenses.submit_button') }}</button>
        </form>
    @endif

    {{-- Existing claims --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha_volunteering.expenses.list_title') }}</h2>
    @if (empty($expenses))
        <div class="govuk-inset-text">{{ __('govuk_alpha_volunteering.expenses.empty') }}</div>
    @else
        <table class="govuk-table">
            <caption class="govuk-visually-hidden">{{ __('govuk_alpha_volunteering.expenses.list_title') }}</caption>
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_volunteering.expenses.col_type') }}</th>
                    <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_volunteering.expenses.col_amount') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_volunteering.expenses.col_description') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_volunteering.expenses.col_status') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_volunteering.expenses.col_submitted') }}</th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                @foreach ($expenses as $expense)
                    @php
                        $expType = (string) ($expense['expense_type'] ?? '');
                        $expStatus = (string) ($expense['status'] ?? 'pending');
                        $sTag = $statusTag[$expStatus] ?? 'govuk-tag--grey';
                        $sLabelKey = $statusLabelKey[$expStatus] ?? 'govuk_alpha_volunteering.expenses.status_pending';
                        $currency = (string) ($expense['currency'] ?? '');
                        $reviewNotes = (string) ($expense['review_notes'] ?? '');
                    @endphp
                    <tr class="govuk-table__row">
                        <td class="govuk-table__cell">{{ isset($typeLabelKey[$expType]) ? __($typeLabelKey[$expType]) : \Illuminate\Support\Str::headline($expType) }}</td>
                        <td class="govuk-table__cell govuk-table__cell--numeric">{{ $currency !== '' ? $currency . ' ' : '' }}{{ $money($expense['amount'] ?? 0) }}</td>
                        <td class="govuk-table__cell">
                            {{ $expense['description'] ?? '' }}
                            @if ($reviewNotes !== '')
                                <br><span class="govuk-hint govuk-!-margin-bottom-0">{{ __('govuk_alpha_volunteering.expenses.col_notes') }}: {{ $reviewNotes }}</span>
                            @endif
                        </td>
                        <td class="govuk-table__cell"><strong class="govuk-tag {{ $sTag }}">{{ __($sLabelKey) }}</strong></td>
                        <td class="govuk-table__cell">{{ $formatDate($expense['submitted_at'] ?? null) ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
