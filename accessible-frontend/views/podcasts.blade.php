{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <span class="govuk-caption-xl">{{ __('govuk_alpha.podcasts.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.podcasts.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.podcasts.description') }}</p>

    @if (empty($shows))
        <p class="govuk-inset-text">{{ __('govuk_alpha.podcasts.empty') }}</p>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($shows as $s)
                @php
                    $sTitle = trim((string) ($s['title'] ?? '')) ?: __('govuk_alpha.podcasts.title');
                    $owner = trim((string) ($s['owner']['name'] ?? ''));
                    $count = (int) ($s['approved_episode_count'] ?? ($s['episodes_count'] ?? 0));
                @endphp
                <article class="nexus-alpha-card">
                    <h2 class="govuk-heading-s govuk-!-margin-bottom-1"><a class="govuk-link" href="{{ route('govuk-alpha.podcasts.show', ['tenantSlug' => $tenantSlug, 'id' => $s['id']]) }}">{{ $sTitle }}</a></h2>
                    @if (trim((string) ($s['description'] ?? '')) !== '')
                        <p class="govuk-body govuk-!-margin-bottom-1">{{ \Illuminate\Support\Str::limit(strip_tags((string) $s['description']), 160) }}</p>
                    @endif
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">
                        @if ($owner !== ''){{ __('govuk_alpha.podcasts.by_label', ['name' => $owner]) }} · @endif{{ __('govuk_alpha.podcasts.episodes_count', ['count' => $count]) }}
                    </p>
                </article>
            @endforeach
        </div>
    @endif
@endsection
