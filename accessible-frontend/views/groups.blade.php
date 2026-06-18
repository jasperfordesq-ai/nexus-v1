{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <span class="govuk-caption-xl">{{ __('govuk_alpha.groups.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.groups.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.groups.description') }}</p>

    @if (($status ?? null) === 'group-deleted')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="groups-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="groups-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.groups.states.group-deleted') }}</p></div>
        </div>
    @endif

    <p class="govuk-body">
        <a class="govuk-button" role="button" draggable="false" data-module="govuk-button"
           href="{{ route('govuk-alpha.groups.create', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.polish_groups.create_group_cta') }}</a>
    </p>

    <form method="get" action="{{ route('govuk-alpha.groups.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
        <div class="govuk-form-group">
            <label class="govuk-label" for="q">{{ __('govuk_alpha.groups.search_label') }}</label>
            <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha.groups.search_hint') }}</div>
            <input class="govuk-input govuk-!-width-two-thirds" id="q" name="q" type="search" value="{{ $groupsQuery ?? '' }}" aria-describedby="q-hint">
        </div>
        <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.actions.search') }}</button>
    </form>

    @if (empty($groups))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.groups.empty') }}</p></div>
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

        @php
            $groupsPage = (int) request('page', 1);
            $groupsTotal = (int) ($groupsTotal ?? 0);
            $groupsPerPage = (int) ($groupsPerPage ?? 30);
            $groupsTotalPages = $groupsPerPage > 0 ? (int) ceil($groupsTotal / $groupsPerPage) : 1;
        @endphp
        @if ($groupsTotalPages > 1)
            <nav class="govuk-pagination govuk-!-margin-top-6" role="navigation" aria-label="{{ __('govuk_alpha.groups.pagination_label') }}">
                @if ($groupsPage > 1)
                    <div class="govuk-pagination__prev">
                        <a class="govuk-link govuk-pagination__link" href="{{ route('govuk-alpha.groups.index', array_filter(['tenantSlug' => $tenantSlug, 'page' => $groupsPage - 1, 'q' => request('q')])) }}" rel="prev">
                            <svg class="govuk-pagination__icon govuk-pagination__icon--prev" xmlns="http://www.w3.org/2000/svg" height="13" width="15" focusable="false" aria-hidden="true" viewBox="0 0 15 13">
                                <path d="m6.5938-0.0078125-6.7266 6.7266 6.7441 6.4062 1.377-1.449-4.1856-3.9768h12.896v-2h-12.984l4.2931-4.293-1.414-1.414z"/>
                            </svg>
                            <span class="govuk-pagination__link-title">{{ __('govuk_alpha.polish_groups.pagination_previous') }}</span>
                        </a>
                    </div>
                @endif
                @if ($groupsPage < $groupsTotalPages)
                    <div class="govuk-pagination__next">
                        <a class="govuk-link govuk-pagination__link" href="{{ route('govuk-alpha.groups.index', array_filter(['tenantSlug' => $tenantSlug, 'page' => $groupsPage + 1, 'q' => request('q')])) }}" rel="next">
                            <span class="govuk-pagination__link-title">{{ __('govuk_alpha.polish_groups.pagination_next') }}</span>
                            <svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" focusable="false" aria-hidden="true" viewBox="0 0 15 13">
                                <path d="m8.107-0.0078125-1.4136 1.414 4.2926 4.293h-12.986v2h12.896l-4.1855 3.9766 1.377 1.4492 6.7441-6.4062-6.7246-6.7246z"/>
                            </svg>
                        </a>
                    </div>
                @endif
            </nav>
        @endif
    @endif
@endsection
