{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $sTitle = trim((string) ($show['title'] ?? '')) ?: __('govuk_alpha.podcasts.title');
        $owner = trim((string) ($show['owner']['name'] ?? ''));
    @endphp

    <a href="{{ route('govuk-alpha.podcasts.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.podcasts.back') }}</a>

    <span class="govuk-caption-xl">{{ $owner !== '' ? __('govuk_alpha.podcasts.by_label', ['name' => $owner]) : ($tenant['name'] ?? $tenantSlug) }}</span>
    <h1 class="govuk-heading-xl">{{ $sTitle }}</h1>
    @if (trim((string) ($show['description'] ?? '')) !== '')
        <div class="govuk-body-l">{!! nl2br(e(strip_tags((string) $show['description']))) !!}</div>
    @endif

    <h2 class="govuk-heading-l govuk-!-margin-top-6">{{ __('govuk_alpha.podcasts.episodes_title') }}</h2>
    @if (empty($episodes))
        <p class="govuk-inset-text">{{ __('govuk_alpha.podcasts.episodes_empty') }}</p>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($episodes as $ep)
                @php $epTitle = trim((string) ($ep['title'] ?? '')) ?: __('govuk_alpha.podcasts.episodes_title'); @endphp
                <article class="nexus-alpha-card">
                    <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $epTitle }}</h3>
                    @if (trim((string) ($ep['description'] ?? '')) !== '')
                        <p class="govuk-body govuk-!-margin-bottom-2">{{ \Illuminate\Support\Str::limit(strip_tags((string) $ep['description']), 240) }}</p>
                    @endif
                    @if (trim((string) ($ep['audio_url'] ?? '')) !== '')
                        <audio controls preload="none" class="nexus-alpha-audio" src="{{ $ep['audio_url'] }}">
                            <a class="govuk-link" href="{{ $ep['audio_url'] }}">{{ $epTitle }}</a>
                        </audio>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
