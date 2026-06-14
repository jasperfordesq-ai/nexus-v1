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
            return $full !== '' ? $full : __('govuk_alpha.members.unknown_member');
        };
        $partnerLoc = fn ($p): string => is_array($p) ? trim((string) ($p['location'] ?? '')) : '';
        $counts = $connectionCounts ?? ['received' => 0, 'sent' => 0, 'total_friends' => 0];
    @endphp

    <span class="govuk-caption-xl" id="connections-top">{{ __('govuk_alpha.connections.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.connections.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.connections.description') }}</p>

    @if (in_array($status, ['connection-accepted', 'connection-declined', 'connection-removed'], true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-live="polite" aria-labelledby="conn-status-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="conn-status-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">
                    @switch($status)
                        @case('connection-accepted'){{ __('govuk_alpha.connections.states.accepted') }}@break
                        @case('connection-declined'){{ __('govuk_alpha.connections.states.declined') }}@break
                        @default{{ __('govuk_alpha.connections.states.removed') }}
                    @endswitch
                </p>
            </div>
        </div>
    @elseif ($status === 'connection-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p class="govuk-body">{{ __('govuk_alpha.connections.states.failed') }}</p>
                </div>
            </div>
        </div>
    @endif

    <p class="govuk-body">{{ __('govuk_alpha.connections.summary', ['received' => (int) ($counts['received'] ?? 0), 'sent' => (int) ($counts['sent'] ?? 0), 'total' => (int) ($counts['total_friends'] ?? 0)]) }}</p>

    <section aria-labelledby="received-heading">
        <h2 class="govuk-heading-l govuk-!-margin-top-6" id="received-heading">{{ __('govuk_alpha.connections.received_title') }}</h2>
        @if (empty($receivedRequests))
            <p class="govuk-inset-text">{{ __('govuk_alpha.connections.received_empty') }}</p>
        @else
            @foreach ($receivedRequests as $c)
                @php $p = $c['partner'] ?? $c['user'] ?? []; $cid = (int) ($c['connection_id'] ?? $c['id'] ?? 0); @endphp
                <div class="nexus-alpha-card govuk-!-margin-bottom-4">
                    <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $partnerName($p) }}</h3>
                    @if ($partnerLoc($p) !== '')
                        <p class="govuk-hint govuk-!-font-size-16 govuk-!-margin-bottom-2">{{ $partnerLoc($p) }}</p>
                    @endif
                    <p class="govuk-body-s govuk-!-margin-bottom-3">{{ __('govuk_alpha.connections.wants_to_connect') }}</p>
                    <div class="nexus-alpha-actions">
                        <form method="post" action="{{ route('govuk-alpha.connections.accept', ['tenantSlug' => $tenantSlug, 'id' => $cid]) }}" class="nexus-alpha-linkform">
                            @csrf
                            <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.connections.accept_button') }}</button>
                        </form>
                        <form method="post" action="{{ route('govuk-alpha.connections.decline', ['tenantSlug' => $tenantSlug, 'id' => $cid]) }}" class="nexus-alpha-linkform">
                            @csrf
                            <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.connections.decline_button') }}</button>
                        </form>
                        @if (!empty($p['id']))
                            <a class="govuk-link govuk-link--no-visited-state" href="{{ route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $p['id']]) }}">{{ __('govuk_alpha.connections.view_profile') }}</a>
                        @endif
                    </div>
                </div>
            @endforeach
        @endif
    </section>

    <section aria-labelledby="accepted-heading">
        <h2 class="govuk-heading-l govuk-!-margin-top-6" id="accepted-heading">{{ __('govuk_alpha.connections.accepted_title') }}</h2>
        @if (empty($acceptedConnections))
            <p class="govuk-inset-text">{{ __('govuk_alpha.connections.accepted_empty') }}</p>
        @else
            @foreach ($acceptedConnections as $c)
                @php $p = $c['partner'] ?? $c['user'] ?? []; $cid = (int) ($c['connection_id'] ?? $c['id'] ?? 0); @endphp
                <div class="nexus-alpha-card govuk-!-margin-bottom-4">
                    <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $partnerName($p) }}</h3>
                    @if ($partnerLoc($p) !== '')
                        <p class="govuk-hint govuk-!-font-size-16 govuk-!-margin-bottom-2">{{ $partnerLoc($p) }}</p>
                    @endif
                    <div class="nexus-alpha-actions">
                        @if (!empty($p['id']))
                            <a class="govuk-link govuk-link--no-visited-state" href="{{ route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $p['id']]) }}">{{ __('govuk_alpha.connections.view_profile') }}</a>
                        @endif
                        <form method="post" action="{{ route('govuk-alpha.connections.remove', ['tenantSlug' => $tenantSlug, 'id' => $cid]) }}" class="nexus-alpha-linkform">
                            @csrf
                            <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.connections.remove_button') }}</button>
                        </form>
                    </div>
                </div>
            @endforeach
        @endif
    </section>

    <section aria-labelledby="sent-heading">
        <h2 class="govuk-heading-l govuk-!-margin-top-6" id="sent-heading">{{ __('govuk_alpha.connections.sent_title') }}</h2>
        @if (empty($sentRequests))
            <p class="govuk-inset-text">{{ __('govuk_alpha.connections.sent_empty') }}</p>
        @else
            @foreach ($sentRequests as $c)
                @php $p = $c['partner'] ?? $c['user'] ?? []; $cid = (int) ($c['connection_id'] ?? $c['id'] ?? 0); @endphp
                <div class="nexus-alpha-card govuk-!-margin-bottom-4">
                    <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $partnerName($p) }}</h3>
                    <p class="govuk-body-s govuk-!-margin-bottom-3">{{ __('govuk_alpha.connections.awaiting_response') }}</p>
                    <div class="nexus-alpha-actions">
                        @if (!empty($p['id']))
                            <a class="govuk-link govuk-link--no-visited-state" href="{{ route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $p['id']]) }}">{{ __('govuk_alpha.connections.view_profile') }}</a>
                        @endif
                        <form method="post" action="{{ route('govuk-alpha.connections.remove', ['tenantSlug' => $tenantSlug, 'id' => $cid]) }}" class="nexus-alpha-linkform">
                            @csrf
                            <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.connections.cancel_button') }}</button>
                        </form>
                    </div>
                </div>
            @endforeach
        @endif
    </section>
@endsection
