{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $givingDays = $givingDays ?? [];
        $donations = $donations ?? [];
        $paymentMethods = $paymentMethods ?? ['bank_transfer', 'paypal'];
        $stats = $stats ?? ['total_raised' => 0, 'total_donors' => 0, 'active_campaigns' => 0];
        $status = $status ?? null;
        $donateError = $donateError ?? null;
        $formatDate = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y') : null;
        $formatDateTime = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y, g:ia') : null;
        $money = fn ($value): string => number_format((float) $value, 2);
        $statusLabelKey = [
            'pending' => 'govuk_alpha_volunteering.donations.status_pending',
            'completed' => 'govuk_alpha_volunteering.donations.status_completed',
            'failed' => 'govuk_alpha_volunteering.donations.status_failed',
            'refunded' => 'govuk_alpha_volunteering.donations.status_refunded',
        ];
        $statusTag = [
            'pending' => 'govuk-tag--yellow',
            'completed' => 'govuk-tag--green',
            'failed' => 'govuk-tag--red',
            'refunded' => 'govuk-tag--grey',
        ];
        $methodLabelKey = [
            'bank_transfer' => 'govuk_alpha_volunteering.donations.method_bank_transfer',
            'paypal' => 'govuk_alpha_volunteering.donations.method_paypal',
            'card' => 'govuk_alpha_volunteering.donations.method_card',
            'stripe' => 'govuk_alpha_volunteering.donations.method_stripe',
        ];
        $dayStatusKey = [
            'active' => 'govuk_alpha_volunteering.donations.day_status_active',
            'upcoming' => 'govuk_alpha_volunteering.donations.day_status_upcoming',
            'ended' => 'govuk_alpha_volunteering.donations.day_status_ended',
        ];
        $dayStatusTag = [
            'active' => 'govuk-tag--green',
            'upcoming' => 'govuk-tag--blue',
            'ended' => 'govuk-tag--grey',
        ];
        $errorLabelKey = [
            'amount' => 'govuk_alpha_volunteering.donations.error_amount',
            'amount-max' => 'govuk_alpha_volunteering.donations.error_amount_max',
            'validation' => 'govuk_alpha_volunteering.donations.error_validation',
        ];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_volunteering') }}</a>

    @if ($status === 'donate-recorded')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="donations-success-title">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="donations-success-title">{{ __('govuk_alpha_volunteering.shared.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_volunteering.donations.donate_recorded') }}</p></div>
        </div>
    @elseif ($status === 'donate-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_volunteering.shared.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        <li><a href="#donate-amount">{{ $donateError !== null && isset($errorLabelKey[$donateError]) ? __($errorLabelKey[$donateError]) : __('govuk_alpha_volunteering.donations.donate_failed') }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ __('govuk_alpha_volunteering.donations.caption') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_volunteering.donations.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_volunteering.donations.description') }}</p>

    {{-- What donations actually are — money, NOT time credits. Mirrors the React
         intro card, which exists to clear up a persistent confusion. --}}
    <div class="govuk-inset-text" role="note" aria-labelledby="donations-intro-heading">
        <h2 class="govuk-heading-s govuk-!-margin-bottom-2" id="donations-intro-heading">{{ __('govuk_alpha_volunteering.donations.intro_title') }}</h2>
        <p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha_volunteering.donations.intro_desc') }}</p>
    </div>

    {{-- Stats --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha_volunteering.donations.stats_title') }}</h2>
    <dl class="nexus-alpha-stat-grid">
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_volunteering.donations.stat_total_raised') }}</dt>
            <dd>{{ $money($stats['total_raised'] ?? 0) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_volunteering.donations.stat_total_donors') }}</dt>
            <dd>{{ (int) ($stats['total_donors'] ?? 0) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_volunteering.donations.stat_active_campaigns') }}</dt>
            <dd>{{ (int) ($stats['active_campaigns'] ?? 0) }}</dd>
        </div>
    </dl>

    {{-- Active giving days --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha_volunteering.donations.giving_days_title') }}</h2>
    @if (empty($givingDays))
        <div class="govuk-inset-text">{{ __('govuk_alpha_volunteering.donations.giving_days_empty') }}</div>
    @else
        <ul class="nexus-alpha-card-list govuk-list">
            @foreach ($givingDays as $day)
                @php
                    $goal = (float) ($day['goal_amount'] ?? $day['target_amount'] ?? 0);
                    $raised = (float) ($day['raised_amount'] ?? 0);
                    $pct = $goal > 0 ? min(100, (int) round(($raised / $goal) * 100)) : 0;
                    $dayState = (string) ($day['status'] ?? (!empty($day['is_active']) ? 'active' : 'ended'));
                    $dayId = (int) ($day['id'] ?? 0);
                    $endValue = $day['end_date'] ?? ($day['ends_at'] ?? null);
                @endphp
                <li class="nexus-alpha-card">
                    <h3 class="govuk-heading-m govuk-!-margin-bottom-1">
                        {{ $day['title'] ?? ($day['name'] ?? '') }}
                        <strong class="govuk-tag {{ $dayStatusTag[$dayState] ?? 'govuk-tag--grey' }}">{{ __($dayStatusKey[$dayState] ?? 'govuk_alpha_volunteering.donations.day_status_ended') }}</strong>
                    </h3>
                    @if (!empty($day['description']))
                        <p class="govuk-body">{{ $day['description'] }}</p>
                    @endif
                    <p class="govuk-body govuk-!-margin-bottom-1">{{ __('govuk_alpha_volunteering.donations.progress_label', ['raised' => $money($raised), 'goal' => $money($goal)]) }}</p>
                    <progress max="100" value="{{ $pct }}" aria-label="{{ __('govuk_alpha_volunteering.donations.progress_aria', ['percent' => $pct]) }}">{{ $pct }}%</progress>
                    <p class="nexus-alpha-meta govuk-!-margin-top-1">
                        <span>{{ trans_choice('govuk_alpha_volunteering.donations.donors_count', (int) ($day['donor_count'] ?? 0), ['count' => (int) ($day['donor_count'] ?? 0)]) }}</span>
                        @if ($endValue)
                            <span>{{ __('govuk_alpha_volunteering.donations.ends_label') }}: {{ $formatDate($endValue) }}</span>
                        @endif
                    </p>
                    @if ($dayId > 0)
                        <a class="govuk-link" href="#donate-form" data-vol-giving-day="{{ $dayId }}">{{ __('govuk_alpha_volunteering.donations.donate_to_day') }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    {{-- My donation history --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha_volunteering.donations.history_title') }}</h2>
    @if (empty($donations))
        <div class="govuk-inset-text">{{ __('govuk_alpha_volunteering.donations.history_empty') }}</div>
    @else
        <table class="govuk-table">
            <caption class="govuk-visually-hidden">{{ __('govuk_alpha_volunteering.donations.history_title') }}</caption>
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_volunteering.donations.col_amount') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_volunteering.donations.col_status') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_volunteering.donations.col_method') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_volunteering.donations.col_when') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_volunteering.donations.col_message') }}</th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                @foreach ($donations as $donation)
                    @php
                        $dStatus = (string) ($donation['status'] ?? 'pending');
                        $dMethod = (string) ($donation['payment_method'] ?? '');
                        $isAnon = !empty($donation['is_anonymous']);
                        $currency = $donation['currency'] ?? '';
                    @endphp
                    <tr class="govuk-table__row">
                        <td class="govuk-table__cell">{{ $money($donation['amount'] ?? 0) }} {{ $currency }}</td>
                        <td class="govuk-table__cell">
                            <strong class="govuk-tag {{ $statusTag[$dStatus] ?? 'govuk-tag--grey' }}">{{ __($statusLabelKey[$dStatus] ?? 'govuk_alpha_volunteering.donations.status_pending') }}</strong>
                            @if ($isAnon)
                                <strong class="govuk-tag govuk-tag--grey">{{ __('govuk_alpha_volunteering.donations.anonymous_tag') }}</strong>
                            @endif
                        </td>
                        <td class="govuk-table__cell">{{ isset($methodLabelKey[$dMethod]) ? __($methodLabelKey[$dMethod]) : ($dMethod !== '' ? $dMethod : '—') }}</td>
                        <td class="govuk-table__cell">{{ $formatDateTime($donation['created_at'] ?? null) ?? '—' }}</td>
                        <td class="govuk-table__cell">{{ $donation['message'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Donate form (offline: bank transfer / PayPal, recorded as pending) --}}
    <h2 class="govuk-heading-l" id="donate-form">{{ __('govuk_alpha_volunteering.donations.form_title') }}</h2>
    <p class="govuk-body">{{ __('govuk_alpha_volunteering.donations.form_intro') }}</p>
    <form id="donate" method="post" action="{{ route('govuk-alpha.volunteering.donations.store', ['tenantSlug' => $tenantSlug]) }}#donate" class="govuk-!-margin-bottom-8">
        @csrf

        <div class="govuk-form-group {{ $status === 'donate-failed' ? 'govuk-form-group--error' : '' }}">
            <label class="govuk-label govuk-label--s" for="donate-amount">{{ __('govuk_alpha_volunteering.donations.amount_label') }}</label>
            <div id="donate-amount-hint" class="govuk-hint">{{ __('govuk_alpha_volunteering.donations.amount_hint') }}</div>
            @if ($status === 'donate-failed' && in_array($donateError, ['amount', 'amount-max'], true))
                <p id="donate-amount-error" class="govuk-error-message">
                    <span class="govuk-visually-hidden">{{ __('govuk_alpha_volunteering.shared.error_title') }}:</span>
                    {{ __($errorLabelKey[$donateError]) }}
                </p>
            @endif
            <div class="govuk-input__wrapper">
                <div class="govuk-input__prefix" aria-hidden="true">&euro;</div>
                <input class="govuk-input govuk-input--width-10 {{ $status === 'donate-failed' ? 'govuk-input--error' : '' }}" id="donate-amount" name="amount" type="text" inputmode="decimal" spellcheck="false" aria-describedby="donate-amount-hint{{ ($status === 'donate-failed' && in_array($donateError, ['amount', 'amount-max'], true)) ? ' donate-amount-error' : '' }}">
            </div>
        </div>

        @if (!empty($givingDays))
            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="donate-giving-day">{{ __('govuk_alpha_volunteering.donations.campaign_label') }}</label>
                <div id="donate-giving-day-hint" class="govuk-hint">{{ __('govuk_alpha_volunteering.donations.campaign_hint') }}</div>
                <select class="govuk-select" id="donate-giving-day" name="giving_day_id" aria-describedby="donate-giving-day-hint">
                    <option value="0">{{ __('govuk_alpha_volunteering.donations.campaign_general') }}</option>
                    @foreach ($givingDays as $day)
                        @php $dayId = (int) ($day['id'] ?? 0); @endphp
                        @if ($dayId > 0)
                            <option value="{{ $dayId }}">{{ $day['title'] ?? ($day['name'] ?? '') }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
        @endif

        <div class="govuk-form-group">
            <fieldset class="govuk-fieldset" aria-describedby="donate-method-hint">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_volunteering.donations.method_label') }}</legend>
                <div id="donate-method-hint" class="govuk-hint">{{ __('govuk_alpha_volunteering.donations.method_hint') }}</div>
                <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                    @foreach ($paymentMethods as $method)
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="donate-method-{{ $method }}" name="payment_method" type="radio" value="{{ $method }}" @checked($loop->first)>
                            <label class="govuk-label govuk-radios__label" for="donate-method-{{ $method }}">{{ isset($methodLabelKey[$method]) ? __($methodLabelKey[$method]) : $method }}</label>
                        </div>
                    @endforeach
                </div>
            </fieldset>
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="donate-message">{{ __('govuk_alpha_volunteering.donations.message_label') }} {{ __('govuk_alpha_volunteering.shared.optional') }}</label>
            <div id="donate-message-hint" class="govuk-hint">{{ __('govuk_alpha_volunteering.donations.message_hint') }}</div>
            <textarea class="govuk-textarea" id="donate-message" name="message" rows="3" maxlength="500" aria-describedby="donate-message-hint"></textarea>
        </div>

        <div class="govuk-form-group">
            <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                <div class="govuk-checkboxes__item">
                    <input class="govuk-checkboxes__input" id="donate-anonymous" name="is_anonymous" type="checkbox" value="1" aria-describedby="donate-anonymous-hint">
                    <label class="govuk-label govuk-checkboxes__label" for="donate-anonymous">{{ __('govuk_alpha_volunteering.donations.anonymous_label') }}</label>
                    <div id="donate-anonymous-hint" class="govuk-hint govuk-checkboxes__hint">{{ __('govuk_alpha_volunteering.donations.anonymous_hint') }}</div>
                </div>
            </div>
        </div>

        <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_volunteering.donations.submit_button') }}</button>
    </form>
@endsection
