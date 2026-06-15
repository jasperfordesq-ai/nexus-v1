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
        <nav class="govuk-!-margin-bottom-6" aria-label="{{ __('govuk_alpha.fed2.connections.title') }}">
            <ul class="govuk-list">
                <li class="govuk-!-display-inline govuk-!-margin-right-4">
                    <a class="govuk-link {{ $tab === 'accepted' ? 'govuk-!-font-weight-bold' : '' }}" href="{{ $tabHref('accepted') }}" @if($tab === 'accepted') aria-current="page" @endif>{{ __('govuk_alpha.fed2.connections.tab_accepted') }}</a>
                </li>
                <li class="govuk-!-display-inline govuk-!-margin-right-4">
                    <a class="govuk-link {{ $tab === 'received' ? 'govuk-!-font-weight-bold' : '' }}" href="{{ $tabHref('received') }}" @if($tab === 'received') aria-current="page" @endif>{{ __('govuk_alpha.fed2.connections.tab_received') }}</a>
                </li>
                <li class="govuk-!-display-inline">
                    <a class="govuk-link {{ $tab === 'sent' ? 'govuk-!-font-weight-bold' : '' }}" href="{{ $tabHref('sent') }}" @if($tab === 'sent') aria-current="page" @endif>{{ __('govuk_alpha.fed2.connections.tab_sent') }}</a>
                </li>
            </ul>
        </nav>

        <div id="connections-list">
            @if (empty($connections))
                <p class="govuk-inset-text">{{ __('govuk_alpha.fed2.connections.' . $emptyKey) }}</p>
            @else
                <div class="nexus-alpha-card-list">
                    @foreach ($connections as $c)
                        @php
                            $cName = trim((string) ($c['name'] ?? '')) ?: __('govuk_alpha.members.unknown_member');
                            $cId = (int) ($c['id'] ?? 0);
                            $cStatus = (string) ($c['status'] ?? '');
                            $cDirection = (string) ($c['direction'] ?? '');
                        @endphp
                        <article class="nexus-alpha-card">
                            <h2 class="govuk-heading-s govuk-!-margin-bottom-1"><a class="govuk-link" href="{{ $memberHref($c) }}">{{ $cName }}</a></h2>
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.fed2.connections.community_label') }}: {{ $c['tenant_name'] ?? '' }}</p>

                            @if ($cStatus === 'pending')
                                <p class="govuk-body-s govuk-!-margin-bottom-2"><strong class="govuk-tag govuk-tag--yellow">{{ __('govuk_alpha.fed2.connections.pending_label') }}</strong></p>
                            @endif

                            <div class="nexus-alpha-actions govuk-button-group">
                                {{-- Received pending → accept / decline. Owner-scoped server-side. --}}
                                @if ($cStatus === 'pending' && $cDirection === 'incoming')
                                    <form method="post" action="{{ route('govuk-alpha.federation.connections.accept', ['tenantSlug' => $tenantSlug, 'id' => $cId]) }}" class="govuk-!-display-inline">
                                        @csrf
                                        <button type="submit" class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.fed2.connections.accept') }}</button>
                                    </form>
                                    <form method="post" action="{{ route('govuk-alpha.federation.connections.reject', ['tenantSlug' => $tenantSlug, 'id' => $cId]) }}" class="govuk-!-display-inline">
                                        @csrf
                                        <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.fed2.connections.reject') }}</button>
                                    </form>
                                @elseif ($cStatus === 'accepted')
                                    <form method="post" action="{{ route('govuk-alpha.federation.connections.remove', ['tenantSlug' => $tenantSlug, 'id' => $cId]) }}" class="govuk-!-display-inline">
                                        @csrf
                                        <button type="submit" class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.fed2.connections.remove') }}</button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
@endsection
