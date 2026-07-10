{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $slots = $slots ?? [];
        $fmtTime = function ($iso) {
            $iso = trim((string) $iso);
            if ($iso === '') {
                return __('govuk_alpha_commerce.slots.not_set');
            }
            try {
                return \Illuminate\Support\Carbon::parse($iso)->format('j M Y, H:i');
            } catch (\Throwable $e) {
                return __('govuk_alpha_commerce.slots.not_set');
            }
        };
        $statusMessages = [
            'slot-created' => ['msg' => __('govuk_alpha_commerce.slots.status_slot_created'), 'error' => false],
            'slot-deleted' => ['msg' => __('govuk_alpha_commerce.slots.status_slot_deleted'), 'error' => false],
            'slot-create-failed' => ['msg' => __('govuk_alpha_commerce.slots.status_slot_create_failed'), 'error' => true],
            'slot-delete-failed' => ['msg' => __('govuk_alpha_commerce.slots.status_slot_delete_failed'), 'error' => true],
            'pickup-confirmed' => ['msg' => __('govuk_alpha_commerce.slots.status_pickup_confirmed'), 'error' => false],
            'pickup-scan-failed' => ['msg' => __('govuk_alpha_commerce.slots.status_pickup_scan_failed'), 'error' => true],
        ];
        $statusEntry = $status !== null && isset($statusMessages[$status]) ? $statusMessages[$status] : null;
    @endphp

    @include('accessible-frontend::partials.commerce-marketplace-nav', ['commerceActiveTab' => 'slots'])

    @if ($statusEntry !== null)
        @if ($statusEntry['error'])
            <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                <div role="alert">
                    <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_commerce.common.error_title') }}</h2>
                    <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li>{{ $statusEntry['msg'] }}</li></ul></div>
                </div>
            </div>
        @else
            <div class="govuk-notification-banner govuk-notification-banner--success" role="alert" aria-labelledby="govuk-notification-banner-title" data-module="govuk-notification-banner">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="govuk-notification-banner-title">{{ __('govuk_alpha.states.success_title') }}</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading">{{ $statusEntry['msg'] }}</p>
                    @if ($status === 'pickup-confirmed' && (int) session('commercePickupScanOrderId') > 0)
                        <p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha_commerce.slots.scan_order_ref', ['id' => (int) session('commercePickupScanOrderId')]) }}</p>
                    @endif
                </div>
            </div>
        @endif
    @endif

    <span class="govuk-caption-l">{{ __('govuk_alpha_commerce.slots.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_commerce.slots.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_commerce.slots.description') }}</p>

    {{-- Confirm a collection: the no-JS equivalent of scanning the buyer's QR
         code. The seller types the short collection code the buyer shows them,
         which marks their click-and-collect order as picked up. --}}
    <form method="post" action="{{ route('govuk-alpha.marketplace.slots.scan', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
        @csrf
        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--m" for="qr_code">{{ __('govuk_alpha_commerce.slots.scan_heading') }}</label>
            <div id="qr_code-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.slots.scan_hint') }}</div>
            <input class="govuk-input govuk-input--width-20" id="qr_code" name="qr_code" type="text" inputmode="text" autocomplete="off" spellcheck="false" maxlength="64" aria-describedby="qr_code-hint">
        </div>
        <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_commerce.slots.scan_submit') }}</button>
    </form>

    <a class="govuk-button" href="#new-slot" data-module="govuk-button">{{ __('govuk_alpha_commerce.slots.new_button') }}</a>

    @if (empty($slots))
        <p class="govuk-inset-text">{{ __('govuk_alpha_commerce.slots.empty') }}</p>
    @else
        <table class="govuk-table">
            <caption class="govuk-table__caption govuk-table__caption--s govuk-visually-hidden">{{ __('govuk_alpha_commerce.slots.title') }}</caption>
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_commerce.slots.window_label') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_commerce.slots.capacity_label') }}</th>
                    <th scope="col" class="govuk-table__header"><span class="govuk-visually-hidden">{{ __('govuk_alpha_commerce.common.view') }}</span></th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                @foreach ($slots as $s)
                    @php
                        $sId = (int) ($s['id'] ?? 0);
                        $cap = (int) ($s['capacity'] ?? 0);
                        $booked = (int) ($s['booked_count'] ?? 0);
                        $remaining = isset($s['remaining']) ? (int) $s['remaining'] : max(0, $cap - $booked);
                    @endphp
                    <tr class="govuk-table__row">
                        <td class="govuk-table__cell">
                            <strong>{{ $fmtTime($s['slot_start'] ?? null) }}</strong><br>
                            <span class="govuk-body-s nexus-alpha-meta">{{ $fmtTime($s['slot_end'] ?? null) }}</span>
                            <div class="govuk-!-margin-top-1">
                                @if (!empty($s['is_recurring']))
                                    <strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha_commerce.slots.recurring') }}</strong>
                                @endif
                                @if (isset($s['is_active']) && !$s['is_active'])
                                    <strong class="govuk-tag govuk-tag--yellow">{{ __('govuk_alpha_commerce.slots.inactive') }}</strong>
                                @endif
                            </div>
                        </td>
                        <td class="govuk-table__cell">
                            {{ __('govuk_alpha_commerce.slots.capacity_value', ['booked' => $booked, 'capacity' => $cap]) }}<br>
                            <span class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha_commerce.slots.remaining_value', ['count' => $remaining]) }}</span>
                        </td>
                        <td class="govuk-table__cell">
                            <a class="govuk-link" href="{{ route('govuk-alpha.marketplace.slots.edit', ['tenantSlug' => $tenantSlug, 'id' => $sId]) }}">{{ __('govuk_alpha_commerce.slots.action_edit') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2 class="govuk-heading-l govuk-!-margin-top-6" id="new-slot">{{ __('govuk_alpha_commerce.slots.new_button') }}</h2>

    @include('accessible-frontend::partials.commerce-pickup-slot-fields', [
        'formAction' => route('govuk-alpha.marketplace.slots.store', ['tenantSlug' => $tenantSlug]),
        'slot' => null,
        'submitLabel' => __('govuk_alpha_commerce.slots.submit_create'),
        'tenantSlug' => $tenantSlug,
    ])
@endsection
