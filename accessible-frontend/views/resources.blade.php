{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <span class="govuk-caption-xl">{{ __('govuk_alpha.resources.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.resources.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.resources.description') }}</p>

    <form method="get" action="{{ route('govuk-alpha.resources.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
        <div class="govuk-form-group">
            <label class="govuk-label" for="q">{{ __('govuk_alpha.resources.search_label') }}</label>
            <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha.resources.search_hint') }}</div>
            <input class="govuk-input govuk-!-width-two-thirds" id="q" name="q" type="search" value="{{ $resourcesQuery ?? '' }}" aria-describedby="q-hint">
        </div>
        <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.actions.search') }}</button>
    </form>

    @if (empty($resources))
        <p class="govuk-inset-text">{{ __('govuk_alpha.resources.empty') }}</p>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($resources as $r)
                @php
                    $rTitle = trim((string) ($r['title'] ?? '')) ?: __('govuk_alpha.resources.title');
                    $rType = strtoupper(trim((string) ($r['file_type'] ?? '')));
                    $rPath = trim((string) ($r['file_path'] ?? ($r['url'] ?? '')));
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $rTitle }}</h2>
                        @if ($rType !== '')<strong class="govuk-tag govuk-tag--grey">{{ $rType }}</strong>@endif
                    </div>
                    @if (trim((string) ($r['description'] ?? '')) !== '')
                        <p class="govuk-body govuk-!-margin-bottom-2">{{ \Illuminate\Support\Str::limit($r['description'], 200) }}</p>
                    @endif
                    @if ($rPath !== '')
                        <a class="govuk-link" href="{{ \Illuminate\Support\Str::startsWith($rPath, ['http://', 'https://', '/']) ? $rPath : '/' . ltrim($rPath, '/') }}" rel="noopener">{{ __('govuk_alpha.resources.download') }}</a>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
