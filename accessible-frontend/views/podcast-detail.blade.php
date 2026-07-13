{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $sTitle   = trim((string) ($show['title'] ?? '')) ?: __('govuk_alpha.podcasts.title');
        $owner    = trim((string) ($show['owner']['name'] ?? ''));
        $artwork  = '';
        if (!empty($show['artwork_url'])) {
            $raw = trim((string) $show['artwork_url']);
            if ($raw !== '' && \Illuminate\Support\Str::startsWith($raw, ['http://', 'https://', '/'])) {
                $artwork = $raw;
            }
        }
        $rssUrl    = trim((string) ($show['rss_url'] ?? ''));
        $rssEnabled = !empty($show['rss_enabled']) && $rssUrl !== '';
    @endphp

    <a href="{{ route('govuk-alpha.podcasts.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.podcasts.back') }}</a>

    @if (session('podcast_status') === 'subscribed')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="podcast-status-heading">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="podcast-status-heading">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.polish_commerce.podcast_subscribe_success') }}</p></div>
        </div>
    @elseif (session('podcast_status') === 'unsubscribed')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="podcast-status-heading">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="podcast-status-heading">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.polish_commerce.podcast_unsubscribe_success') }}</p></div>
        </div>
    @elseif (session('podcast_status') === 'subscribe-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li>{{ __('govuk_alpha.polish_commerce.podcast_subscribe_failed') }}</li></ul></div>
            </div>
        </div>
    @endif

    @if ($artwork !== '')
        <img class="nexus-alpha-avatar nexus-alpha-avatar--xl govuk-!-margin-bottom-4" src="{{ $artwork }}" alt="" aria-hidden="true" loading="lazy" decoding="async" referrerpolicy="no-referrer">
    @endif

    <span class="govuk-caption-xl">{{ $owner !== '' ? __('govuk_alpha.podcasts.by_label', ['name' => $owner]) : ($tenant['name'] ?? $tenantSlug) }}</span>
    <h1 class="govuk-heading-xl">{{ $sTitle }}</h1>

    @if (trim((string) ($show['description'] ?? '')) !== '')
        <div class="govuk-body-l">{!! nl2br(e(strip_tags((string) $show['description']))) !!}</div>
    @endif

    @if ($rssEnabled)
        <p class="govuk-body-s">
            <a class="govuk-link" href="{{ $rssUrl }}" target="_blank" rel="noopener noreferrer">{{ __('govuk_alpha.polish_commerce.podcast_rss_link') }}<span class="govuk-visually-hidden"> {{ __('govuk_alpha.opens_new_tab') }}</span></a>
        </p>
    @endif

    @if ($currentUserId)
        <form method="post" action="{{ route('govuk-alpha.podcasts.subscribe', ['tenantSlug' => $tenantSlug, 'id' => $show['id']]) }}" class="govuk-!-margin-bottom-6">
            @csrf
            @if ($isSubscribed)
                <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.polish_commerce.podcast_unsubscribe') }}</button>
            @else
                <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.polish_commerce.podcast_subscribe') }}</button>
            @endif
        </form>
    @endif

    <h2 class="govuk-heading-l govuk-!-margin-top-6">{{ __('govuk_alpha.podcasts.episodes_title') }}</h2>
    @if (empty($episodes))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.podcasts.episodes_empty') }}</p></div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($episodes as $ep)
                @php $epTitle = trim((string) ($ep['title'] ?? '')) ?: __('govuk_alpha.podcasts.episodes_title'); @endphp
                <article class="nexus-alpha-card">
                    <h3 class="govuk-heading-s govuk-!-margin-bottom-1">
                        <a class="govuk-link" href="{{ route('govuk-alpha.podcasts.episode', ['tenantSlug' => $tenantSlug, 'showId' => $show['id'], 'id' => $ep['id']]) }}">{{ $epTitle }}</a>
                    </h3>
                    @if (trim((string) ($ep['description'] ?? '')) !== '')
                        <p class="govuk-body govuk-!-margin-bottom-2">{{ \Illuminate\Support\Str::limit(strip_tags((string) $ep['description']), 240) }}</p>
                    @endif
                    @if (trim((string) ($ep['audio_url'] ?? '')) !== '')
                        <audio controls preload="none" class="nexus-alpha-audio" src="{{ $ep['audio_url'] }}" aria-label="{{ $epTitle }}">
                            <a class="govuk-link" href="{{ $ep['audio_url'] }}">{{ __('govuk_alpha.podcasts.download_episode', ['title' => $epTitle]) }}</a>
                        </audio>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
