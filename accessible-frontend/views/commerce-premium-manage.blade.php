{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $sub = $subscription ?? [];
        $tierName = trim((string) ($sub['tier_name'] ?? ''));
        $subStatus = (string) ($sub['status'] ?? '');
        $interval = (string) ($sub['billing_interval'] ?? '');
        $periodEnd = $sub['current_period_end'] ?? null;
        $canceledAt = $sub['canceled_at'] ?? null;
        $features = is_array($sub['features'] ?? null) ? $sub['features'] : [];
        $statusMessages = [
            'cancel-scheduled' => __('govuk_alpha_commerce.premium_manage.status_cancel_scheduled'),
            'cancel-failed' => __('govuk_alpha_commerce.premium_manage.status_cancel_failed'),
            'portal-failed' => __('govuk_alpha_commerce.premium_manage.status_portal_failed'),
        ];
        $statusLabels = [
            'active' => __('govuk_alpha_commerce.premium_manage.status_active'),
            'cancelled' => __('govuk_alpha_commerce.premium_manage.status_cancelled'),
            'canceled' => __('govuk_alpha_commerce.premium_manage.status_cancelled'),
            'past_due' => __('govuk_alpha_commerce.premium_manage.status_past_due'),
        ];
        $intervalLabel = $interval === 'yearly' || $interval === 'year'
            ? __('govuk_alpha_commerce.premium_manage.interval_yearly')
            : __('govuk_alpha_commerce.premium_manage.interval_monthly');
        $renewFmt = '';
        if ($periodEnd) {
            try { $renewFmt = \Illuminate\Support\Carbon::parse($periodEnd)->translatedFormat('j F Y'); } catch (\Throwable $e) { $renewFmt = ''; }
        }
    @endphp

    <a href="{{ route('govuk-alpha.premium.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha_commerce.common.back_to_premium') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_commerce.premium_manage.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_commerce.premium_manage.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_commerce.premium_manage.description') }}</p>

    @if (($status ?? null) !== null && isset($statusMessages[$status]))
        <div class="govuk-notification-banner{{ \Illuminate\Support\Str::endsWith($status, 'failed') ? '' : ' govuk-notification-banner--success' }}" role="region" aria-labelledby="commerce-premium-status" data-module="govuk-notification-banner">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="commerce-premium-status">{{ __('govuk_alpha_commerce.common.notice_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ $statusMessages[$status] }}</p>
            </div>
        </div>
    @endif

    <dl class="govuk-summary-list govuk-!-margin-bottom-6">
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_commerce.premium_manage.tier_label') }}</dt>
            <dd class="govuk-summary-list__value">{{ $tierName }}</dd>
        </div>
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_commerce.premium_manage.status_label') }}</dt>
            <dd class="govuk-summary-list__value"><strong class="govuk-tag {{ $subStatus === 'active' ? 'govuk-tag--green' : 'govuk-tag--grey' }}">{{ $statusLabels[$subStatus] ?? $subStatus }}</strong></dd>
        </div>
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_commerce.premium_manage.interval_label') }}</dt>
            <dd class="govuk-summary-list__value">{{ $intervalLabel }}</dd>
        </div>
        @if ($renewFmt !== '')
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ $canceledAt ? __('govuk_alpha_commerce.premium_manage.cancels_label') : __('govuk_alpha_commerce.premium_manage.renews_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $renewFmt }}</dd>
            </div>
        @endif
    </dl>

    @if (!empty($features))
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_commerce.premium_manage.features_heading') }}</h2>
        <ul class="govuk-list govuk-list--bullet">
            @foreach ($features as $feature)
                <li>{{ $feature }}</li>
            @endforeach
        </ul>
    @endif

    <form method="post" action="{{ route('govuk-alpha.premium.portal', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-4">
        @csrf
        <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha_commerce.premium_manage.manage_billing') }}</button>
    </form>

    @unless ($canceledAt)
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_commerce.premium_manage.cancel_heading') }}</h2>
        <div class="govuk-warning-text">
            <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
            <strong class="govuk-warning-text__text">
                <span class="govuk-visually-hidden">{{ __('govuk_alpha_commerce.common.notice_title') }}</span>
                {{ __('govuk_alpha_commerce.premium_manage.cancel_warning') }}
            </strong>
        </div>
        <form method="post" action="{{ route('govuk-alpha.premium.cancel', ['tenantSlug' => $tenantSlug]) }}">
            @csrf
            <button class="govuk-button govuk-button--warning" data-module="govuk-button">{{ __('govuk_alpha_commerce.premium_manage.cancel_button') }}</button>
        </form>
    @endunless
@endsection
