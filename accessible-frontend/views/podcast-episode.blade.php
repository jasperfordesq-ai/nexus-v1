{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $epTitle  = trim((string) ($episode['title'] ?? '')) ?: __('govuk_alpha.podcasts.episodes_title');
        $showTitle = trim((string) ($show['title'] ?? '')) ?: __('govuk_alpha.podcasts.title');
        $audioUrl  = trim((string) ($episode['audio_url'] ?? ''));
        $transcript = trim((string) ($episode['transcript'] ?? ''));
    @endphp

    <a href="{{ route('govuk-alpha.podcasts.show', ['tenantSlug' => $tenantSlug, 'id' => $show['id']]) }}" class="govuk-back-link">{{ __('govuk_alpha.polish_commerce.podcast_episode_back') }}</a>

    <span class="govuk-caption-xl">{{ $showTitle }}</span>
    <h1 class="govuk-heading-xl">{{ $epTitle }}</h1>

    @if (trim((string) ($episode['description'] ?? '')) !== '')
        <div class="govuk-body-l">{!! nl2br(e(strip_tags((string) $episode['description']))) !!}</div>
    @endif

    @if ($audioUrl !== '')
        <div class="govuk-!-margin-bottom-6">
            <audio controls preload="metadata" class="nexus-alpha-audio" src="{{ $audioUrl }}" aria-label="{{ __('govuk_alpha.polish_commerce.podcast_episode_audio_label', ['title' => $epTitle]) }}">
                <a class="govuk-link" href="{{ $audioUrl }}">{{ __('govuk_alpha.podcasts.download_episode', ['title' => $epTitle]) }}</a>
            </audio>
        </div>
    @else
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.polish_commerce.podcast_episode_no_audio') }}</p></div>
    @endif

    @if ($transcript !== '')
        <h2 class="govuk-heading-m">{{ __('govuk_alpha.polish_commerce.podcast_episode_transcript') }}</h2>
        <div class="govuk-body">{!! nl2br(e($transcript)) !!}</div>
    @endif
@endsection
