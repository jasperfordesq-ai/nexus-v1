{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        // The personal/transactional facilities, gathered in one place so the
        // service navigation can stay focused on community + discovery. Each item
        // is gated by the same module/feature checks used elsewhere; Connections
        // appears automatically once its route exists.
        $accountLinks = [];

        if (\App\Core\TenantContext::hasModule('wallet')) {
            $accountLinks[] = [
                'title' => __('govuk_alpha.account.wallet_title'),
                'description' => __('govuk_alpha.account.wallet_description'),
                'href' => route('govuk-alpha.wallet.index', ['tenantSlug' => $tenantSlug]),
            ];
        }

        if (\App\Services\BrokerControlConfigService::isDirectMessagingEnabled()) {
            $accountLinks[] = [
                'title' => __('govuk_alpha.account.messages_title'),
                'description' => __('govuk_alpha.account.messages_description'),
                'href' => route('govuk-alpha.messages.index', ['tenantSlug' => $tenantSlug]),
                'badge' => (int) ($alphaUnreadMessages ?? 0),
            ];
        }

        if (\Illuminate\Support\Facades\Route::has('govuk-alpha.connections.index') && \App\Core\TenantContext::hasFeature('connections')) {
            $accountLinks[] = [
                'title' => __('govuk_alpha.account.connections_title'),
                'description' => __('govuk_alpha.account.connections_description'),
                'href' => route('govuk-alpha.connections.index', ['tenantSlug' => $tenantSlug]),
            ];
        }

        $accountLinks[] = [
            'title' => __('govuk_alpha.account.profile_title'),
            'description' => __('govuk_alpha.account.profile_description'),
            'href' => route('govuk-alpha.profile.me', ['tenantSlug' => $tenantSlug]),
        ];
        $accountLinks[] = [
            'title' => __('govuk_alpha.account.settings_title'),
            'description' => __('govuk_alpha.account.settings_description'),
            'href' => route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug]),
        ];
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha.account.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.account.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.account.description') }}</p>

    <div class="nexus-alpha-card-list govuk-!-margin-top-6">
        @foreach ($accountLinks as $item)
            <article class="nexus-alpha-card">
                <h2 class="govuk-heading-m govuk-!-margin-bottom-2">
                    <a class="govuk-link" href="{{ $item['href'] }}">{{ $item['title'] }}</a>
                    @if (!empty($item['badge']) && $item['badge'] > 0)
                        <strong class="govuk-tag govuk-tag--blue govuk-!-margin-left-2">{{ trans_choice('govuk_alpha.messages.unread_count', $item['badge'], ['count' => $item['badge']]) }}</strong>
                    @endif
                </h2>
                <p class="govuk-body govuk-!-margin-bottom-0">{{ $item['description'] }}</p>
            </article>
        @endforeach
    </div>

    @if (!empty($alphaSignOutUrl))
        {{-- Sign-out changes state, so it is a CSRF-protected POST form, not a GET link. --}}
        <form method="post" action="{{ $alphaSignOutUrl }}" class="govuk-!-margin-top-8">
            @csrf
            <button type="submit" class="govuk-button govuk-button--warning" data-module="govuk-button">{{ __('govuk_alpha.account.sign_out') }}</button>
        </form>
    @endif
@endsection
