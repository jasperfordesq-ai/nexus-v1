{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $gName = trim((string) ($group['name'] ?? '')) ?: __('govuk_alpha.groups.title');
        $gPrivate = ($group['visibility'] ?? 'public') !== 'public';
        $gCount = (int) ($group['member_count'] ?? count($groupMembers));
        $gId = (int) ($group['id'] ?? 0);
        $viewerRole = $group['viewer_membership']['role'] ?? ($group['my_role'] ?? null);
        $isAdmin = in_array((string) $viewerRole, ['owner', 'admin'], true);
        $isPending = (($group['viewer_membership']['status'] ?? ($group['my_status'] ?? null)) === 'pending');
        $successStates = ['group-joined', 'group-left', 'group-created', 'group-updated'];
        $failStates = ['group-failed', 'group-update-failed', 'group-delete-failed'];
        // WAVE T1-GROUPS feed-compose outcomes (keyed under groups_t1.states).
        $t1SuccessStates = ['group-posted'];
        $t1FailStates = ['group-post-empty', 'group-post-failed', 'group-post-forbidden'];
    @endphp

    @if (in_array($status, $successStates, true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-live="polite" aria-labelledby="grp-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="grp-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.groups.states.' . $status) }}</p></div>
        </div>
    @elseif (in_array($status, $failStates, true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert"><h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p class="govuk-body">{{ __('govuk_alpha.groups.states.' . $status) }}</p></div></div>
        </div>
    @elseif (in_array($status, $t1SuccessStates, true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-live="polite" aria-labelledby="grp-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="grp-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.groups_t1.states.' . $status) }}</p></div>
        </div>
    @elseif (in_array($status, $t1FailStates, true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert"><h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p class="govuk-body">{{ __('govuk_alpha.groups_t1.states.' . $status) }}</p></div></div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ __('govuk_alpha.groups.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <div class="nexus-alpha-module-row">
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">{{ $gName }}</h1>
        <strong class="govuk-tag {{ $gPrivate ? 'govuk-tag--grey' : 'govuk-tag--green' }}">{{ $gPrivate ? __('govuk_alpha.groups.visibility_private') : __('govuk_alpha.groups.visibility_public') }}</strong>
    </div>
    <p class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha.groups.members_count', ['count' => $gCount]) }}</p>

    @if (trim((string) ($group['description'] ?? '')) !== '')
        <p class="govuk-body-l">{{ $group['description'] }}</p>
    @endif

    <div class="govuk-!-margin-bottom-6">
        @if ($isMember)
            <p class="govuk-inset-text">{{ __('govuk_alpha.groups.you_are_member') }}</p>
            <p class="govuk-body">
                <a class="govuk-link" href="{{ route('govuk-alpha.groups.discussions.index', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}">{{ __('govuk_alpha.groups.discussions.link') }}</a>
            </p>
            <form method="post" action="{{ route('govuk-alpha.groups.leave', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}">
                @csrf
                <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.groups.leave') }}</button>
            </form>
        @elseif ($isPending)
            <p class="govuk-inset-text">{{ __('govuk_alpha.groups.pending_member') }}</p>
        @else
            <form method="post" action="{{ route('govuk-alpha.groups.join', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}">
                @csrf
                <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.groups.join') }}</button>
            </form>
        @endif
    </div>

    @if ($isAdmin)
        <div class="nexus-alpha-actions govuk-!-margin-bottom-6">
            <a class="govuk-link govuk-!-margin-right-4" href="{{ route('govuk-alpha.groups.edit', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}">{{ __('govuk_alpha.groups.edit.link') }}</a>
            <a class="govuk-link govuk-!-margin-right-4" href="{{ route('govuk-alpha.groups.manage', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}">{{ __('govuk_alpha.groups.manage.link') }}</a>
        </div>
    @endif

    @if (!empty($groupMembers))
        <h2 class="govuk-heading-l">{{ __('govuk_alpha.groups.members_title') }}</h2>
        <ul class="govuk-list">
            @foreach ($groupMembers as $m)
                @php
                    $mName = trim((string) ($m['name'] ?? '')) ?: trim((string) ($m['first_name'] ?? '') . ' ' . (string) ($m['last_name'] ?? ''));
                    $mId = (int) ($m['user_id'] ?? ($m['id'] ?? 0));
                @endphp
                @if ($mName !== '')
                    <li>@if ($mId > 0)<a class="govuk-link" href="{{ route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $mId]) }}">{{ $mName }}</a>@else{{ $mName }}@endif</li>
                @endif
            @endforeach
        </ul>
    @endif

    {{-- ===== WAVE T1-GROUPS: events tab ===== --}}
    @php
        $groupEvents = $groupEvents ?? [];
        $groupFeed = $groupFeed ?? [];
        $groupCanParticipate = $groupCanParticipate ?? $isMember;
        $formatEventDateTime = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y, g:ia') : null;
    @endphp

    <h2 class="govuk-heading-l" id="group-events">{{ __('govuk_alpha.groups_t1.events_title') }}</h2>
    <p class="govuk-body">{{ __('govuk_alpha.groups_t1.events_description') }}</p>
    @if (!$groupCanParticipate && $gPrivate)
        <p class="govuk-inset-text">{{ __('govuk_alpha.groups_t1.events_members_only') }}</p>
    @elseif (empty($groupEvents))
        <p class="govuk-inset-text">{{ __('govuk_alpha.groups_t1.events_empty') }}</p>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($groupEvents as $event)
                @php
                    $evId = (int) ($event['id'] ?? 0);
                    $evTitle = trim((string) ($event['title'] ?? ''));
                    $evStart = $formatEventDateTime($event['start_time'] ?? $event['start_date'] ?? null);
                    $evLocation = trim((string) ($event['location'] ?? ''));
                @endphp
                @if ($evId > 0 && $evTitle !== '')
                    <article class="nexus-alpha-card">
                        <div class="nexus-alpha-listing-row">
                            @if (!empty($event['cover_image']))
                                <div class="nexus-alpha-listing-row__media">
                                    <img class="nexus-alpha-card-thumb" src="{{ $event['cover_image'] }}" alt="" width="120" height="90" loading="lazy" decoding="async">
                                </div>
                            @endif
                            <div class="nexus-alpha-listing-row__body">
                                <h3 class="govuk-heading-m govuk-!-margin-bottom-2">
                                    <a class="govuk-link" href="{{ route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $evId]) }}">{{ $evTitle }}</a>
                                </h3>
                                <dl class="nexus-alpha-inline-list">
                                    @if ($evStart)
                                        <div>
                                            <dt>{{ __('govuk_alpha.groups_t1.event_starts') }}</dt>
                                            <dd>{{ $evStart }}</dd>
                                        </div>
                                    @endif
                                    <div>
                                        <dt>{{ __('govuk_alpha.groups_t1.event_location') }}</dt>
                                        <dd>{{ $evLocation !== '' ? $evLocation : __('govuk_alpha.groups_t1.event_online') }}</dd>
                                    </div>
                                </dl>
                                <a class="govuk-link govuk-link--no-visited-state" href="{{ route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $evId]) }}">{{ __('govuk_alpha.actions.view_details') }}</a>
                            </div>
                        </div>
                    </article>
                @endif
            @endforeach
        </div>
    @endif

    {{-- ===== WAVE T1-GROUPS: group feed tab (members only) ===== --}}
    <h2 class="govuk-heading-l" id="group-feed">{{ __('govuk_alpha.groups_t1.feed_title') }}</h2>
    <p class="govuk-body">{{ __('govuk_alpha.groups_t1.feed_description') }}</p>

    @if (!$groupCanParticipate)
        <p class="govuk-inset-text">{{ __('govuk_alpha.groups_t1.feed_members_only') }}</p>
    @else
        <form method="post" action="{{ route('govuk-alpha.groups.feed.store', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}" enctype="multipart/form-data" class="govuk-!-margin-bottom-7">
            @csrf
            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--m" for="group-feed-content">{{ __('govuk_alpha.groups_t1.compose_label') }}</label>
                <div id="group-feed-content-hint" class="govuk-hint">{{ __('govuk_alpha.groups_t1.compose_hint') }}</div>
                <textarea class="govuk-textarea" id="group-feed-content" name="content" rows="4" aria-describedby="group-feed-content-hint"></textarea>
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="group-feed-image">{{ __('govuk_alpha.groups_t1.compose_image_label') }}</label>
                <div id="group-feed-image-hint" class="govuk-hint">{{ __('govuk_alpha.groups_t1.compose_image_hint') }}</div>
                <input class="govuk-file-upload" id="group-feed-image" name="image" type="file" accept="image/jpeg,image/png,image/gif,image/webp" aria-describedby="group-feed-image-hint">
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="group-feed-image-alt">{{ __('govuk_alpha.groups_t1.compose_image_alt_label') }}</label>
                <div id="group-feed-image-alt-hint" class="govuk-hint">{{ __('govuk_alpha.groups_t1.compose_image_alt_hint') }}</div>
                <input class="govuk-input" id="group-feed-image-alt" name="image_alt" type="text" maxlength="500" aria-describedby="group-feed-image-alt-hint">
            </div>
            <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.groups_t1.compose_submit') }}</button>
        </form>

        @if (empty($groupFeed))
            <p class="govuk-inset-text">{{ __('govuk_alpha.groups_t1.feed_empty') }}</p>
        @else
            <div class="nexus-alpha-card-list">
                @foreach ($groupFeed as $item)
                    @php
                        $postAuthor = trim((string) ($item['author']['name'] ?? $item['author_name'] ?? '')) ?: __('govuk_alpha.feed.unknown_author');
                        $postCreatedAt = !empty($item['created_at']) ? \Illuminate\Support\Carbon::parse($item['created_at']) : null;
                        $postAvatar = $item['author']['avatar_url'] ?? null;
                        $postContent = trim(html_entity_decode(strip_tags((string) ($item['content'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                        $postParagraphs = $postContent !== '' ? (preg_split('/\R{2,}/u', $postContent) ?: []) : [];
                        $postMedia = is_array($item['media'] ?? null) ? $item['media'] : [];
                        if (empty($postMedia) && !empty($item['image_url'])) {
                            $postMedia = [['file_url' => $item['image_url'], 'thumbnail_url' => null, 'alt_text' => null]];
                        }
                    @endphp
                    <article class="nexus-alpha-card">
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">
                            @if (!empty($postAvatar))
                                <img class="nexus-alpha-avatar nexus-alpha-avatar--small" src="{{ $postAvatar }}" alt="" loading="lazy" decoding="async" width="32" height="32">
                            @endif
                            {{ __('govuk_alpha.groups_t1.feed_posted_by', ['name' => $postAuthor]) }}
                            @if ($postCreatedAt)
                                <span aria-hidden="true"> | </span>
                                <span class="govuk-visually-hidden">{{ __('govuk_alpha.groups_t1.feed_posted_on_prefix') }}</span>
                                <time datetime="{{ $postCreatedAt->toIso8601String() }}">{{ $postCreatedAt->translatedFormat('j F Y, H:i') }}</time>
                            @endif
                        </p>
                        @foreach ($postParagraphs as $paragraph)
                            @if (trim($paragraph) !== '')
                                <p class="govuk-body">{!! nl2br(e(trim($paragraph))) !!}</p>
                            @endif
                        @endforeach
                        @if (!empty($postMedia))
                            <ul class="nexus-alpha-feed-media" data-count="{{ min(count($postMedia), 4) }}">
                                @foreach (array_slice($postMedia, 0, 4) as $media)
                                    @php
                                        $fullUrl = $media['file_url'] ?? null;
                                        $thumbUrl = $media['thumbnail_url'] ?? $fullUrl;
                                        $altText = trim((string) ($media['alt_text'] ?? '')) !== ''
                                            ? $media['alt_text']
                                            : __('govuk_alpha.groups_t1.image_alt', ['group' => $gName]);
                                    @endphp
                                    @if ($fullUrl)
                                        <li>
                                            <a href="{{ $fullUrl }}" target="_blank" rel="noopener noreferrer">
                                                <img src="{{ $thumbUrl }}" alt="{{ $altText }}" class="nexus-alpha-feed-image" loading="lazy">
                                            </a>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    @endif
@endsection
