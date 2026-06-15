{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $member = $member ?? [];
        $balance = (int) ($balance ?? 0);
        $viewerEnabled = (bool) ($viewerEnabled ?? false);
        $mName = trim((string) ($member['name'] ?? '')) ?: __('govuk_alpha.federation.member.caption');
        $memberId = (int) ($member['id'] ?? 0);
        $memberTenantId = (int) ($member['tenant_id'] ?? 0);
        $statusKey = (string) ($status ?? '');
        $statusText = $statusKey !== '' ? __('govuk_alpha.fed2.transfer.status.' . $statusKey) : '';
        $backHref = route('govuk-alpha.federation.members.show', ['tenantSlug' => $tenantSlug, 'id' => $memberId, 'tenant_id' => $memberTenantId]);
    @endphp

    <a href="{{ $backHref }}" class="govuk-back-link">{{ __('govuk_alpha.fed2.transfer.back') }}</a>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-xl">{{ __('govuk_alpha.fed2.transfer.caption') }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.fed2.transfer.title') }}</h1>
            <p class="govuk-body-l">{{ __('govuk_alpha.fed2.transfer.description') }}</p>

            @if ($statusText !== '')
                <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <ul class="govuk-list govuk-error-summary__list">
                                <li><a href="#amount">{{ $statusText }}</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <dl class="govuk-summary-list govuk-!-margin-bottom-6">
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha.fed2.transfer.recipient_label') }}</dt>
                    <dd class="govuk-summary-list__value">{{ $mName }}</dd>
                </div>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha.fed2.transfer.community_label') }}</dt>
                    <dd class="govuk-summary-list__value">{{ $member['tenant_name'] ?? '' }}</dd>
                </div>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha.fed2.transfer.balance_label') }}</dt>
                    <dd class="govuk-summary-list__value">{{ __('govuk_alpha.fed2.transfer.balance_value', ['amount' => $balance]) }}</dd>
                </div>
            </dl>

            @if (!$viewerEnabled)
                <p class="govuk-inset-text">{{ __('govuk_alpha.fed2.transfer.not_enabled') }}</p>
                <a class="govuk-link" href="{{ route('govuk-alpha.federation.settings', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.fed2.transfer.optin_link') }}</a>
            @else
                <div class="govuk-warning-text">
                    <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                    <strong class="govuk-warning-text__text">
                        <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_title') }}</span>
                        {{ __('govuk_alpha.fed2.transfer.warning') }}
                    </strong>
                </div>

                <form method="post" action="{{ route('govuk-alpha.federation.transfer.store', ['tenantSlug' => $tenantSlug, 'id' => $memberId]) }}">
                    @csrf
                    <input type="hidden" name="receiver_tenant_id" value="{{ $memberTenantId }}">

                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--m" for="amount">{{ __('govuk_alpha.fed2.transfer.amount_label') }}</label>
                        <div id="amount-hint" class="govuk-hint">{{ __('govuk_alpha.fed2.transfer.amount_hint') }}</div>
                        <input class="govuk-input govuk-input--width-5" id="amount" name="amount" type="number" inputmode="numeric" min="1" max="100" step="1" aria-describedby="amount-hint">
                    </div>

                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--m" for="description">{{ __('govuk_alpha.fed2.transfer.description_field_label') }}</label>
                        <div id="description-hint" class="govuk-hint">{{ __('govuk_alpha.fed2.transfer.description_field_hint') }}</div>
                        <input class="govuk-input govuk-!-width-two-thirds" id="description" name="description" type="text" maxlength="500" aria-describedby="description-hint">
                    </div>

                    <div class="govuk-button-group">
                        <button type="submit" class="govuk-button govuk-button--warning" data-module="govuk-button">{{ __('govuk_alpha.fed2.transfer.submit') }}</button>
                        <a class="govuk-link" href="{{ $backHref }}">{{ __('govuk_alpha.fed2.transfer.cancel') }}</a>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection
