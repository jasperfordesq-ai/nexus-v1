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
        $gallery = is_array($listing['images'] ?? null) ? $listing['images'] : [];
        $skillTags = collect($listing['skill_tags'] ?? [])
            ->map(fn ($tag) => is_array($tag) ? ($tag['name'] ?? $tag['tag'] ?? null) : (is_string($tag) ? $tag : (is_object($tag) ? ($tag->name ?? null) : null)))
            ->filter(fn ($tag) => is_string($tag) && trim($tag) !== '')
            ->map(fn ($tag) => trim($tag))
            ->unique()
            ->values();
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.listings.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_listings') }}</a>

    @if ($status === 'listing-created')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-labelledby="listing-created-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="listing-created-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.listings.create.created') }}</p>
            </div>
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
                            <a href="{{ $image['url'] }}" target="_blank" rel="noopener noreferrer">
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
            @php($expiresAt = \Illuminate\Support\Carbon::parse($listing['expires_at'])->translatedFormat('j F Y'))
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
        <h2 class="govuk-heading-l">{{ __('govuk_alpha.listings.author_title') }}</h2>
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

    <section class="govuk-!-margin-top-7 govuk-!-margin-bottom-8" aria-labelledby="listing-exchange-title">
        <h2 class="govuk-heading-l" id="listing-exchange-title">{{ __('govuk_alpha.listings.exchange_title') }}</h2>

        @if ($requiresAuth)
            <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="listing-auth-required-title">
                <div class="govuk-notification-banner__header">
                    <h3 class="govuk-notification-banner__title" id="listing-auth-required-title">{{ __('govuk_alpha.states.auth_required') }}</h3>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-body">{{ __('govuk_alpha.listings.auth_required_detail') }}</p>
                    <div class="nexus-alpha-actions">
                        <a class="govuk-button" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.nav.login') }}</a>
                        <a class="govuk-button govuk-button--secondary" href="{{ route('govuk-alpha.register', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.nav.register') }}</a>
                    </div>
                </div>
            </div>
        @elseif ($isOwner)
            <div class="govuk-inset-text">{{ __('govuk_alpha.listings.own_listing_detail') }}</div>
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
