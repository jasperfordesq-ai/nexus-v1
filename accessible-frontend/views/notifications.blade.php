{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $unreadTotal = (int) ($notificationCounts['total'] ?? 0);
        $dateFmt = fn ($v): ?string => $v ? \Illuminate\Support\Carbon::parse($v)->diffForHumans() : null;

        // Notification messages are sometimes stored as i18n keys (e.g.
        // 'svc_notifications_2.message.new_message') rather than plain text.
        $resolveText = function (string $raw): string {
            $raw = trim($raw);
            if ($raw === '') { return ''; }
            if (preg_match('/^[a-z0-9_]+\.[a-z0-9_.]+$/', $raw) && \Illuminate\Support\Facades\Lang::has($raw)) {
                return __($raw);
            }
            return $raw;
        };

        // Map a notification type to a category + GOV.UK tag colour.
        $category = function (string $type): string {
            $t = strtolower($type);
            return match (true) {
                str_contains($t, 'message') => 'messages',
                str_contains($t, 'connection') || str_contains($t, 'friend') => 'connections',
                str_contains($t, 'review') => 'reviews',
                str_contains($t, 'transaction') || str_contains($t, 'payment') || str_contains($t, 'credit') => 'transactions',
                str_contains($t, 'event') => 'events',
                str_contains($t, 'group') => 'groups',
                str_contains($t, 'listing') || str_contains($t, 'match') => 'listings',
                str_contains($t, 'job') => 'jobs',
                str_contains($t, 'safeguard') || str_contains($t, 'broker') => 'safeguarding',
                str_contains($t, 'security') || str_contains($t, 'password') || str_contains($t, '2fa') || str_contains($t, 'passkey') => 'security',
                str_contains($t, 'like') || str_contains($t, 'comment') || str_contains($t, 'reaction') || str_contains($t, 'mention') || str_contains($t, 'post') => 'social',
                str_contains($t, 'idea') => 'ideation',
                $t === 'system' || str_contains($t, 'announce') || str_contains($t, 'welcome') || str_contains($t, 'badge') || str_contains($t, 'achievement') || str_contains($t, 'level') => 'system',
                default => 'other',
            };
        };
        $catColour = [
            'messages' => 'blue', 'connections' => 'purple', 'reviews' => 'yellow',
            'transactions' => 'green', 'social' => 'pink', 'events' => 'turquoise',
            'groups' => 'orange', 'listings' => 'blue', 'jobs' => 'green',
            'safeguarding' => 'red', 'security' => 'red', 'ideation' => 'purple',
            'system' => 'grey', 'other' => 'grey',
        ];
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha.notifications.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.notifications.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.notifications.description') }}</p>

    @if (in_array($status, ['marked-read', 'notification-deleted', 'notification-marked-read', 'all-notifications-deleted'], true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="notif-status">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="notif-status">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.notifications.states.' . $status) }}</p>
            </div>
        </div>
    @endif

    <div class="govuk-!-margin-bottom-4">
        <p class="govuk-body govuk-!-margin-bottom-2">
            <a class="govuk-link govuk-link--no-visited-state @if (!$notificationsUnreadOnly) govuk-!-font-weight-bold @endif"
               href="{{ route('govuk-alpha.notifications.index', ['tenantSlug' => $tenantSlug]) }}"
               @if (!$notificationsUnreadOnly) aria-current="true" @endif>{{ __('govuk_alpha.notifications.all_filter') }}</a>
            &nbsp;·&nbsp;
            <a class="govuk-link govuk-link--no-visited-state @if ($notificationsUnreadOnly) govuk-!-font-weight-bold @endif"
               href="{{ route('govuk-alpha.notifications.index', ['tenantSlug' => $tenantSlug, 'filter' => 'unread']) }}"
               @if ($notificationsUnreadOnly) aria-current="true" @endif>{{ __('govuk_alpha.notifications.unread_filter') }}@if ($unreadTotal > 0) ({{ $unreadTotal }})@endif</a>
        </p>
        <div class="govuk-button-group govuk-!-margin-bottom-2">
            @if ($unreadTotal > 0)
                <form method="post" action="{{ route('govuk-alpha.notifications.read-all', ['tenantSlug' => $tenantSlug]) }}">
                    @csrf
                    <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.notifications.mark_all_read') }}</button>
                </form>
            @endif
        </div>
        @if (!empty($notifications))
            <details class="govuk-details govuk-!-margin-bottom-2" data-module="govuk-details">
                <summary class="govuk-details__summary">
                    <span class="govuk-details__summary-text">{{ __('govuk_alpha.notifications.delete_all') }}</span>
                </summary>
                <div class="govuk-details__text">
                    <div class="govuk-warning-text">
                        <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                        <strong class="govuk-warning-text__text">
                            <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.warning_prefix') }}</span>
                            {{ __('govuk_alpha.polish_discovery.notifications_delete_confirm_warning') }}
                        </strong>
                    </div>
                    <form method="post" action="{{ route('govuk-alpha.notifications.delete-all', ['tenantSlug' => $tenantSlug]) }}">
                        @csrf
                        <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.polish_discovery.notifications_delete_confirm_button') }}</button>
                    </form>
                </div>
            </details>
        @endif
    </div>

    @if (empty($notifications))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.notifications.empty') }}</p></div>
    @else
        @foreach ($notifications as $n)
            @php
                $nId = (int) ($n['id'] ?? 0);
                $nText = $resolveText((string) ($n['message'] ?? ($n['title'] ?? '')));
                $isGrouped = (bool) ($n['is_grouped'] ?? false);
                $groupKey = (string) ($n['group_key'] ?? '');
                $groupCount = (int) ($n['group_count'] ?? 1);
                $actors = is_array($n['actors'] ?? null) ? $n['actors'] : [];
                $actorNames = array_values(array_filter(array_map(fn ($a) => trim((string) ($a['name'] ?? '')), $actors)));
                $remaining = (int) ($n['remaining_count'] ?? 0);
                // Unread = the single's flag, or "not all read" for a group.
                $nUnread = $isGrouped ? !($n['all_read'] ?? false) : !($n['is_read'] ?? false);
                $nWhen = $dateFmt($n['created_at'] ?? null);
                $nCat = $category((string) ($n['type'] ?? 'system'));
                $nColour = $catColour[$nCat] ?? 'grey';
                $nLink = trim((string) ($n['link'] ?? ''));
                $nHref = $nLink !== '' ? url('/' . $tenantSlug . '/accessible' . (\Illuminate\Support\Str::startsWith($nLink, '/') ? $nLink : '/' . $nLink)) : null;
            @endphp
            <div class="nexus-alpha-card govuk-!-margin-bottom-3">
                <div class="nexus-alpha-module-row">
                    <p class="govuk-body govuk-!-margin-bottom-1">
                        <strong class="govuk-tag govuk-tag--{{ $nColour }}">{{ __('govuk_alpha.notifications.types.' . $nCat) }}</strong>
                        @if ($isGrouped)<strong class="govuk-tag govuk-tag--purple">{{ __('govuk_alpha.notifications.group_tag', ['count' => $groupCount]) }}</strong> @endif
                        @if ($nUnread)<strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha.notifications.new_tag') }}</strong> @endif
                        @if ($nHref)<a class="govuk-link govuk-link--no-visited-state" href="{{ $nHref }}">{{ $nText }}</a>@else{{ $nText }}@endif
                    </p>
                    <div class="nexus-alpha-actions">
                        @if ($isGrouped && $nUnread && $groupKey !== '')
                            <form method="post" action="{{ route('govuk-alpha.notifications.group-read', ['tenantSlug' => $tenantSlug]) }}" class="nexus-alpha-linkform">
                                @csrf
                                <input type="hidden" name="group_key" value="{{ $groupKey }}">
                                <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.notifications.mark_group_read') }}</button>
                            </form>
                        @elseif (!$isGrouped && $nUnread)
                            <form method="post" action="{{ route('govuk-alpha.notifications.mark-read', ['tenantSlug' => $tenantSlug, 'id' => $nId]) }}" class="nexus-alpha-linkform">
                                @csrf
                                <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.notifications.mark_read') }}</button>
                            </form>
                        @endif
                        @unless ($isGrouped)
                            <form method="post" action="{{ route('govuk-alpha.notifications.delete', ['tenantSlug' => $tenantSlug, 'id' => $nId]) }}" class="nexus-alpha-linkform">
                                @csrf
                                <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.notifications.delete') }}</button>
                            </form>
                        @endunless
                    </div>
                </div>
                @if ($isGrouped && !empty($actorNames))
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">
                        @if ($remaining > 0)
                            {{ __('govuk_alpha.notifications.actors_and_more', ['names' => implode(', ', $actorNames), 'count' => $remaining]) }}
                        @else
                            {{ __('govuk_alpha.notifications.actors_label', ['names' => implode(', ', $actorNames)]) }}
                        @endif
                    </p>
                @endif
                @if ($nWhen)
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">{{ $nWhen }}</p>
                @endif
            </div>
        @endforeach

        @if ($notificationsHasMore && $notificationsCursor)
            <p class="govuk-body govuk-!-margin-top-4">
                <a class="govuk-link" href="{{ route('govuk-alpha.notifications.index', array_filter(['tenantSlug' => $tenantSlug, 'filter' => $notificationsUnreadOnly ? 'unread' : null, 'cursor' => $notificationsCursor])) }}">{{ __('govuk_alpha.notifications.load_more') }}</a>
            </p>
        @endif
    @endif
@endsection
