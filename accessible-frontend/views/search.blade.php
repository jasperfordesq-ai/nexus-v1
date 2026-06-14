{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $typeMeta = [
            'listing' => ['govuk-alpha.listings.show', 'govuk-tag--blue', 'tag_listing'],
            'user'    => ['govuk-alpha.members.show', 'govuk-tag--purple', 'tag_user'],
            'event'   => ['govuk-alpha.events.show', 'govuk-tag--green', 'tag_event'],
            'group'   => ['govuk-alpha.groups.show', 'govuk-tag--turquoise', 'tag_group'],
        ];
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha.search.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.search.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.search.description') }}</p>

    <form method="get" action="{{ route('govuk-alpha.search', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
        <div class="govuk-form-group">
            <label class="govuk-label" for="q">{{ __('govuk_alpha.search.label') }}</label>
            <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha.search.hint') }}</div>
            <input class="govuk-input govuk-!-width-two-thirds" id="q" name="q" type="search" value="{{ $searchQuery }}" aria-describedby="q-hint">
        </div>
        <div class="govuk-form-group">
            <label class="govuk-label" for="type">{{ __('govuk_alpha.search.type_label') }}</label>
            <select class="govuk-select" id="type" name="type">
                @foreach (['all', 'listing', 'user', 'event', 'group'] as $t)
                    <option value="{{ $t }}" @selected($t === $searchType)>{{ __('govuk_alpha.search.type_' . $t) }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.search.button') }}</button>
    </form>

    @if ($searchQuery === '')
        <p class="govuk-inset-text">{{ __('govuk_alpha.search.no_query') }}</p>
    @elseif (empty($searchResults))
        <p class="govuk-inset-text">{{ __('govuk_alpha.search.empty') }}</p>
    @else
        <p class="govuk-body">{{ __('govuk_alpha.search.results_count', ['count' => $searchTotal]) }}</p>
        @foreach ($searchResults as $item)
            @php
                $itType = (string) ($item['type'] ?? '');
                $itTitle = trim((string) ($item['title'] ?? ($item['name'] ?? '')));
                $itId = (int) ($item['id'] ?? 0);
                $meta = $typeMeta[$itType] ?? null;
                $href = ($meta && $itId > 0 && \Illuminate\Support\Facades\Route::has($meta[0])) ? route($meta[0], ['tenantSlug' => $tenantSlug, 'id' => $itId]) : null;
            @endphp
            @if ($itTitle !== '')
                <div class="nexus-alpha-card govuk-!-margin-bottom-3">
                    <div class="nexus-alpha-module-row">
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-0">
                            @if ($href)<a class="govuk-link" href="{{ $href }}">{{ $itTitle }}</a>@else{{ $itTitle }}@endif
                        </h2>
                        @if ($meta)<strong class="govuk-tag {{ $meta[1] }}">{{ __('govuk_alpha.search.' . $meta[2]) }}</strong>@endif
                    </div>
                </div>
            @endif
        @endforeach
    @endif
@endsection
