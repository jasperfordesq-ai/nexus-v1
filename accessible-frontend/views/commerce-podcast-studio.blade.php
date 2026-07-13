{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $shows = $shows ?? [];
        $statusLabels = [
            'published' => __('govuk_alpha_commerce.podcast_studio.status_published'),
            'draft' => __('govuk_alpha_commerce.podcast_studio.status_draft'),
            'archived' => __('govuk_alpha_commerce.podcast_studio.status_archived'),
        ];
        $statusTags = [
            'published' => 'govuk-tag--green',
            'draft' => 'govuk-tag--grey',
            'archived' => 'govuk-tag--red',
        ];
        $statusMessages = [
            'show-deleted' => ['msg' => __('govuk_alpha_commerce.podcast_studio.status_show_deleted'), 'error' => false],
            'show-delete-failed' => ['msg' => __('govuk_alpha_commerce.podcast_studio.status_show_delete_failed'), 'error' => true],
        ];
        $statusEntry = $status !== null && isset($statusMessages[$status]) ? $statusMessages[$status] : null;
    @endphp

    @include('accessible-frontend::partials.commerce-courses-nav', ['coursesActiveTab' => 'browse'])

    @if ($statusEntry !== null)
        @if ($statusEntry['error'])
            <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                <div role="alert">
                    <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_commerce.common.error_title') }}</h2>
                    <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li>{{ $statusEntry['msg'] }}</li></ul></div>
                </div>
            </div>
        @else
            <div class="govuk-notification-banner govuk-notification-banner--success" role="alert" aria-labelledby="govuk-notification-banner-title" data-module="govuk-notification-banner">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="govuk-notification-banner-title">{{ __('govuk_alpha.states.success_title') }}</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading">{{ $statusEntry['msg'] }}</p>
                </div>
            </div>
        @endif
    @endif

    <span class="govuk-caption-l">{{ __('govuk_alpha_commerce.podcast_studio.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_commerce.podcast_studio.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_commerce.podcast_studio.description') }}</p>

    @if (!empty($canCreateShow))
        <a class="govuk-button" href="{{ route('govuk-alpha.podcasts.studio.create', ['tenantSlug' => $tenantSlug]) }}" data-module="govuk-button">{{ __('govuk_alpha_commerce.podcast_studio.create_button') }}</a>
    @endif

    @if (empty($shows))
        <p class="govuk-inset-text">{{ __('govuk_alpha_commerce.podcast_studio.empty') }}</p>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($shows as $show)
                @php
                    $sStatus = (string) ($show['status'] ?? 'draft');
                    $episodeCount = (int) ($show['episode_count'] ?? 0);
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $show['title'] ?? '' }}</h2>
                        <strong class="govuk-tag {{ $statusTags[$sStatus] ?? 'govuk-tag--grey' }}">{{ $statusLabels[$sStatus] ?? $sStatus }}</strong>
                    </div>
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">{{ trans_choice('govuk_alpha_commerce.podcast_studio.episode_count', $episodeCount, ['count' => $episodeCount]) }}</p>
                    <a class="govuk-link" href="{{ route('govuk-alpha.podcasts.studio.manage', ['tenantSlug' => $tenantSlug, 'id' => (int) ($show['id'] ?? 0)]) }}">{{ __('govuk_alpha_commerce.podcast_studio.manage_button') }}</a>
                </article>
            @endforeach
        </div>
    @endif
@endsection
