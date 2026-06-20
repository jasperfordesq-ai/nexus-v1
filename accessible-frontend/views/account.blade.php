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

        if (\Illuminate\Support\Facades\Route::has('govuk-alpha.notifications.index') && \App\Core\TenantContext::hasModule('notifications')) {
            $accountLinks[] = [
                'title' => __('govuk_alpha.notifications.title'),
                'description' => __('govuk_alpha.notifications.description'),
                'href' => route('govuk-alpha.notifications.index', ['tenantSlug' => $tenantSlug]),
            ];
        }

        if (\Illuminate\Support\Facades\Route::has('govuk-alpha.reviews.index') && \App\Core\TenantContext::hasFeature('reviews')) {
            $accountLinks[] = [
                'title' => __('govuk_alpha.reviews_page.title'),
                'description' => __('govuk_alpha.reviews_page.description'),
                'href' => route('govuk-alpha.reviews.index', ['tenantSlug' => $tenantSlug]),
            ];
        }

        if (\Illuminate\Support\Facades\Route::has('govuk-alpha.activity')) {
            $accountLinks[] = [
                'title' => __('govuk_alpha.activity.title'),
                'description' => __('govuk_alpha.activity.description'),
                'href' => route('govuk-alpha.activity', ['tenantSlug' => $tenantSlug]),
            ];
        }

        if (\Illuminate\Support\Facades\Route::has('govuk-alpha.saved.index')) {
            $accountLinks[] = [
                'title' => __('govuk_alpha.saved.title'),
                'description' => __('govuk_alpha.saved.description'),
                'href' => route('govuk-alpha.saved.index', ['tenantSlug' => $tenantSlug]),
            ];
        }

        if (\Illuminate\Support\Facades\Route::has('govuk-alpha.jobs.index') && \App\Core\TenantContext::hasFeature('job_vacancies')) {
            $accountLinks[] = [
                'title' => __('govuk_alpha.jobs_t2.account_title'),
                'description' => __('govuk_alpha.jobs_t2.account_description'),
                'href' => route('govuk-alpha.jobs.index', ['tenantSlug' => $tenantSlug]),
            ];
        }

        if (\Illuminate\Support\Facades\Route::has('govuk-alpha.matches.index') && \App\Core\TenantContext::hasModule('listings')) {
            $accountLinks[] = [
                'title' => __('govuk_alpha.matches.title'),
                'description' => __('govuk_alpha.matches.description'),
                'href' => route('govuk-alpha.matches.index', ['tenantSlug' => $tenantSlug]),
            ];
        }

        if (\Illuminate\Support\Facades\Route::has('govuk-alpha.group-exchanges.index') && \App\Core\TenantContext::hasFeature('group_exchanges')) {
            $accountLinks[] = [
                'title' => __('govuk_alpha.group_exchanges.title'),
                'description' => __('govuk_alpha.group_exchanges.description'),
                'href' => route('govuk-alpha.group-exchanges.index', ['tenantSlug' => $tenantSlug]),
            ];
        }

        // Gamification — gated on the gamification feature (parity with React, which
        // wraps achievements/leaderboard/nexus-score in <FeatureGate feature="gamification">
        // and filters the same links out of its account/personal menus).
        if (\Illuminate\Support\Facades\Route::has('govuk-alpha.achievements') && \App\Core\TenantContext::hasFeature('gamification')) {
            $accountLinks[] = [
                'title' => __('govuk_alpha.achievements.title'),
                'description' => __('govuk_alpha.achievements.description'),
                'href' => route('govuk-alpha.achievements', ['tenantSlug' => $tenantSlug]),
            ];
        }
        if (\Illuminate\Support\Facades\Route::has('govuk-alpha.leaderboard') && \App\Core\TenantContext::hasFeature('gamification')) {
            $accountLinks[] = [
                'title' => __('govuk_alpha.leaderboard.title'),
                'description' => __('govuk_alpha.leaderboard.description'),
                'href' => route('govuk-alpha.leaderboard', ['tenantSlug' => $tenantSlug]),
            ];
        }
        if (\Illuminate\Support\Facades\Route::has('govuk-alpha.nexus-score') && \App\Core\TenantContext::hasFeature('gamification')) {
            $accountLinks[] = [
                'title' => __('govuk_alpha.nexus_score.title'),
                'description' => __('govuk_alpha.nexus_score.description'),
                'href' => route('govuk-alpha.nexus-score', ['tenantSlug' => $tenantSlug]),
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

        if (\Illuminate\Support\Facades\Route::has('govuk-alpha.settings.linked-accounts')) {
            $accountLinks[] = [
                'title' => __('govuk_alpha_settings.nav.linked_accounts'),
                'description' => __('govuk_alpha_settings.linked.description'),
                'href' => route('govuk-alpha.settings.linked-accounts', ['tenantSlug' => $tenantSlug]),
            ];
        }
        if (\Illuminate\Support\Facades\Route::has('govuk-alpha.settings.appearance')) {
            $accountLinks[] = [
                'title' => __('govuk_alpha_settings.nav.appearance'),
                'description' => __('govuk_alpha_settings.appearance.description'),
                'href' => route('govuk-alpha.settings.appearance', ['tenantSlug' => $tenantSlug]),
            ];
        }
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
