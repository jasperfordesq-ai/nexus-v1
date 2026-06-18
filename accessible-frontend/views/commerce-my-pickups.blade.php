{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $reservations = $reservations ?? [];
        $fmtTime = function ($iso) {
            $iso = trim((string) $iso);
            if ($iso === '') {
                return __('govuk_alpha_commerce.pickups.not_set');
            }
            try {
                return \Illuminate\Support\Carbon::parse($iso)->format('j M Y, H:i');
            } catch (\Throwable $e) {
                return __('govuk_alpha_commerce.pickups.not_set');
            }
        };
        $statusLabels = [
            'reserved' => __('govuk_alpha_commerce.pickups.status_reserved'),
            'picked_up' => __('govuk_alpha_commerce.pickups.status_picked_up'),
            'cancelled' => __('govuk_alpha_commerce.pickups.status_cancelled'),
            'no_show' => __('govuk_alpha_commerce.pickups.status_no_show'),
        ];
        $statusTags = [
            'reserved' => 'govuk-tag--blue',
            'picked_up' => 'govuk-tag--green',
            'cancelled' => 'govuk-tag--red',
            'no_show' => 'govuk-tag--yellow',
        ];
    @endphp

    @include('accessible-frontend::partials.commerce-marketplace-nav', ['commerceActiveTab' => 'orders'])

    <span class="govuk-caption-l">{{ __('govuk_alpha_commerce.pickups.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_commerce.pickups.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_commerce.pickups.description') }}</p>

    @if (empty($reservations))
        <p class="govuk-inset-text">{{ __('govuk_alpha_commerce.pickups.empty') }}</p>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($reservations as $r)
                @php
                    $rStatus = (string) ($r['status'] ?? 'reserved');
                    $rTitle = trim((string) ($r['listing_title'] ?? '')) ?: __('govuk_alpha_commerce.pickups.order_label', ['id' => (int) ($r['order_id'] ?? 0)]);
                    $slotStart = $r['slot']['slot_start'] ?? null;
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $rTitle }}</h2>
                        <strong class="govuk-tag {{ $statusTags[$rStatus] ?? 'govuk-tag--grey' }}">{{ $statusLabels[$rStatus] ?? $rStatus }}</strong>
                    </div>
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">{{ __('govuk_alpha_commerce.pickups.window_label') }}: {{ $fmtTime($slotStart) }}</p>

                    @if ($rStatus === 'reserved' && trim((string) ($r['qr_code'] ?? '')) !== '')
                        <div class="govuk-inset-text govuk-!-margin-top-0 govuk-!-margin-bottom-0">
                            <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha_commerce.pickups.code_heading') }}</h3>
                            <p class="govuk-hint govuk-!-margin-bottom-1">{{ __('govuk_alpha_commerce.pickups.code_hint') }}</p>
                            <p class="govuk-body govuk-!-font-weight-bold">{{ $r['qr_code'] }}</p>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
