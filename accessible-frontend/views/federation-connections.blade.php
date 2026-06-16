{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $allowed = (bool) ($allowed ?? false);
        $connections = $connections ?? [];
        $tab = (string) ($tab ?? 'accepted');
        $statusKey = (string) ($status ?? '');
        $statusText = $statusKey !== '' ? __('govuk_alpha.fed2.connections.status.' . $statusKey) : '';
        $statusIsError = in_array($statusKey, ['connection-action-failed'], true);

        $emptyKey = match ($tab) {
            'received' => 'empty_received',
            'sent' => 'empty_sent',
            default => 'empty_accepted',
        };

        $tabHref = fn (string $t): string => route('govuk-alpha.federation.connections.index', ['tenantSlug' => $tenantSlug, 'tab' => $t]);
        $memberHref = function (array $c) use ($tenantSlug): string {
            return route('govuk-alpha.federation.members.show', [
                'tenantSlug' => $tenantSlug,
                'id' => (int) ($c['user_id'] ?? 0),
                'tenant_id' => (int) ($c['tenant_id'] ?? 0),
            ]);
        };
    @endphp

    <a href="{{ route('govuk-alpha.federation.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.fed2.connections.back') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha.fed2.connections.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.fed2.connections.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.fed2.connections.description') }}</p>

    @if ($statusText !== '')
        @if ($statusIsError)
            <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                <div role="alert">
                    <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                    <div class="govuk-error-summary__body">
                        <ul class="govuk-list govuk-error-summary__list">
                            <li><a href="#connections-list">{{ $statusText }}</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        @else
            <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-labelledby="fed-conn-status">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="fed-conn-status">{{ __('govuk_alpha.states.success_title') }}</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading">{{ $statusText }}</p>
                </div>
            </div>
        @endif
    @endif

    @if (!$allowed)
        <p class="govuk-inset-text">{{ __('govuk_alpha.fed2.connections.not_available') }}</p>
    @else
        {{-- govuk-tabs progressive-enhancement: server pre-selects the active panel
             by omitting govuk-tabs__panel--hidden. Without JS the panels are all
             visible and the tab links navigate via full page reload (aria-current). --}}
        <div class="govuk-tabs" data-module="govuk-tabs">
            <h2 class="govuk-tabs__title">{{ __('govuk_alpha.polish_federation.connections_tabs_label') }}</h2>
            <ul class="govuk-tabs__list" role="tablist">
                <li class="govuk-tabs__list-item {{ $tab === 'accepted' ? 'govuk-tabs__list-item--selected' : '' }}" role="presentation">
                    <a class="govuk-tabs__tab" href="{{ $tabHref('accepted') }}" id="tab-accepted" role="tab" aria-controls="panel-accepted" @if($tab === 'accepted') aria-selected="true" @else aria-selected="false" tabindex="-1" @endif>{{ __('govuk_alpha.fed2.connections.tab_accepted') }}</a>
                </li>
                <li class="govuk-tabs__list-item {{ $tab === 'received' ? 'govuk-tabs__list-item--selected' : '' }}" role="presentation">
                    <a class="govuk-tabs__tab" href="{{ $tabHref('received') }}" id="tab-received" role="tab" aria-controls="panel-received" @if($tab === 'received') aria-selected="true" @else aria-selected="false" tabindex="-1" @endif>{{ __('govuk_alpha.fed2.connections.tab_received') }}</a>
                </li>
                <li class="govuk-tabs__list-item {{ $tab === 'sent' ? 'govuk-tabs__list-item--selected' : '' }}" role="presentation">
                    <a class="govuk-tabs__tab" href="{{ $tabHref('sent') }}" id="tab-sent" role="tab" aria-controls="panel-sent" @if($tab === 'sent') aria-selected="true" @else aria-selected="false" tabindex="-1" @endif>{{ __('govuk_alpha.fed2.connections.tab_sent') }}</a>
                </li>
            </ul>

            {{-- Each panel contains the card list for that tab. --}}
            @foreach (['accepted', 'received', 'sent'] as $panelTab)
                @php
                    $isActive = ($tab === $panelTab);
                    $panelEmptyKey = match ($panelTab) {
                        'received' => 'empty_received',
                        'sent' => 'empty_sent',
                        default => 'empty_accepted',
                    };
                @endphp
                <div class="govuk-tabs__panel {{ !$isActive ? 'govuk-tabs__panel--hidden' : '' }}" id="panel-{{ $panelTab }}" role="tabpanel" aria-labelledby="tab-{{ $panelTab }}">
                    @if (!$isActive)
                        {{-- Non-active panels show nothing when JS is disabled. --}}
                    @elseif (empty($connections))
                        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.fed2.connections.' . $panelEmptyKey) }}</p></div>
                    @else
                        <div class="nexus-alpha-card-list" id="connections-list">
                            @foreach ($connections as $c)
                                @php
                                    $cName = trim((string) ($c['name'] ?? '')) ?: __('govuk_alpha.members.unknown_member');
                                    $cId = (int) ($c['id'] ?? 0);
                                    $cStatus = (string) ($c['status'] ?? '');
                                    $cDirection = (string) ($c['direction'] ?? '');
                                    $acceptHidden = ['tenantSlug' => $tenantSlug, 'id' => $cId];
                                @endphp
                                <article class="nexus-alpha-card">
                                    <h2 class="govuk-heading-s govuk-!-margin-bottom-1"><a class="govuk-link" href="{{ $memberHref($c) }}">{{ $cName }}</a></h2>
                                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.fed2.connections.community_label') }}: {{ $c['tenant_name'] ?? '' }}</p>

                                    @if ($cStatus === 'pending')
                                        <p class="govuk-body-s govuk-!-margin-bottom-2"><strong class="govuk-tag govuk-tag--yellow">{{ __('govuk_alpha.fed2.connections.pending_label') }}</strong></p>
                                    @endif

                                    {{-- Pending-incoming: accept and decline in ONE form with two submit buttons.
                                         This keeps both inside a single govuk-button-group with no wrapping form per button. --}}
                                    @if ($cStatus === 'pending' && $cDirection === 'incoming')
                                        <div class="govuk-button-group">
                                            <form method="post" action="{{ route('govuk-alpha.federation.connections.accept', $acceptHidden) }}">
                                                @csrf
                                                <button type="submit" class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.fed2.connections.accept') }}</button>
                                            </form>
                                            <form method="post" action="{{ route('govuk-alpha.federation.connections.reject', $acceptHidden) }}">
                                                @csrf
                                                <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.fed2.connections.reject') }}</button>
                                            </form>
                                        </div>
                                    @elseif ($cStatus === 'accepted')
                                        <div class="govuk-button-group">
                                            <form method="post" action="{{ route('govuk-alpha.federation.connections.remove', ['tenantSlug' => $tenantSlug, 'id' => $cId]) }}">
                                                @csrf
                                                <button type="submit" class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.fed2.connections.remove') }}</button>
                                            </form>
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endsection
