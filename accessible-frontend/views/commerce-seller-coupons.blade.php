{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $coupons = $coupons ?? [];
        $statusLabels = [
            'draft' => __('govuk_alpha_commerce.coupons.status_draft'),
            'active' => __('govuk_alpha_commerce.coupons.status_active'),
            'paused' => __('govuk_alpha_commerce.coupons.status_paused'),
            'expired' => __('govuk_alpha_commerce.coupons.status_expired'),
        ];
        $statusTags = [
            'draft' => 'govuk-tag--grey',
            'active' => 'govuk-tag--green',
            'paused' => 'govuk-tag--yellow',
            'expired' => 'govuk-tag--red',
        ];
        $discountLabel = function (array $c) {
            $type = (string) ($c['discount_type'] ?? 'percent');
            $val = (float) ($c['discount_value'] ?? 0);
            if ($type === 'percent') {
                return rtrim(rtrim(number_format($val, 2), '0'), '.') . '%';
            }
            if ($type === 'bogo') {
                return __('govuk_alpha_commerce.coupons.discount_type_bogo');
            }
            return number_format($val, 2);
        };
        $statusMessages = [
            'coupon-created' => ['msg' => __('govuk_alpha_commerce.coupons.status_coupon_created'), 'error' => false],
            'coupon-deleted' => ['msg' => __('govuk_alpha_commerce.coupons.status_coupon_deleted'), 'error' => false],
            'coupon-delete-failed' => ['msg' => __('govuk_alpha_commerce.coupons.status_coupon_delete_failed'), 'error' => true],
        ];
        $statusEntry = $status !== null && isset($statusMessages[$status]) ? $statusMessages[$status] : null;
    @endphp

    @include('accessible-frontend::partials.commerce-marketplace-nav', ['commerceActiveTab' => 'sell'])

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
                </div>
            </div>
        @endif
    @endif

    <span class="govuk-caption-l">{{ __('govuk_alpha_commerce.coupons.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_commerce.coupons.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_commerce.coupons.description') }}</p>

    <a class="govuk-button" href="{{ route('govuk-alpha.marketplace.coupons.create', ['tenantSlug' => $tenantSlug]) }}" data-module="govuk-button">{{ __('govuk_alpha_commerce.coupons.create_button') }}</a>

    @if (empty($coupons))
        <p class="govuk-inset-text">{{ __('govuk_alpha_commerce.coupons.empty') }}</p>
    @else
        <table class="govuk-table">
            <caption class="govuk-table__caption govuk-table__caption--s govuk-visually-hidden">{{ __('govuk_alpha_commerce.coupons.title') }}</caption>
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_commerce.coupons.code_label') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_commerce.coupons.discount_label') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_commerce.coupons.status_label') }}</th>
                    <th scope="col" class="govuk-table__header"><span class="govuk-visually-hidden">{{ __('govuk_alpha_commerce.common.view') }}</span></th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                @foreach ($coupons as $c)
                    @php $cStatus = (string) ($c['status'] ?? 'draft'); @endphp
                    <tr class="govuk-table__row">
                        <td class="govuk-table__cell">
                            <strong>{{ $c['title'] ?? '' }}</strong><br>
                            <span class="govuk-body-s nexus-alpha-meta">{{ $c['code'] ?? '' }}</span>
                        </td>
                        <td class="govuk-table__cell">{{ $discountLabel($c) }}</td>
                        <td class="govuk-table__cell"><strong class="govuk-tag {{ $statusTags[$cStatus] ?? 'govuk-tag--grey' }}">{{ $statusLabels[$cStatus] ?? $cStatus }}</strong></td>
                        <td class="govuk-table__cell">
                            <a class="govuk-link" href="{{ route('govuk-alpha.marketplace.coupons.edit', ['tenantSlug' => $tenantSlug, 'id' => (int) ($c['id'] ?? 0)]) }}">{{ __('govuk_alpha_commerce.coupons.action_edit') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
