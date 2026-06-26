{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        use Illuminate\Support\Facades\Lang;
        $type = (($listing['type'] ?? 'offer') === 'request') ? 'request' : 'offer';
        $typeClass = ($type === 'request') ? 'govuk-tag--purple' : 'govuk-tag--blue';
        $authorName = $listing['author_name'] ?? $listing['user']['name'] ?? null;
        $authorId = (int) ($listing['user_id'] ?? $listing['author_id'] ?? $listing['user']['id'] ?? 0);
        $authorAvatar = $listing['author_avatar'] ?? null;
        $authorTagline = $listing['user']['tagline'] ?? $listing['author_tagline'] ?? null;
        $authorReviews = (int) ($listing['author_reviews_count'] ?? 0);
        $authorExchanges = (int) ($listing['author_exchanges_count'] ?? 0);
        $createdAt = !empty($listing['created_at']) ? \Illuminate\Support\Carbon::parse($listing['created_at'])->translatedFormat('j F Y') : null;
        $serviceType = $listing['service_type'] ?? null;
        $showServiceBadge = is_string($serviceType) && $serviceType !== '' && $serviceType !== 'physical_only'
            && Lang::has('govuk_alpha.listings.service_types.' . $serviceType);
        $statusValue = $listing['status'] ?? null;
        // Authenticated viewer flag for the comments/report/delete controls below
        // ($requiresAuth is set by the controller; true when the viewer is a guest).
        $isAuthenticated = !($requiresAuth ?? true);
        $gallery = is_array($listing['images'] ?? null) ? $listing['images'] : [];
        $skillTags = collect($listing['skill_tags'] ?? [])
            ->map(fn ($tag) => is_array($tag) ? ($tag['name'] ?? $tag['tag'] ?? null) : (is_string($tag) ? $tag : (is_object($tag) ? ($tag->name ?? null) : null)))
            ->filter(fn ($tag) => is_string($tag) && trim($tag) !== '')
            ->map(fn ($tag) => trim($tag))
            ->unique()
            ->values();
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.listings.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_listings') }}</a>

    @if ($status === 'listing-created' || $status === 'listing-updated')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="listing-created-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="listing-created-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ $status === 'listing-updated' ? __('govuk_alpha.listings.edit.updated') : __('govuk_alpha.listings.create.created') }}</p>
            </div>
        </div>
    @elseif ($status === 'listing-delete-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert"><h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li>{{ __('govuk_alpha.listings.edit.delete_failed') }}</li></ul></div></div>
        </div>
    @endif

    @php
        $actionSuccessStates = [
            'listing-saved' => 'govuk_alpha.polish_listings.status_listing_saved',
            'listing-unsaved' => 'govuk_alpha.polish_listings.status_listing_unsaved',
            'listing-renewed' => 'govuk_alpha.polish_listings.status_listing_renewed',
        ];
        $actionErrorStates = [
            'save-failed' => 'govuk_alpha.polish_listings.status_listing_save_failed',
            'unsave-failed' => 'govuk_alpha.polish_listings.status_listing_save_failed',
            'renew-failed' => 'govuk_alpha.polish_listings.status_listing_renew_failed',
        ];
    @endphp
    @if (isset($actionSuccessStates[$status]))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="listing-action-status-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="listing-action-status-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __($actionSuccessStates[$status]) }}</p>
            </div>
        </div>
    @elseif (isset($actionErrorStates[$status]))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert"><h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li>{{ __($actionErrorStates[$status]) }}</li></ul></div></div>
        </div>
    @endif

    @if ($status === 'exchange-disabled' || $status === 'own-listing')
        <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="listing-status-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="listing-status-title">{{ __('govuk_alpha.states.not_available') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-body">{{ $status === 'own-listing' ? __('govuk_alpha.listings.own_listing') : __('govuk_alpha.listings.exchange_disabled') }}</p>
            </div>
        </div>
    @endif

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-l">{{ __('govuk_alpha.listings.detail_title') }}</span>
            <h1 class="govuk-heading-xl">{{ $listing['title'] }}</h1>
            <p class="nexus-alpha-actions govuk-!-margin-bottom-4">
                <strong class="govuk-tag {{ $typeClass }}">{{ __('govuk_alpha.listings.' . $type) }}</strong>
                @if (!empty($listing['is_featured']))
                    <strong class="govuk-tag govuk-tag--yellow">{{ __('govuk_alpha.listings.featured') }}</strong>
                @endif
                @if ($showServiceBadge)
                    <strong class="govuk-tag govuk-tag--turquoise">{{ __('govuk_alpha.listings.service_types.' . $serviceType) }}</strong>
                @endif
            </p>

            @if (!empty($listing['image_url']))
                <figure class="nexus-alpha-detail-hero">
                    <img src="{{ $listing['image_url'] }}" alt="{{ __('govuk_alpha.listings.image_alt', ['title' => $listing['title']]) }}" width="640" height="360" decoding="async">
                </figure>
            @endif

            <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.listings.description_title') }}</h2>
            <div class="govuk-body">{!! nl2br(e((string) ($listing['description'] ?? ''))) !!}</div>

            @if (!empty($gallery))
                <h2 class="govuk-heading-m">{{ __('govuk_alpha.listings.gallery_title') }}</h2>
                <ul class="nexus-alpha-thumb-list">
                    @foreach ($gallery as $galleryIndex => $image)
                        <li>
                            <a href="{{ $image['url'] }}" target="_blank" rel="noopener noreferrer"><span class="govuk-visually-hidden"> {{ __('govuk_alpha.opens_new_tab') }}</span>
                                <img src="{{ $image['url'] }}" alt="{{ !empty($image['alt_text']) ? $image['alt_text'] : __('govuk_alpha.listings.gallery_image_alt', ['number' => $galleryIndex + 1, 'title' => $listing['title']]) }}" loading="lazy" decoding="async">
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($skillTags->isNotEmpty())
                <h2 class="govuk-heading-m">{{ __('govuk_alpha.listings.skills_title') }}</h2>
                <ul class="govuk-list nexus-alpha-actions govuk-!-margin-bottom-6">
                    @foreach ($skillTags as $skill)
                        <li><strong class="govuk-tag govuk-tag--grey">{{ $skill }}</strong></li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="govuk-grid-column-one-third">
            <div class="govuk-inset-text">
                {{ __('govuk_alpha.listings.detail_inset', ['type' => __('govuk_alpha.listings.' . $type)]) }}
            </div>
        </div>
    </div>

    <div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">
    <h2 class="govuk-heading-l">{{ __('govuk_alpha.listings.summary_title') }}</h2>
    <dl class="govuk-summary-list">
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.listings.type') }}</dt>
            <dd class="govuk-summary-list__value">
                <strong class="govuk-tag {{ $typeClass }}">{{ __('govuk_alpha.listings.' . $type) }}</strong>
            </dd>
        </div>
        @if (is_string($serviceType) && $serviceType !== '' && Lang::has('govuk_alpha.listings.service_types.' . $serviceType))
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.listings.service_type_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ __('govuk_alpha.listings.service_types.' . $serviceType) }}</dd>
            </div>
        @endif
        @if (is_string($statusValue) && $statusValue !== '' && Lang::has('govuk_alpha.listings.status_values.' . $statusValue))
            @php
                $statusTagClass = match ($statusValue) {
                    'active' => 'govuk-tag--green',
                    'pending', 'pending_review' => 'govuk-tag--blue',
                    'paused' => 'govuk-tag--yellow',
                    'suspended', 'deleted' => 'govuk-tag--red',
                    default => 'govuk-tag--grey',
                };
            @endphp
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.listings.status_label') }}</dt>
                <dd class="govuk-summary-list__value">
                    <strong class="govuk-tag {{ $statusTagClass }}">{{ __('govuk_alpha.listings.status_values.' . $statusValue) }}</strong>
                </dd>
            </div>
        @endif
        @if (!empty($listing['category_name']))
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.listings.category') }}</dt>
                <dd class="govuk-summary-list__value">{{ $listing['category_name'] }}</dd>
            </div>
        @endif
        @if (!empty($listing['location']))
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.listings.location') }}</dt>
                <dd class="govuk-summary-list__value">{{ $listing['location'] }}</dd>
            </div>
        @endif
        @if (!empty($listing['hours_estimate']))
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.listings.hours_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ __('govuk_alpha.listings.hours', ['count' => $listing['hours_estimate']]) }}</dd>
            </div>
        @endif
        @if ($createdAt)
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.listings.created') }}</dt>
                <dd class="govuk-summary-list__value">{{ $createdAt }}</dd>
            </div>
        @endif
        @if (!empty($listing['expires_at']))
            @php
                $expiresAt = \Illuminate\Support\Carbon::parse($listing['expires_at'])->translatedFormat('j F Y');
            @endphp
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.listings.expires_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $statusValue === 'expired' ? __('govuk_alpha.listings.expired_on', ['date' => $expiresAt]) : __('govuk_alpha.listings.expires_on', ['date' => $expiresAt]) }}</dd>
            </div>
        @endif
        @if ((int) ($listing['renewal_count'] ?? 0) > 0)
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.listings.renewals_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ trans_choice('govuk_alpha.listings.renewed_count', (int) $listing['renewal_count'], ['count' => (int) $listing['renewal_count']]) }}</dd>
            </div>
        @endif
    </dl>

    @if ($authorName)
        <h2 class="govuk-heading-l govuk-!-margin-top-6">{{ __('govuk_alpha.listings.author_title') }}</h2>
        <div class="nexus-alpha-summary govuk-!-margin-bottom-7">
            <div>
                @if (!empty($authorAvatar))
                    <img class="nexus-alpha-avatar nexus-alpha-avatar--large" src="{{ $authorAvatar }}" alt="" width="64" height="64" loading="lazy" decoding="async">
                @else
                    <span class="nexus-alpha-avatar nexus-alpha-avatar--large nexus-alpha-avatar--placeholder" aria-hidden="true">{{ mb_strtoupper(mb_substr($authorName, 0, 1)) }}</span>
                @endif
            </div>
            <div>
                <p class="govuk-body govuk-!-margin-bottom-1">
                    <strong>{{ $authorName }}</strong>
                    @if (!empty($listing['author_verified']))
                        <strong class="govuk-tag govuk-tag--green govuk-!-margin-left-1">{{ __('govuk_alpha.members.verified') }}</strong>
                    @endif
                </p>
                @if (!empty($authorTagline))
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">{{ $authorTagline }}</p>
                @endif
                @php
                    // Author verification badges — mirrors the React <VerificationBadgeRow>,
                    // which self-fetches /v2/users/{id}/verification-badges. The accessible
                    // frontend has no client fetch, so we read the same service directly.
                    $authorBadges = [];
                    if ($authorId > 0) {
                        try {
                            $authorBadges = app(\App\Services\MemberVerificationBadgeService::class)->getUserBadges($authorId);
                        } catch (\Throwable $e) {
                            report($e);
                            $authorBadges = [];
                        }
                    }
                @endphp
                @if (!empty($authorBadges))
                    <ul class="govuk-list nexus-alpha-actions govuk-!-margin-bottom-2" aria-label="{{ __('govuk_alpha_listings.detail.author_badges_heading') }}">
                        @foreach ($authorBadges as $badge)
                            @php
                                $badgeType = (string) ($badge['badge_type'] ?? '');
                                $badgeKey = 'govuk_alpha_listings.badges.' . $badgeType;
                                $badgeLabel = \Illuminate\Support\Facades\Lang::has($badgeKey) ? __($badgeKey) : (string) ($badge['label'] ?? $badgeType);
                            @endphp
                            @if ($badgeLabel !== '')
                                <li><strong class="govuk-tag govuk-tag--green">{{ $badgeLabel }}</strong></li>
                            @endif
                        @endforeach
                    </ul>
                @endif
                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">
                    @if (!empty($listing['author_rating']))
                        <span>{{ __('govuk_alpha.members.rating', ['rating' => $listing['author_rating']]) }}</span>
                        <span aria-hidden="true"> · </span>
                    @endif
                    <span>{{ trans_choice('govuk_alpha.listings.author_reviews', $authorReviews, ['count' => $authorReviews]) }}</span>
                    <span aria-hidden="true"> · </span>
                    <span>{{ trans_choice('govuk_alpha.listings.author_exchanges', $authorExchanges, ['count' => $authorExchanges]) }}</span>
                </p>
                @if ($authorId > 0 && !$requiresAuth && \App\Core\TenantContext::hasFeature('connections'))
                    <p class="govuk-body-s govuk-!-margin-bottom-0">
                        <a class="govuk-link" href="{{ route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $authorId]) }}">{{ __('govuk_alpha.listings.view_profile') }}<span class="govuk-visually-hidden">{{ __('govuk_alpha.listings.view_profile_for', ['name' => $authorName]) }}</span></a>
                    </p>
                @endif
            </div>
        </div>
    @endif

    {{-- Like / unlike action + count --}}
    <div id="like" class="govuk-!-margin-top-4 govuk-!-margin-bottom-2">
        @if ($isAuthenticated && !$isOwner)
            <form method="post" action="{{ route('govuk-alpha.listings.like', ['tenantSlug' => $tenantSlug, 'id' => $listing['id']]) }}" class="govuk-!-display-inline-block">
                @csrf
                <button class="govuk-button {{ ($hasLiked ?? false) ? '' : 'govuk-button--secondary' }} govuk-!-margin-bottom-0" data-module="govuk-button">
                    {{ ($hasLiked ?? false) ? __('govuk_alpha_listings.detail.unlike') : __('govuk_alpha_listings.detail.like') }}
                </button>
            </form>
        @endif
        <span class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha_listings.detail.likes_count', ['count' => (int) ($likeCount ?? 0)]) }}</span>
    </div>

    {{-- Save / unsave action + share URL + report link --}}
    <div class="govuk-button-group govuk-!-margin-top-4 govuk-!-margin-bottom-2">
        @if ($isAuthenticated && !$isOwner)
            @if ($isSaved ?? false)
                <form method="post" action="{{ route('govuk-alpha.listings.unsave', ['tenantSlug' => $tenantSlug, 'id' => $listing['id']]) }}">
                    @csrf
                    <button class="govuk-button govuk-button--secondary" data-module="govuk-button">
                        {{ __('govuk_alpha.polish_listings.unsave_listing') }}
                        <span class="govuk-visually-hidden">{{ __('govuk_alpha.polish_listings.unsave_listing_for', ['title' => $listing['title']]) }}</span>
                    </button>
                </form>
            @else
                <form method="post" action="{{ route('govuk-alpha.listings.save', ['tenantSlug' => $tenantSlug, 'id' => $listing['id']]) }}">
                    @csrf
                    <button class="govuk-button govuk-button--secondary" data-module="govuk-button">
                        {{ __('govuk_alpha.polish_listings.save_listing') }}
                        <span class="govuk-visually-hidden">{{ __('govuk_alpha.polish_listings.save_listing_for', ['title' => $listing['title']]) }}</span>
                    </button>
                </form>
            @endif
        @endif
        @if ($isAuthenticated)
            <a class="govuk-link" href="{{ route('govuk-alpha.listings.comments', ['tenantSlug' => $tenantSlug, 'id' => $listing['id']]) }}">
                @if ((int) ($listing['comments_count'] ?? 0) > 0)
                    {{ __('govuk_alpha_listings.detail.comments_link_count', ['count' => (int) $listing['comments_count']]) }}
                @else
                    {{ __('govuk_alpha_listings.detail.comments_link') }}
                @endif
            </a>
        @endif
        @if ($isAuthenticated && !$isOwner)
            <a class="govuk-link" href="{{ route('govuk-alpha.listings.report', ['tenantSlug' => $tenantSlug, 'id' => $listing['id']]) }}">{{ __('govuk_alpha.polish_listings.report_listing_title') }}</a>
        @endif
    </div>

    <div class="govuk-inset-text govuk-!-margin-bottom-7">
        <p class="govuk-body govuk-!-margin-bottom-1"><strong>{{ __('govuk_alpha.listings.share_link_label') }}</strong></p>
        <p class="govuk-body nexus-alpha-share-url govuk-!-margin-bottom-0">{{ url()->current() }}</p>
    </div>

    </div>{{-- /govuk-grid-column-two-thirds (summary + author) --}}
    </div>{{-- /govuk-grid-row --}}

    <section class="govuk-!-margin-top-7 govuk-!-margin-bottom-8" aria-labelledby="listing-exchange-title">
        <h2 class="govuk-heading-l" id="listing-exchange-title">{{ __('govuk_alpha.listings.exchange_title') }}</h2>

        @if ($requiresAuth)
            <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="listing-auth-required-title">
                <div class="govuk-notification-banner__header">
                    <h3 class="govuk-notification-banner__title" id="listing-auth-required-title">{{ __('govuk_alpha.states.auth_required') }}</h3>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-body">{{ __('govuk_alpha.listings.auth_required_detail') }}</p>
                    <div class="govuk-button-group">
                        <a class="govuk-button" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.nav.login') }}</a>
                        <a class="govuk-button govuk-button--secondary" href="{{ route('govuk-alpha.register', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.nav.register') }}</a>
                    </div>
                </div>
            </div>
        @elseif ($isOwner)
            <div class="govuk-inset-text">{{ __('govuk_alpha.listings.own_listing_detail') }}</div>
            <div class="govuk-button-group">
                <a class="govuk-button govuk-button--secondary" href="{{ route('govuk-alpha.listings.edit', ['tenantSlug' => $tenantSlug, 'id' => $listing['id']]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.listings.edit.edit_listing') }}</a>
                <a class="govuk-button govuk-button--secondary" href="{{ route('govuk-alpha.listings.analytics', ['tenantSlug' => $tenantSlug, 'id' => $listing['id']]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha_listings.nav.view_analytics') }}</a>
            </div>
            @if (($statusValue ?? '') === 'expired' || !empty($listing['expires_at']))
                @if (($statusValue ?? '') === 'expired')
                    <p class="govuk-hint">{{ __('govuk_alpha.polish_listings.renew_listing_hint') }}</p>
                    <form method="post" action="{{ route('govuk-alpha.listings.renew', ['tenantSlug' => $tenantSlug, 'id' => $listing['id']]) }}">
                        @csrf
                        <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.polish_listings.renew_listing') }}</button>
                    </form>
                @endif
            @endif

            {{-- Delete is a clearly-warned, CSRF-protected action (route is owner-gated). --}}
            <hr class="govuk-section-break govuk-section-break--visible govuk-section-break--m govuk-!-margin-top-4">
            <h3 class="govuk-heading-s">{{ __('govuk_alpha_listings.detail.delete_heading') }}</h3>
            <div class="govuk-warning-text">
                <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                <strong class="govuk-warning-text__text">
                    <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.warning') }}</span>
                    {{ __('govuk_alpha_listings.detail.delete_warning') }}
                </strong>
            </div>
            <form method="post" action="{{ route('govuk-alpha.listings.delete', ['tenantSlug' => $tenantSlug, 'id' => $listing['id']]) }}">
                @csrf
                <button class="govuk-button govuk-button--warning" data-module="govuk-button">{{ __('govuk_alpha_listings.detail.delete_button') }}</button>
            </form>
        @elseif ($exchangeWorkflowEnabled)
            @if ($activeExchange)
                <div class="govuk-inset-text">
                    <p class="govuk-body">{{ __('govuk_alpha.listings.active_exchange_detail') }}</p>
                    <a class="govuk-button" href="{{ route('govuk-alpha.exchanges.show', ['tenantSlug' => $tenantSlug, 'id' => $activeExchange['id']]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.actions.view_exchange') }}</a>
                </div>
            @else
                <p class="govuk-body">{{ __('govuk_alpha.listings.exchange_detail') }}</p>
                <a class="govuk-button" href="{{ route('govuk-alpha.exchanges.request', ['tenantSlug' => $tenantSlug, 'listingId' => $listing['id']]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.actions.request_exchange') }}</a>
            @endif
        @elseif ($directMessagingEnabled && $authorId > 0)
            <p class="govuk-body">{{ __('govuk_alpha.listings.direct_message_detail') }}</p>
            <a class="govuk-button" href="{{ route('govuk-alpha.messages.new', ['tenantSlug' => $tenantSlug, 'userId' => $authorId, 'listing' => $listing['id']]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.actions.send_message') }}</a>
        @else
            <div class="govuk-inset-text">{{ __('govuk_alpha.listings.messaging_unavailable') }}</div>
        @endif
    </section>

    @foreach (['member_offers', 'member_requests'] as $section)
        @if (!empty($listing[$section]))
            <h2 class="govuk-heading-m">{{ __('govuk_alpha.listings.' . $section) }}</h2>
            <ul class="govuk-list govuk-list--spaced">
                @foreach ($listing[$section] as $related)
                    <li>
                        <a class="govuk-link" href="{{ route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $related['id']]) }}">{{ $related['title'] }}</a>
                    </li>
                @endforeach
            </ul>
        @endif
    @endforeach
@endsection
