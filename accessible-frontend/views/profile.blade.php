{{-- Copyright (c) 2024-2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $avatar = $profile['avatar_url'] ?? null;
        $joinedAt = !empty($profile['created_at']) ? \Illuminate\Support\Carbon::parse($profile['created_at'])->translatedFormat('j F Y') : null;
        $profileType = ($profile['profile_type'] ?? 'individual') === 'organisation' ? 'organisation' : 'individual';
        $rating = $profileStats['rating'] ?? null;
    @endphp

    @if (!($isOwnProfile ?? false))
        <a class="govuk-back-link" href="{{ route('govuk-alpha.members.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_members') }}</a>
    @endif

    @if (($status ?? '') === 'profile-updated')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-labelledby="profile-status-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="profile-status-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.profile_settings.success') }}</p>
            </div>
        </div>
    @endif

    @php
        $connectionStatuses = ['connection-sent', 'connection-accepted', 'connection-declined', 'connection-cancelled', 'connection-removed', 'connection-failed', 'endorsement-added', 'endorsement-removed', 'endorsement-failed'];
    @endphp
    @if (in_array(($status ?? ''), $connectionStatuses, true))
        @php $isFailure = in_array($status, ['connection-failed', 'endorsement-failed'], true); @endphp
        <div class="govuk-notification-banner {{ $isFailure ? '' : 'govuk-notification-banner--success' }}" data-module="govuk-notification-banner" role="region" aria-labelledby="connection-status-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="connection-status-title">{{ $isFailure ? __('govuk_alpha.states.important') : __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.states.' . $status) }}</p>
            </div>
        </div>
    @endif

    <div class="nexus-alpha-profile-hero">
        <div class="nexus-alpha-profile-hero__media">
            @if ($avatar)
                <img class="nexus-alpha-avatar nexus-alpha-avatar--xl" src="{{ $avatar }}" alt="{{ __('govuk_alpha.members.avatar_alt', ['name' => $displayName]) }}">
            @else
                <span class="nexus-alpha-avatar nexus-alpha-avatar--xl nexus-alpha-avatar--placeholder" aria-hidden="true">{{ mb_strtoupper(mb_substr($displayName, 0, 1)) }}</span>
            @endif
        </div>
        <div class="nexus-alpha-profile-hero__content">
            <span class="govuk-caption-l">
                {{ ($isOwnProfile ?? false) ? __('govuk_alpha.profile.own_caption') : __('govuk_alpha.profile.member_caption') }}
            </span>
            <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">{{ $displayName }}</h1>
            <div class="nexus-alpha-profile-hero__badges">
                @if (!empty($profile['is_verified']))
                    <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.profile.verified') }}</strong>
                @endif
                <strong class="govuk-tag govuk-tag--grey">{{ __('govuk_alpha.profile.profile_type_' . $profileType) }}</strong>
            </div>
            @if (!empty($profile['tagline']))
                <p class="govuk-body-l govuk-!-margin-top-4">{{ $profile['tagline'] }}</p>
            @endif
            @if ($isOwnProfile ?? false)
                <div class="nexus-alpha-actions govuk-!-margin-top-4">
                    <a class="govuk-button" href="{{ route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.actions.edit_profile') }}</a>
                </div>
            @elseif ((int) ($memberId ?? 0) > 0)
                @php $cs = $connectionState ?? 'none'; @endphp
                @if ($cs === 'pending_received')
                    <p class="govuk-body govuk-!-margin-top-4 govuk-!-margin-bottom-2">{{ __('govuk_alpha.profile.connection.request_received', ['name' => $displayName]) }}</p>
                @endif
                <div class="nexus-alpha-actions {{ $cs === 'pending_received' ? '' : 'govuk-!-margin-top-4' }}">
                    @if (($directMessagingEnabled ?? false))
                        <a class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" href="{{ route('govuk-alpha.messages.new', ['tenantSlug' => $tenantSlug, 'userId' => $memberId]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.actions.send_message') }}</a>
                    @endif
                    @if ($cs === 'none')
                        <form method="post" action="{{ route('govuk-alpha.members.connection', ['tenantSlug' => $tenantSlug, 'id' => $memberId]) }}">
                            @csrf
                            <input type="hidden" name="action" value="connect">
                            <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.profile.connection.connect') }}</button>
                        </form>
                    @elseif ($cs === 'pending_sent')
                        <strong class="govuk-tag govuk-tag--yellow">{{ __('govuk_alpha.profile.connection.request_sent') }}</strong>
                        <form method="post" action="{{ route('govuk-alpha.members.connection', ['tenantSlug' => $tenantSlug, 'id' => $memberId]) }}">
                            @csrf
                            <input type="hidden" name="action" value="cancel">
                            <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.profile.connection.cancel_request') }}</button>
                        </form>
                    @elseif ($cs === 'pending_received')
                        <form method="post" action="{{ route('govuk-alpha.members.connection', ['tenantSlug' => $tenantSlug, 'id' => $memberId]) }}">
                            @csrf
                            <input type="hidden" name="action" value="accept">
                            <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.profile.connection.accept') }}</button>
                        </form>
                        <form method="post" action="{{ route('govuk-alpha.members.connection', ['tenantSlug' => $tenantSlug, 'id' => $memberId]) }}">
                            @csrf
                            <input type="hidden" name="action" value="decline">
                            <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.profile.connection.decline') }}</button>
                        </form>
                    @elseif ($cs === 'connected')
                        <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.profile.connection.connected') }}</strong>
                        <form method="post" action="{{ route('govuk-alpha.members.connection', ['tenantSlug' => $tenantSlug, 'id' => $memberId]) }}">
                            @csrf
                            <input type="hidden" name="action" value="remove">
                            <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.profile.connection.remove') }}</button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <h2 class="govuk-heading-l">{{ __('govuk_alpha.profile.activity_title') }}</h2>
    <dl class="nexus-alpha-stat-grid">
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha.profile.hours_given_label') }}</dt>
            <dd>{{ number_format((float) $profileStats['hours_given'], 1) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha.profile.hours_received_label') }}</dt>
            <dd>{{ number_format((float) $profileStats['hours_received'], 1) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha.profile.active_listings_label') }}</dt>
            <dd>{{ (int) $profileStats['listings_count'] }}</dd>
        </div>
        @if ($rating !== null)
            <div class="nexus-alpha-stat">
                <dt>{{ __('govuk_alpha.profile.rating_label') }}</dt>
                <dd>{{ number_format((float) $rating, 1) }}</dd>
            </div>
        @endif
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha.profile.level_label') }}</dt>
            <dd>{{ (int) $profileStats['level'] }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha.profile.xp_label') }}</dt>
            <dd>{{ (int) $profileStats['xp'] }}</dd>
        </div>
    </dl>

    @if (!empty($profileBadges))
        <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.profile.badges_title') }}</h2>
        <ul class="govuk-list nexus-alpha-actions">
            @foreach (array_slice($profileBadges, 0, 12) as $badge)
                <li>
                    @if (!empty($badge['icon']))<span aria-hidden="true">{{ $badge['icon'] }}</span>@endif
                    <strong class="govuk-tag govuk-tag--blue">{{ $badge['name'] ?? '' }}</strong>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="govuk-grid-row govuk-!-margin-top-7">
        <div class="govuk-grid-column-two-thirds">
            <h2 class="govuk-heading-l">{{ __('govuk_alpha.profile.about_title') }}</h2>
            @if (!empty($profile['bio']))
                <div class="govuk-body">{!! nl2br(e((string) $profile['bio'])) !!}</div>
            @else
                <div class="govuk-inset-text">{{ __('govuk_alpha.profile.empty_bio') }}</div>
            @endif

            <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.profile.skills_title') }}</h2>
            @if (empty($profileSkills))
                <div class="govuk-inset-text">{{ __('govuk_alpha.profile.empty_skills') }}</div>
            @else
                <ul class="govuk-list nexus-alpha-skill-list">
                    @foreach ($profileSkills as $skill)
                        @php
                            $skillName = (string) ($skill['skill_name'] ?? '');
                            $endorseCount = (int) (($endorsements['counts'][$skillName] ?? 0));
                            $viewerEndorsed = in_array($skillName, $endorsements['viewerEndorsed'] ?? [], true);
                        @endphp
                        <li>
                            <span class="govuk-!-font-weight-bold">{{ $skillName }}</span>
                            @if (!empty($skill['is_offering']))
                                <strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha.profile.skill_offering') }}</strong>
                            @endif
                            @if (!empty($skill['is_requesting']))
                                <strong class="govuk-tag govuk-tag--purple">{{ __('govuk_alpha.profile.skill_requesting') }}</strong>
                            @endif
                            @if ($endorseCount > 0)
                                <strong class="govuk-tag govuk-tag--green">{{ trans_choice('govuk_alpha.profile.endorsement_count', $endorseCount, ['count' => $endorseCount]) }}</strong>
                            @endif
                            @if (($canEndorse ?? false) && $skillName !== '')
                                <form method="post" action="{{ route('govuk-alpha.members.endorse', ['tenantSlug' => $tenantSlug, 'id' => $memberId]) }}" class="govuk-!-margin-top-1">
                                    @csrf
                                    <input type="hidden" name="skill_name" value="{{ $skillName }}">
                                    <input type="hidden" name="action" value="{{ $viewerEndorsed ? 'remove' : 'endorse' }}">
                                    <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">
                                        {{ $viewerEndorsed ? __('govuk_alpha.profile.remove_endorsement') : __('govuk_alpha.profile.endorse') }}<span class="govuk-visually-hidden"> {{ $skillName }}</span>
                                    </button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.profile.listings_title') }}</h2>
            @if (empty($profileListings))
                <div class="govuk-inset-text">{{ __('govuk_alpha.profile.empty_listings') }}</div>
            @else
                <div class="nexus-alpha-card-list">
                    @foreach ($profileListings as $listing)
                        @php
                            $type = (($listing['type'] ?? 'offer') === 'request') ? 'request' : 'offer';
                            $typeClass = $type === 'request' ? 'govuk-tag--purple' : 'govuk-tag--blue';
                        @endphp
                        <article class="nexus-alpha-card">
                            <h3 class="govuk-heading-m govuk-!-margin-bottom-2">
                                <a class="govuk-link" href="{{ route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $listing['id']]) }}">{{ $listing['title'] }}</a>
                            </h3>
                            <strong class="govuk-tag {{ $typeClass }}">{{ __('govuk_alpha.listings.' . $type) }}</strong>
                            @if (!empty($listing['description']))
                                <p class="govuk-body govuk-!-margin-top-3">{{ \Illuminate\Support\Str::limit(strip_tags((string) $listing['description']), 180) }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif

            <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.profile.reviews_title') }}</h2>
            @if (empty($profileReviews))
                <div class="govuk-inset-text">{{ __('govuk_alpha.profile.empty_reviews') }}</div>
            @else
                <div class="nexus-alpha-card-list">
                    @foreach ($profileReviews as $review)
                        @php
                            $reviewerName = trim((string) ($review['reviewer_name'] ?? '')) ?: __('govuk_alpha.profile.anonymous_review');
                        @endphp
                        <article class="nexus-alpha-card">
                            <h3 class="govuk-heading-m govuk-!-margin-bottom-1">{{ __('govuk_alpha.profile.review_by', ['name' => $reviewerName]) }}</h3>
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">{{ __('govuk_alpha.profile.rating_label') }} {{ (int) $review['rating'] }}</p>
                            @if (!empty($review['comment']))
                                <div class="govuk-body">{!! nl2br(e((string) $review['comment'])) !!}</div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="govuk-grid-column-one-third">
            <h2 class="govuk-heading-l">{{ __('govuk_alpha.profile.summary_title') }}</h2>
            <dl class="govuk-summary-list">
                @if ($joinedAt)
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha.profile.joined_label') }}</dt>
                        <dd class="govuk-summary-list__value">{{ $joinedAt }}</dd>
                    </div>
                @endif
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha.home.summary_community_key') }}</dt>
                    <dd class="govuk-summary-list__value">{{ $communityName }}</dd>
                </div>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha.profile.profile_type_label') }}</dt>
                    <dd class="govuk-summary-list__value">{{ __('govuk_alpha.profile.profile_type_' . $profileType) }}</dd>
                </div>
                @if (!empty($profile['location']))
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha.profile.location_label') }}</dt>
                        <dd class="govuk-summary-list__value">{{ $profile['location'] }}</dd>
                    </div>
                @endif
                @if ($isOwnProfile ?? false)
                    @if (!empty($profile['email']))
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.profile.email_label') }}</dt>
                            <dd class="govuk-summary-list__value">{{ $profile['email'] }}</dd>
                        </div>
                    @endif
                    @if (!empty($profile['phone']))
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.profile.phone_label') }}</dt>
                            <dd class="govuk-summary-list__value">{{ $profile['phone'] }}</dd>
                        </div>
                    @endif
                @endif
            </dl>

            <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.profile.availability_title') }}</h2>
            @if (empty($profileAvailability))
                <div class="govuk-inset-text">{{ __('govuk_alpha.profile.empty_availability') }}</div>
            @else
                <dl class="govuk-summary-list">
                    @foreach ($profileAvailability as $availability)
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">{{ $availability['label'] }}</dt>
                            <dd class="govuk-summary-list__value">
                                {{ $availability['time'] }}
                                @if (!empty($availability['note']))
                                    <span class="govuk-body-s">{{ $availability['note'] }}</span>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </div>
    </div>
@endsection
