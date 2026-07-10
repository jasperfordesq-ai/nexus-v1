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
        $loadError = (bool) ($loadError ?? false);
        // Whitelist the ?status= values (same pattern as federation-member):
        // an arbitrary query value must never echo a raw translation key.
        $allowedStatuses = ['connection-accepted', 'connection-rejected', 'connection-removed', 'connection-action-failed'];
        $statusKey = (string) ($status ?? '');
        $statusKey = in_array($statusKey, $allowedStatuses, true) ? $statusKey : '';
        $statusText = $statusKey !== '' ? __('govuk_alpha.fed2.connections.status.' . $statusKey) : '';
        $statusIsError = in_array($statusKey, ['connection-action-failed'], true);

        $tabHref = fn (string $t): string => route('govuk-alpha.federation.connections.index', ['tenantSlug' => $tenantSlug, 'tab' => $t]);

        $membersIndexHref = route('govuk-alpha.federation.members.index', ['tenantSlug' => $tenantSlug]);

        $memberHref = function (array $c) use ($tenantSlug): string {
            // tenant_id is REQUIRED so the profile (where compose lives) scopes to the owning community.
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

    @include('accessible-frontend::partials.federation-nav')

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
            <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="fed-conn-status">
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
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.fed2.connections.not_available') }}</p></div>
    @else
        {{-- These tabs are full-page navigation links, NOT in-page JS tab panels:
             each link reloads with ?tab=. So we deliberately do NOT use the
             role="tab"/tablist/tabpanel + roving-tabindex ARIA (that would assert
             in-page panel switching and pull inactive links out of the keyboard
             tab order). Instead we mark the current tab with aria-current — the
             same pattern as the shared federation sub-nav. --}}
        <div class="govuk-tabs" data-module="govuk-tabs">
            <h2 class="govuk-tabs__title">{{ __('govuk_alpha.polish_federation.connections_tabs_label') }}</h2>
            <ul class="govuk-tabs__list">
                <li class="govuk-tabs__list-item {{ $tab === 'accepted' ? 'govuk-tabs__list-item--selected' : '' }}">
                    <a class="govuk-tabs__tab" href="{{ $tabHref('accepted') }}" id="tab-accepted" @if($tab === 'accepted') aria-current="page" @endif>{{ __('govuk_alpha.fed2.connections.tab_accepted') }}</a>
                </li>
                <li class="govuk-tabs__list-item {{ $tab === 'received' ? 'govuk-tabs__list-item--selected' : '' }}">
                    <a class="govuk-tabs__tab" href="{{ $tabHref('received') }}" id="tab-received" @if($tab === 'received') aria-current="page" @endif>{{ __('govuk_alpha.fed2.connections.tab_received') }}</a>
                </li>
                <li class="govuk-tabs__list-item {{ $tab === 'sent' ? 'govuk-tabs__list-item--selected' : '' }}">
                    <a class="govuk-tabs__tab" href="{{ $tabHref('sent') }}" id="tab-sent" @if($tab === 'sent') aria-current="page" @endif>{{ __('govuk_alpha.fed2.connections.tab_sent') }}</a>
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
                <div class="govuk-tabs__panel {{ !$isActive ? 'govuk-tabs__panel--hidden' : '' }}" id="panel-{{ $panelTab }}">
                    @if (!$isActive)
                        {{-- Non-active panels show nothing when JS is disabled. --}}
                    @elseif ($loadError)
                        {{-- FC-1: load failure replaces the empty state with an error summary + try-again link. --}}
                        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                            <div role="alert">
                                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                                <div class="govuk-error-summary__body">
                                    <p class="govuk-body">{{ __('govuk_alpha.fed2.connections.load_error') }}</p>
                                    <ul class="govuk-list govuk-error-summary__list">
                                        <li><a href="{{ $tabHref($panelTab) }}">{{ __('govuk_alpha.fed2.connections.try_again') }}</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    @elseif (empty($connections))
                        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.fed2.connections.' . $panelEmptyKey) }}</p></div>
                        @if ($panelTab === 'accepted')
                            {{-- FC-4: accepted empty state offers a route to browse members. --}}
                            <a href="{{ $membersIndexHref }}" role="button" draggable="false" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.fed2.connections.browse_members') }}</a>
                        @endif
                    @else
                        <div class="nexus-alpha-card-list" id="connections-list">
                            @foreach ($connections as $c)
                                @php
                                    $cName = trim((string) ($c['name'] ?? '')) ?: __('govuk_alpha.members.unknown_member');
                                    $cId = (int) ($c['id'] ?? 0);
                                    $cStatus = (string) ($c['status'] ?? '');
                                    $cDirection = (string) ($c['direction'] ?? '');
                                    $cMessage = trim((string) ($c['message'] ?? ''));
                                    $cCreatedRaw = $c['created_at'] ?? null;
                                    $cCreated = $cCreatedRaw ? \Illuminate\Support\Carbon::parse($cCreatedRaw)->translatedFormat('j F Y') : '';
                                    $isReceived = ($cStatus === 'pending' && $cDirection === 'incoming');
                                    $isSent = ($cStatus === 'pending' && $cDirection === 'outgoing');
                                    $isAccepted = ($cStatus === 'accepted');
                                    $actionHidden = ['tenantSlug' => $tenantSlug, 'id' => $cId];
                                @endphp
                                <article class="nexus-alpha-card">
                                    <h2 class="govuk-heading-s govuk-!-margin-bottom-1"><a class="govuk-link" href="{{ $memberHref($c) }}">{{ $cName }}</a></h2>
                                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">
                                        <strong class="govuk-tag govuk-tag--grey">{{ __('govuk_alpha.fed2.connections.community_label') }}: {{ $c['tenant_name'] ?? '' }}</strong>
                                    </p>

                                    {{-- FC-3: every card shows when the request was made. --}}
                                    @if ($cCreated !== '')
                                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.fed2.connections.created_label') }}: {{ $cCreated }}</p>
                                    @endif

                                    {{-- FC-5: the yellow "Pending" tag is for OUTGOING/sent requests only. --}}
                                    @if ($isSent)
                                        <p class="govuk-body-s govuk-!-margin-bottom-2"><strong class="govuk-tag govuk-tag--yellow">{{ __('govuk_alpha.fed2.connections.pending_label') }}</strong></p>
                                    @endif

                                    {{-- FC-2: incoming requests with a note show the requester's message. --}}
                                    @if ($isReceived && $cMessage !== '')
                                        <div class="govuk-inset-text govuk-!-margin-top-2 govuk-!-margin-bottom-2">
                                            <p class="govuk-body govuk-!-margin-bottom-0">
                                                <span class="govuk-visually-hidden">{{ __('govuk_alpha.fed2.connections.request_message_label') }}: </span>{{ $cMessage }}
                                            </p>
                                        </div>
                                    @endif

                                    {{-- Pending-incoming: accept and decline in two forms inside one button group;
                                         no wrapping form per button. --}}
                                    @if ($isReceived)
                                        <div class="govuk-button-group">
                                            <form method="post" action="{{ route('govuk-alpha.federation.connections.accept', $actionHidden) }}">
                                                @csrf
                                                <button type="submit" class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.fed2.connections.accept') }}</button>
                                            </form>
                                            <form method="post" action="{{ route('govuk-alpha.federation.connections.reject', $actionHidden) }}">
                                                @csrf
                                                <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.fed2.connections.reject') }}</button>
                                            </form>
                                        </div>
                                    @elseif ($isAccepted)
                                        {{-- FC-1 (FC-5 list): accepted cards get a "Send a message" link to the profile (compose) plus Remove. --}}
                                        <div class="govuk-button-group">
                                            <a class="govuk-link" href="{{ $memberHref($c) }}">{{ __('govuk_alpha.fed2.connections.message') }}</a>
                                            <form method="post" action="{{ route('govuk-alpha.federation.connections.remove', $actionHidden) }}">
                                                @csrf
                                                <button type="submit" class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.fed2.connections.remove') }}</button>
                                            </form>
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        </div>

                        {{-- GOV.UK pagination (previous / next page links, no JS) —
                             mirrors the wallet history pattern. --}}
                        @php
                            $connPage = (int) ($page ?? 1);
                            $connHasMore = (bool) ($hasMore ?? false);
                            $pageHref = fn (int $p): string => route('govuk-alpha.federation.connections.index', array_filter([
                                'tenantSlug' => $tenantSlug,
                                'tab' => $panelTab,
                                'page' => $p > 1 ? $p : null,
                            ])) . '#connections-list';
                        @endphp
                        @if ($connPage > 1 || $connHasMore)
                            <nav class="govuk-pagination" aria-label="{{ __('govuk_alpha.fed2.connections.title') }}">
                                @if ($connPage > 1)
                                    <div class="govuk-pagination__prev">
                                        <a class="govuk-link govuk-pagination__link" href="{{ $pageHref($connPage - 1) }}" rel="prev">
                                            <svg class="govuk-pagination__icon govuk-pagination__icon--prev" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                                                <path d="m6.5938-0.0078125-6.7266 6.7266 6.7441 6.4062 1.377-1.449-4.1856-3.9768h12.896v-2h-12.984l4.2931-4.293-1.414-1.414z"></path>
                                            </svg>
                                            <span class="govuk-pagination__link-title">{{ __('govuk_alpha.fed2.connections.pagination_previous') }}</span>
                                        </a>
                                    </div>
                                @endif
                                @if ($connHasMore)
                                    <div class="govuk-pagination__next">
                                        <a class="govuk-link govuk-pagination__link" href="{{ $pageHref($connPage + 1) }}" rel="next">
                                            <span class="govuk-pagination__link-title">{{ __('govuk_alpha.fed2.connections.pagination_next') }}</span>
                                            <svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                                                <path d="m8.107-0.0078125-1.4136 1.414 4.2926 4.293h-12.986v2h12.896l-4.1855 3.9766 1.377 1.4492 6.7441-6.4062-6.7246-6.7266z"></path>
                                            </svg>
                                        </a>
                                    </div>
                                @endif
                            </nav>
                        @endif
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endsection
