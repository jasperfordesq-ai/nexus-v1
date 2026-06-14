{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <span class="govuk-caption-xl">{{ __('govuk_alpha.groups.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.groups.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.groups.description') }}</p>

    <form method="get" action="{{ route('govuk-alpha.groups.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
        <div class="govuk-form-group">
            <label class="govuk-label" for="q">{{ __('govuk_alpha.groups.search_label') }}</label>
            <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha.groups.search_hint') }}</div>
            <input class="govuk-input govuk-!-width-two-thirds" id="q" name="q" type="search" value="{{ $groupsQuery ?? '' }}" aria-describedby="q-hint">
        </div>
        <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.actions.search') }}</button>
    </form>

    @if (empty($groups))
        <p class="govuk-inset-text">{{ __('govuk_alpha.groups.empty') }}</p>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($groups as $g)
                @php
                    $gName = trim((string) ($g['name'] ?? '')) ?: __('govuk_alpha.groups.title');
                    $gPrivate = ($g['visibility'] ?? 'public') !== 'public';
                    $gCount = (int) ($g['member_count'] ?? 0);
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1"><a class="govuk-link" href="{{ route('govuk-alpha.groups.show', ['tenantSlug' => $tenantSlug, 'id' => $g['id']]) }}">{{ $gName }}</a></h2>
                        <strong class="govuk-tag {{ $gPrivate ? 'govuk-tag--grey' : 'govuk-tag--green' }}">{{ $gPrivate ? __('govuk_alpha.groups.visibility_private') : __('govuk_alpha.groups.visibility_public') }}</strong>
                    </div>
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">{{ __('govuk_alpha.groups.members_count', ['count' => $gCount]) }}</p>
                    @if (trim((string) ($g['description'] ?? '')) !== '')
                        <p class="govuk-body govuk-!-margin-bottom-0">{{ \Illuminate\Support\Str::limit($g['description'], 160) }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
