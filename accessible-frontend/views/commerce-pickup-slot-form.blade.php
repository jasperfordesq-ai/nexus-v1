{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $slot = $slot ?? null;
        $slotId = is_array($slot) ? (int) ($slot['id'] ?? 0) : 0;
        $statusMessages = [
            'slot-saved' => ['msg' => __('govuk_alpha_commerce.slots.status_slot_saved'), 'error' => false],
            'slot-save-failed' => ['msg' => __('govuk_alpha_commerce.slots.status_slot_save_failed'), 'error' => true],
        ];
        $statusEntry = $status !== null && isset($statusMessages[$status]) ? $statusMessages[$status] : null;
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.marketplace.slots', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_commerce.slots.back_to_slots') }}</a>

    @include('accessible-frontend::partials.commerce-marketplace-nav', ['commerceActiveTab' => 'slots'])

    @if ($statusEntry !== null && !$statusEntry['error'])
        <div class="govuk-notification-banner govuk-notification-banner--success" role="alert" aria-labelledby="govuk-notification-banner-title" data-module="govuk-notification-banner">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="govuk-notification-banner-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ $statusEntry['msg'] }}</p>
            </div>
        </div>
    @endif

    @if ($statusEntry !== null && $statusEntry['error'])
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_commerce.common.error_title') }}</h2>
                <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li>{{ $statusEntry['msg'] }}</li></ul></div>
            </div>
        </div>
    @endif

    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_commerce.slots.title_edit') }}</h1>

    @include('accessible-frontend::partials.commerce-pickup-slot-fields', [
        'formAction' => route('govuk-alpha.marketplace.slots.update', ['tenantSlug' => $tenantSlug, 'id' => $slotId]),
        'slot' => $slot,
        'submitLabel' => __('govuk_alpha_commerce.slots.submit_edit'),
        'tenantSlug' => $tenantSlug,
    ])

    @if ($slotId > 0)
        <h2 class="govuk-heading-m govuk-!-margin-top-6">{{ __('govuk_alpha_commerce.slots.delete_heading') }}</h2>
        <div class="govuk-warning-text">
            <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
            <strong class="govuk-warning-text__text">
                <span class="govuk-visually-hidden">{{ __('govuk_alpha_commerce.common.notice_title') }}</span>
                {{ __('govuk_alpha_commerce.slots.delete_warning') }}
            </strong>
        </div>
        <form method="post" action="{{ route('govuk-alpha.marketplace.slots.delete', ['tenantSlug' => $tenantSlug, 'id' => $slotId]) }}">
            @csrf
            <button class="govuk-button govuk-button--warning" data-module="govuk-button">{{ __('govuk_alpha_commerce.slots.action_delete') }}</button>
        </form>
    @endif
@endsection
