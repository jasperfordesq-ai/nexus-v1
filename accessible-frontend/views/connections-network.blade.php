{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $partnerName = function ($p): string {
            $p = is_array($p) ? $p : [];
            if (($p['profile_type'] ?? '') === 'organisation' && trim((string) ($p['organization_name'] ?? '')) !== '') {
                return $p['organization_name'];
            }
            $n = trim((string) ($p['name'] ?? ''));
            if ($n !== '') {
                return $n;
            }
            $full = trim((string) ($p['first_name'] ?? '') . ' ' . (string) ($p['last_name'] ?? ''));
            return $full !== '' ? $full : __('govuk_alpha_connections.common.unknown_member');
        };
        $partnerLoc = fn ($p): string => is_array($p) ? trim((string) ($p['location'] ?? '')) : '';
        $counts = $connectionCounts ?? ['received' => 0, 'sent' => 0, 'total_friends' => 0];
        $hasSearch = ($connSearch ?? '') !== '';
    @endphp

    <span class="govuk-caption-xl" id="connections-network-top">{{ __('govuk_alpha_connections.network.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_connections.network.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_connections.network.description') }}</p>

    {{-- Status banners --}}
    @if (in_array($status, ['connection-accepted', 'connection-declined', 'connection-removed'], true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="conn-net-status-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="conn-net-status-title">{{ __('govuk_alpha_connections.common.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">
                    @switch($status)
                        @case('connection-accepted'){{ __('govuk_alpha_connections.network_states.accepted') }}@break
                        @case('connection-declined'){{ __('govuk_alpha_connections.network_states.declined') }}@break
                        @default{{ __('govuk_alpha_connections.network_states.removed') }}
                    @endswitch
                </p>
            </div>
        </div>
    @elseif ($status === 'connection-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_connections.common.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p class="govuk-body">{{ __('govuk_alpha_connections.network_states.failed') }}</p>
                </div>
            </div>
        </div>
    @endif

    <p class="govuk-body">{{ __('govuk_alpha_connections.network.summary', [
        'received' => (int) ($counts['received'] ?? 0),
        'sent' => (int) ($counts['sent'] ?? 0),
        'total' => (int) ($counts['total_friends'] ?? 0),
    ]) }}</p>

    {{-- Name / location search (parity: React connections search) --}}
    <form method="get" action="{{ route('govuk-alpha.connections.network', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
        <input type="hidden" name="tab" value="{{ $activeTab }}">
        <div class="govuk-form-group govuk-!-margin-bottom-2">
            <label class="govuk-label" for="conn-net-search">{{ __('govuk_alpha_connections.network.search_label') }}</label>
            <div id="conn-net-search-hint" class="govuk-hint">{{ __('govuk_alpha_connections.network.search_hint') }}</div>
            <input class="govuk-input govuk-!-width-two-thirds" id="conn-net-search" name="q" type="search" value="{{ $connSearch ?? '' }}" aria-describedby="conn-net-search-hint">
        </div>
        <div class="govuk-button-group">
            <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha_connections.network.search_submit') }}</button>
            @if ($hasSearch)
                <a class="govuk-link" href="{{ route('govuk-alpha.connections.network', ['tenantSlug' => $tenantSlug, 'tab' => $activeTab]) }}">{{ __('govuk_alpha_connections.network.search_clear') }}</a>
            @endif
        </div>
    </form>

    {{-- Tab navigation: three-way split with counts (parity: React Tabs) --}}
    @php
        $tabDefs = [
            'accepted' => __('govuk_alpha_connections.network.tab_accepted'),
            'pending_received' => __('govuk_alpha_connections.network.tab_pending_received'),
            'pending_sent' => __('govuk_alpha_connections.network.tab_pending_sent'),
        ];
        $tabCounts = [
            'accepted' => (int) ($counts['total_friends'] ?? 0),
            'pending_received' => (int) ($counts['received'] ?? 0),
            'pending_sent' => (int) ($counts['sent'] ?? 0),
        ];
    @endphp
    <nav aria-label="{{ __('govuk_alpha_connections.network.title') }}" class="govuk-!-margin-bottom-6">
        <ul class="nexus-alpha-filter-nav">
            @foreach ($tabDefs as $tabKey => $tabLabel)
                <li>
                    <a class="govuk-link{{ $activeTab === $tabKey ? ' govuk-link--no-visited-state' : '' }}"
                       href="{{ route('govuk-alpha.connections.network', array_filter(['tenantSlug' => $tenantSlug, 'tab' => $tabKey, 'q' => $connSearch ?: null])) }}"
                       @if ($activeTab === $tabKey) aria-current="true" @endif>{{ $tabLabel }} ({{ $tabCounts[$tabKey] }})</a>
                </li>
            @endforeach
        </ul>
    </nav>

    {{-- Section: My connections (accepted) --}}
    <section aria-labelledby="net-accepted-heading">
        <h2 class="govuk-heading-l" id="net-accepted-heading">{{ __('govuk_alpha_connections.network.tab_accepted') }}</h2>
        @php $acc = $sections['accepted'] ?? ['items' => [], 'has_more' => false, 'cursor' => null]; @endphp
        @if (empty($acc['items']))
            <div class="govuk-inset-text">
                <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha_connections.network_empty.accepted_title') }}</h3>
                <p class="govuk-body">{{ $hasSearch ? __('govuk_alpha_connections.network_empty.accepted_search') : __('govuk_alpha_connections.network_empty.accepted_body') }}</p>
                @unless ($hasSearch)
                    <a class="govuk-button govuk-button--secondary" data-module="govuk-button" role="button" draggable="false" href="{{ route('govuk-alpha.members.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_connections.network_empty.find_members') }}</a>
                @endunless
            </div>
        @else
            <div class="nexus-alpha-card-list">
                @foreach ($acc['items'] as $c)
                    @php
                        $p = $c['partner'] ?? $c['user'] ?? [];
                        $cid = (int) ($c['connection_id'] ?? $c['id'] ?? 0);
                        $since = null;
                        if (!empty($c['created_at'])) {
                            try { $since = \Illuminate\Support\Carbon::parse($c['created_at'])->translatedFormat('F Y'); } catch (\Throwable $e) { $since = null; }
                        }
                    @endphp
                    <article class="nexus-alpha-card">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $partnerName($p) }}</h3>
                        @if ($partnerLoc($p) !== '')
                            <p class="govuk-hint govuk-!-font-size-16 govuk-!-margin-bottom-1">{{ $partnerLoc($p) }}</p>
                        @endif
                        @if ($since !== null)
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-3">{{ __('govuk_alpha_connections.network.connected_since', ['date' => $since]) }}</p>
                        @endif
                        <div class="govuk-button-group">
                            @if (!empty($p['id']))
                                <a class="govuk-button govuk-button--secondary" data-module="govuk-button" role="button" draggable="false" href="{{ route('govuk-alpha.messages.new', ['tenantSlug' => $tenantSlug, 'userId' => $p['id']]) }}">{{ __('govuk_alpha_connections.network.message') }}</a>
                                <a class="govuk-link govuk-link--no-visited-state" href="{{ route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $p['id']]) }}">{{ __('govuk_alpha_connections.network.view_profile') }}</a>
                            @endif
                            <form method="post" action="{{ route('govuk-alpha.connections.remove', ['tenantSlug' => $tenantSlug, 'id' => $cid]) }}">
                                @csrf
                                <button class="govuk-button govuk-button--warning" data-module="govuk-button">{{ __('govuk_alpha_connections.network.disconnect') }}</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
            @if (!empty($acc['has_more']) && !empty($acc['cursor']) && !$hasSearch)
                <p class="govuk-!-margin-top-2">
                    <a class="govuk-button govuk-button--secondary" data-module="govuk-button" role="button" draggable="false"
                       href="{{ route('govuk-alpha.connections.network', ['tenantSlug' => $tenantSlug, 'tab' => 'accepted', 'cursor' => $acc['cursor']]) }}#net-accepted-heading">
                        {{ __('govuk_alpha_connections.network.load_more') }}
                        <span class="govuk-visually-hidden">{{ __('govuk_alpha_connections.network.load_more_sr', ['section' => __('govuk_alpha_connections.network.tab_accepted')]) }}</span>
                    </a>
                </p>
            @endif
        @endif
    </section>

    {{-- Section: Pending requests (received) --}}
    <section aria-labelledby="net-received-heading">
        <h2 class="govuk-heading-l govuk-!-margin-top-6" id="net-received-heading">{{ __('govuk_alpha_connections.network.tab_pending_received') }}</h2>
        @php $rec = $sections['pending_received'] ?? ['items' => [], 'has_more' => false, 'cursor' => null]; @endphp
        @if (empty($rec['items']))
            <div class="govuk-inset-text">
                <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha_connections.network_empty.received_title') }}</h3>
                <p class="govuk-body">{{ $hasSearch ? __('govuk_alpha_connections.network_empty.received_search') : __('govuk_alpha_connections.network_empty.received_body') }}</p>
            </div>
        @else
            <div class="nexus-alpha-card-list">
                @foreach ($rec['items'] as $c)
                    @php $p = $c['partner'] ?? $c['user'] ?? []; $cid = (int) ($c['connection_id'] ?? $c['id'] ?? 0); @endphp
                    <article class="nexus-alpha-card">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $partnerName($p) }}</h3>
                        @if ($partnerLoc($p) !== '')
                            <p class="govuk-hint govuk-!-font-size-16 govuk-!-margin-bottom-1">{{ $partnerLoc($p) }}</p>
                        @endif
                        <p class="govuk-body-s govuk-!-margin-bottom-3">{{ __('govuk_alpha_connections.network.wants_to_connect') }}</p>
                        <div class="govuk-button-group">
                            <form method="post" action="{{ route('govuk-alpha.connections.accept', ['tenantSlug' => $tenantSlug, 'id' => $cid]) }}">
                                @csrf
                                <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_connections.network.accept') }}</button>
                            </form>
                            <form method="post" action="{{ route('govuk-alpha.connections.decline', ['tenantSlug' => $tenantSlug, 'id' => $cid]) }}">
                                @csrf
                                <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha_connections.network.decline') }}</button>
                            </form>
                            @if (!empty($p['id']))
                                <a class="govuk-link govuk-link--no-visited-state" href="{{ route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $p['id']]) }}">{{ __('govuk_alpha_connections.network.view_profile') }}</a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
            @if (!empty($rec['has_more']) && !empty($rec['cursor']) && !$hasSearch)
                <p class="govuk-!-margin-top-2">
                    <a class="govuk-button govuk-button--secondary" data-module="govuk-button" role="button" draggable="false"
                       href="{{ route('govuk-alpha.connections.network', ['tenantSlug' => $tenantSlug, 'tab' => 'pending_received', 'cursor' => $rec['cursor']]) }}#net-received-heading">
                        {{ __('govuk_alpha_connections.network.load_more') }}
                        <span class="govuk-visually-hidden">{{ __('govuk_alpha_connections.network.load_more_sr', ['section' => __('govuk_alpha_connections.network.tab_pending_received')]) }}</span>
                    </a>
                </p>
            @endif
        @endif
    </section>

    {{-- Section: Sent requests --}}
    <section aria-labelledby="net-sent-heading">
        <h2 class="govuk-heading-l govuk-!-margin-top-6" id="net-sent-heading">{{ __('govuk_alpha_connections.network.tab_pending_sent') }}</h2>
        @php $snt = $sections['pending_sent'] ?? ['items' => [], 'has_more' => false, 'cursor' => null]; @endphp
        @if (empty($snt['items']))
            <div class="govuk-inset-text">
                <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha_connections.network_empty.sent_title') }}</h3>
                <p class="govuk-body">{{ $hasSearch ? __('govuk_alpha_connections.network_empty.sent_search') : __('govuk_alpha_connections.network_empty.sent_body') }}</p>
                @unless ($hasSearch)
                    <a class="govuk-button govuk-button--secondary" data-module="govuk-button" role="button" draggable="false" href="{{ route('govuk-alpha.members.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_connections.network_empty.find_members') }}</a>
                @endunless
            </div>
        @else
            <div class="nexus-alpha-card-list">
                @foreach ($snt['items'] as $c)
                    @php $p = $c['partner'] ?? $c['user'] ?? []; $cid = (int) ($c['connection_id'] ?? $c['id'] ?? 0); @endphp
                    <article class="nexus-alpha-card">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $partnerName($p) }}</h3>
                        @if ($partnerLoc($p) !== '')
                            <p class="govuk-hint govuk-!-font-size-16 govuk-!-margin-bottom-1">{{ $partnerLoc($p) }}</p>
                        @endif
                        <p class="govuk-body-s govuk-!-margin-bottom-3">{{ __('govuk_alpha_connections.network.request_pending') }}</p>
                        <div class="govuk-button-group">
                            @if (!empty($p['id']))
                                <a class="govuk-link govuk-link--no-visited-state" href="{{ route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $p['id']]) }}">{{ __('govuk_alpha_connections.network.view_profile') }}</a>
                            @endif
                            <form method="post" action="{{ route('govuk-alpha.connections.remove', ['tenantSlug' => $tenantSlug, 'id' => $cid]) }}">
                                @csrf
                                <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha_connections.network.cancel_request') }}</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
            @if (!empty($snt['has_more']) && !empty($snt['cursor']) && !$hasSearch)
                <p class="govuk-!-margin-top-2">
                    <a class="govuk-button govuk-button--secondary" data-module="govuk-button" role="button" draggable="false"
                       href="{{ route('govuk-alpha.connections.network', ['tenantSlug' => $tenantSlug, 'tab' => 'pending_sent', 'cursor' => $snt['cursor']]) }}#net-sent-heading">
                        {{ __('govuk_alpha_connections.network.load_more') }}
                        <span class="govuk-visually-hidden">{{ __('govuk_alpha_connections.network.load_more_sr', ['section' => __('govuk_alpha_connections.network.tab_pending_sent')]) }}</span>
                    </a>
                </p>
            @endif
        @endif
    </section>

    <p class="govuk-body govuk-!-margin-top-6">
        <a class="govuk-link" href="{{ route('govuk-alpha.connections.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_connections.network.back_to_connections') }}</a>
    </p>
@endsection
