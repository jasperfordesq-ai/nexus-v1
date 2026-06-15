{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        // Resolve a browseable URL for each bookmarkable type.
        $resolveUrl = function (string $type, int $id, string $slug = '', string $tenantSlug = '') use (&$resolveUrl): string {
            return match ($type) {
                'listing'    => \Illuminate\Support\Facades\Route::has('govuk-alpha.listings.show')
                    ? route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $id])
                    : '',
                'event'      => \Illuminate\Support\Facades\Route::has('govuk-alpha.events.show')
                    ? route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $id])
                    : '',
                'job'        => \Illuminate\Support\Facades\Route::has('govuk-alpha.jobs.show')
                    ? route('govuk-alpha.jobs.show', ['tenantSlug' => $tenantSlug, 'id' => $id])
                    : '',
                'blog'       => ($slug !== '' && \Illuminate\Support\Facades\Route::has('govuk-alpha.blog.show'))
                    ? route('govuk-alpha.blog.show', ['tenantSlug' => $tenantSlug, 'slug' => $slug])
                    : '',
                'post', 'discussion' => \Illuminate\Support\Facades\Route::has('govuk-alpha.feed')
                    ? route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug])
                    : '',
                default => '',
            };
        };
        $savedTypeFilter = $savedTypeFilter ?? '';
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha.saved.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.saved.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.saved.description') }}</p>

    {{-- Type filter --}}
    <form method="get" action="{{ route('govuk-alpha.saved.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-4">
        <div class="govuk-form-group govuk-!-margin-bottom-2">
            <label class="govuk-label" for="saved-type-filter">{{ __('govuk_alpha.polish_discovery.saved_type_filter_label') }}</label>
            <select class="govuk-select" id="saved-type-filter" name="type">
                <option value="">{{ __('govuk_alpha.polish_discovery.saved_type_all') }}</option>
                @foreach (['post', 'listing', 'event', 'job', 'blog', 'discussion'] as $t)
                    <option value="{{ $t }}"{{ $savedTypeFilter === $t ? ' selected' : '' }}>
                        {{ \Illuminate\Support\Facades\Lang::has('govuk_alpha.saved.types.' . $t)
                            ? __('govuk_alpha.saved.types.' . $t)
                            : \Illuminate\Support\Str::headline($t) }}
                    </option>
                @endforeach
            </select>
        </div>
        <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.actions.apply') }}</button>
        @if ($savedTypeFilter !== '')
            <a class="govuk-link govuk-!-margin-left-4" href="{{ route('govuk-alpha.saved.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.clear_filters') }}</a>
        @endif
    </form>

    @if (empty($savedItems))
        <p class="govuk-inset-text">{{ __('govuk_alpha.saved.empty') }}</p>
    @else
        <ul class="govuk-list govuk-list--spaced">
            @foreach ($savedItems as $s)
                @php
                    $sTitle = trim((string) ($s['title'] ?? ''));
                    $sTypeRaw = strtolower(class_basename(trim((string) ($s['bookmarkable_type'] ?? ''))));
                    $sId = (int) ($s['bookmarkable_id'] ?? 0);
                    $sSlug = trim((string) ($s['slug'] ?? ''));
                    $sType = $sTypeRaw === ''
                        ? ''
                        : (\Illuminate\Support\Facades\Lang::has('govuk_alpha.saved.types.' . $sTypeRaw)
                            ? __('govuk_alpha.saved.types.' . $sTypeRaw)
                            : \Illuminate\Support\Str::headline($sTypeRaw));
                    $sUrl = $sId > 0 ? $resolveUrl($sTypeRaw, $sId, $sSlug, $tenantSlug) : '';
                @endphp
                @if ($sTitle !== '' || $sType !== '')
                    <li>
                        @if ($sTitle !== '')
                            @if ($sUrl !== '')
                                <a class="govuk-link" href="{{ $sUrl }}">{{ $sTitle }}</a>
                            @else
                                {{ $sTitle }}
                            @endif
                        @endif
                        @if ($sType !== '') <strong class="govuk-tag govuk-tag--grey govuk-!-margin-left-1">{{ $sType }}</strong>@endif
                        @if ($sId > 0 && $sTypeRaw !== '')
                            <form method="post" action="{{ route('govuk-alpha.saved.destroy', ['tenantSlug' => $tenantSlug]) }}" class="nexus-alpha-linkform govuk-!-display-inline-block govuk-!-margin-left-2">
                                @csrf
                                <input type="hidden" name="type" value="{{ $sTypeRaw }}">
                                <input type="hidden" name="id" value="{{ $sId }}">
                                <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0 govuk-!-font-size-16" data-module="govuk-button"
                                    aria-label="{{ __('govuk_alpha.polish_discovery.saved_remove_label', ['title' => $sTitle]) }}">{{ __('govuk_alpha.polish_discovery.saved_remove_button') }}</button>
                            </form>
                        @endif
                    </li>
                @endif
            @endforeach
        </ul>
    @endif
@endsection
