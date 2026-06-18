{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $f = $filters ?? [];
        $skillsList = $skillsList ?? [];
        $grouped = $grouped ?? ['listings' => [], 'users' => [], 'events' => [], 'groups' => []];
        $activeTab = $activeTab ?? 'all';
        $hasSearched = !empty($hasSearched);
        $total = (int) ($searchTotal ?? 0);
        $activeFilterCount = (int) ($activeFilterCount ?? 0);

        $countListings = count($grouped['listings'] ?? []);
        $countUsers = count($grouped['users'] ?? []);
        $countEvents = count($grouped['events'] ?? []);
        $countGroups = count($grouped['groups'] ?? []);

        // The current filter set as query params, so links (popular tags, tabs)
        // can preserve everything else.
        $baseParams = array_filter([
            'q' => $searchQuery ?: null,
            'type' => ($f['type'] ?? 'all') !== 'all' ? $f['type'] : null,
            'category_id' => ($f['category_id'] ?? 0) > 0 ? $f['category_id'] : null,
            'sort' => ($f['sort'] ?? 'relevance') !== 'relevance' ? $f['sort'] : null,
            'skills' => !empty($skillsList) ? implode(',', $skillsList) : null,
            'date_from' => ($f['date_from'] ?? '') !== '' ? $f['date_from'] : null,
            'date_to' => ($f['date_to'] ?? '') !== '' ? $f['date_to'] : null,
            'location' => ($f['location'] ?? '') !== '' ? $f['location'] : null,
        ], fn ($v) => $v !== null && $v !== '');

        $tabHref = fn (string $tab) => route('govuk-alpha.search.advanced', array_merge(['tenantSlug' => $tenantSlug], $baseParams, $tab !== 'all' ? ['tab' => $tab] : []));
    @endphp

    {{-- Status banners --}}
    @if (in_array($status ?? null, ['search-saved', 'search-deleted'], true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="search-status-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="search-status-title">{{ __('govuk_alpha_search.saved.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">
                    {{ ($status === 'search-saved') ? __('govuk_alpha_search.saved.saved_banner') : __('govuk_alpha_search.saved.deleted_banner') }}
                </p>
            </div>
        </div>
    @elseif (in_array($status ?? null, ['search-save-failed', 'search-delete-failed'], true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_search.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p>{{ ($status === 'search-save-failed') ? __('govuk_alpha_search.saved.save_failed_banner') : __('govuk_alpha_search.saved.delete_failed_banner') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if ($hasSearched && !empty($searchError))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_search.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p>{{ __('govuk_alpha_search.states.error') }}</p>
                    <p><a class="govuk-link" href="{{ url()->full() }}">{{ __('govuk_alpha_search.states.try_again') }}</a></p>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ __('govuk_alpha_search.advanced.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_search.advanced.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_search.advanced.description') }}</p>

    <p class="govuk-body">
        <a class="govuk-link" href="{{ route('govuk-alpha.search', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_search.advanced.simple_view_link') }}</a>
    </p>

    {{-- Search + filters form --}}
    <form method="get" action="{{ route('govuk-alpha.search.advanced', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--m" for="q">{{ __('govuk_alpha_search.query.label') }}</label>
            <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha_search.query.hint') }}</div>
            <input class="govuk-input govuk-!-width-two-thirds" id="q" name="q" type="search" value="{{ $searchQuery ?? '' }}" aria-describedby="q-hint">
        </div>

        {{-- Advanced filters in a disclosure so they are progressive but always reachable --}}
        <details class="govuk-details" data-module="govuk-details" @if ($activeFilterCount > 0) open @endif>
            <summary class="govuk-details__summary">
                <span class="govuk-details__summary-text">
                    {{ $activeFilterCount > 0
                        ? __('govuk_alpha_search.filters.summary_with_count', ['count' => $activeFilterCount])
                        : __('govuk_alpha_search.filters.summary') }}
                </span>
            </summary>
            <div class="govuk-details__text">
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                        <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha_search.filters.legend') }}</h2>
                    </legend>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="type">{{ __('govuk_alpha_search.filters.content_type') }}</label>
                        <select class="govuk-select" id="type" name="type">
                            <option value="all" @selected(($f['type'] ?? 'all') === 'all')>{{ __('govuk_alpha_search.filters.all_types') }}</option>
                            <option value="listings" @selected(($f['type'] ?? '') === 'listings')>{{ __('govuk_alpha_search.filters.type_listings') }}</option>
                            <option value="users" @selected(($f['type'] ?? '') === 'users')>{{ __('govuk_alpha_search.filters.type_users') }}</option>
                            <option value="events" @selected(($f['type'] ?? '') === 'events')>{{ __('govuk_alpha_search.filters.type_events') }}</option>
                            <option value="groups" @selected(($f['type'] ?? '') === 'groups')>{{ __('govuk_alpha_search.filters.type_groups') }}</option>
                        </select>
                    </div>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="category_id">{{ __('govuk_alpha_search.filters.category') }}</label>
                        <select class="govuk-select" id="category_id" name="category_id">
                            <option value="0" @selected(((int) ($f['category_id'] ?? 0)) === 0)>{{ __('govuk_alpha_search.filters.all_categories') }}</option>
                            @foreach ($categories ?? [] as $cat)
                                <option value="{{ (int) $cat['id'] }}" @selected(((int) ($f['category_id'] ?? 0)) === (int) $cat['id'])>{{ $cat['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="sort">{{ __('govuk_alpha_search.filters.sort_by') }}</label>
                        <select class="govuk-select" id="sort" name="sort">
                            <option value="relevance" @selected(($f['sort'] ?? 'relevance') === 'relevance')>{{ __('govuk_alpha_search.filters.sort_relevance') }}</option>
                            <option value="newest" @selected(($f['sort'] ?? '') === 'newest')>{{ __('govuk_alpha_search.filters.sort_newest') }}</option>
                            <option value="oldest" @selected(($f['sort'] ?? '') === 'oldest')>{{ __('govuk_alpha_search.filters.sort_oldest') }}</option>
                        </select>
                    </div>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="date_from">{{ __('govuk_alpha_search.filters.date_from') }}</label>
                        <div id="date-from-hint" class="govuk-hint">{{ __('govuk_alpha_search.filters.date_from_hint') }}</div>
                        <input class="govuk-input govuk-input--width-10" id="date_from" name="date_from" type="date" value="{{ $f['date_from'] ?? '' }}" aria-describedby="date-from-hint">
                    </div>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="date_to">{{ __('govuk_alpha_search.filters.date_to') }}</label>
                        <div id="date-to-hint" class="govuk-hint">{{ __('govuk_alpha_search.filters.date_to_hint') }}</div>
                        <input class="govuk-input govuk-input--width-10" id="date_to" name="date_to" type="date" value="{{ $f['date_to'] ?? '' }}" aria-describedby="date-to-hint">
                    </div>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="location">{{ __('govuk_alpha_search.filters.location') }}</label>
                        <div id="location-hint" class="govuk-hint">{{ __('govuk_alpha_search.filters.location_hint') }}</div>
                        <input class="govuk-input govuk-!-width-two-thirds" id="location" name="location" type="text" value="{{ $f['location'] ?? '' }}" aria-describedby="location-hint" autocomplete="off">
                    </div>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="skills">{{ __('govuk_alpha_search.filters.skills') }}</label>
                        <div id="skills-hint" class="govuk-hint">{{ __('govuk_alpha_search.filters.skills_hint') }}</div>
                        <input class="govuk-input govuk-!-width-two-thirds" id="skills" name="skills" type="text" value="{{ !empty($skillsList) ? implode(', ', $skillsList) : '' }}" aria-describedby="skills-hint" autocomplete="off">

                        @if (!empty($skillsList))
                            <p class="govuk-body-s govuk-!-margin-top-2 govuk-!-margin-bottom-0">{{ __('govuk_alpha_search.filters.active_skills') }}</p>
                            <ul class="govuk-list nexus-alpha-inline-list govuk-!-margin-bottom-0">
                                @foreach ($skillsList as $skill)
                                    <li><strong class="govuk-tag govuk-tag--blue">{{ $skill }}</strong></li>
                                @endforeach
                            </ul>
                        @endif

                        @if (!empty($popularTags))
                            <p class="govuk-body-s govuk-!-margin-top-2 govuk-!-margin-bottom-1">{{ __('govuk_alpha_search.filters.popular') }}</p>
                            <ul class="govuk-list nexus-alpha-inline-list govuk-!-margin-bottom-0">
                                @foreach ($popularTags as $tag)
                                    @php
                                        $tagSkills = $skillsList;
                                        $tagSkills[] = $tag;
                                        $tagSkills = array_values(array_unique($tagSkills));
                                        $tagParams = array_merge($baseParams, ['skills' => implode(',', $tagSkills)]);
                                    @endphp
                                    <li>
                                        <a class="govuk-link" href="{{ route('govuk-alpha.search.advanced', array_merge(['tenantSlug' => $tenantSlug], $tagParams)) }}"
                                           aria-label="{{ __('govuk_alpha_search.filters.popular_add', ['tag' => $tag]) }}">{{ $tag }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </fieldset>

                <div class="govuk-button-group">
                    <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_search.filters.apply') }}</button>
                    @if ($activeFilterCount > 0)
                        <a class="govuk-link" href="{{ route('govuk-alpha.search.advanced', array_filter(['tenantSlug' => $tenantSlug, 'q' => $searchQuery ?: null])) }}">{{ __('govuk_alpha_search.filters.reset') }}</a>
                    @endif
                </div>
            </div>
        </details>

        <div class="govuk-button-group">
            <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_search.query.submit') }}</button>
            @if ($searchQuery ?? '')
                <a class="govuk-link" href="{{ route('govuk-alpha.search.advanced', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_search.query.clear') }}</a>
            @endif
        </div>
    </form>

    {{-- Save current search --}}
    @if ($hasSearched && empty($searchError))
        <details class="govuk-details" data-module="govuk-details">
            <summary class="govuk-details__summary">
                <span class="govuk-details__summary-text">{{ __('govuk_alpha_search.saved.save_this') }}</span>
            </summary>
            <div class="govuk-details__text">
                <form method="post" action="{{ route('govuk-alpha.search.saved.save', ['tenantSlug' => $tenantSlug]) }}">
                    @csrf
                    <input type="hidden" name="q" value="{{ $searchQuery ?? '' }}">
                    <input type="hidden" name="type" value="{{ $f['type'] ?? 'all' }}">
                    <input type="hidden" name="category_id" value="{{ (int) ($f['category_id'] ?? 0) }}">
                    <input type="hidden" name="sort" value="{{ $f['sort'] ?? 'relevance' }}">
                    <input type="hidden" name="skills" value="{{ !empty($skillsList) ? implode(',', $skillsList) : '' }}">
                    <input type="hidden" name="date_from" value="{{ $f['date_from'] ?? '' }}">
                    <input type="hidden" name="date_to" value="{{ $f['date_to'] ?? '' }}">
                    <input type="hidden" name="location" value="{{ $f['location'] ?? '' }}">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="saved-name">{{ __('govuk_alpha_search.saved.name_label') }}</label>
                        <div id="saved-name-hint" class="govuk-hint">{{ __('govuk_alpha_search.saved.name_hint') }}</div>
                        <input class="govuk-input govuk-!-width-two-thirds" id="saved-name" name="name" type="text" value="{{ $searchQuery ?? '' }}" aria-describedby="saved-name-hint" maxlength="255">
                    </div>
                    <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha_search.saved.save_submit') }}</button>
                </form>
            </div>
        </details>
    @endif

    {{-- Saved searches list --}}
    @if (!empty($savedSearches))
        <div class="nexus-alpha-card govuk-!-margin-bottom-6">
            <h2 class="govuk-heading-m">{{ trans_choice('govuk_alpha_search.saved.count', count($savedSearches), ['count' => count($savedSearches)]) }}</h2>
            <ul class="govuk-list govuk-!-margin-bottom-0">
                @foreach ($savedSearches as $s)
                    <li class="nexus-alpha-module-row govuk-!-padding-top-2 govuk-!-padding-bottom-2">
                        <span>
                            <strong>{{ $s['name'] }}</strong>
                            <span class="nexus-alpha-meta">
                                {{ ($s['query'] ?? '') !== '' ? $s['query'] : __('govuk_alpha_search.saved.no_query') }}
                                @if (($s['last_result_count'] ?? null) !== null)
                                    &middot; {{ __('govuk_alpha_search.saved.last_result_count', ['count' => $s['last_result_count']]) }}
                                @endif
                            </span>
                        </span>
                        <span class="nexus-alpha-actions">
                            <form method="post" action="{{ route('govuk-alpha.search.saved.run', ['tenantSlug' => $tenantSlug, 'id' => $s['id']]) }}" class="govuk-!-display-inline">
                                @csrf
                                <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button"
                                        aria-label="{{ __('govuk_alpha_search.saved.run_aria', ['name' => $s['name']]) }}">{{ __('govuk_alpha_search.saved.run') }}</button>
                            </form>
                            <a class="govuk-link" href="{{ route('govuk-alpha.search.saved.delete.confirm', ['tenantSlug' => $tenantSlug, 'id' => $s['id']]) }}"
                               aria-label="{{ __('govuk_alpha_search.saved.delete_aria', ['name' => $s['name']]) }}">{{ __('govuk_alpha_search.saved.delete') }}</a>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Results --}}
    @if (!$hasSearched)
        <div class="govuk-inset-text">
            <h2 class="govuk-heading-m">{{ __('govuk_alpha_search.states.no_query_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha_search.states.no_query') }}</p>
            <ul class="govuk-list govuk-list--bullet">
                <li>{{ __('govuk_alpha_search.states.no_query_tip_services') }}</li>
                <li>{{ __('govuk_alpha_search.states.no_query_tip_people') }}</li>
                <li>{{ __('govuk_alpha_search.states.no_query_tip_events') }}</li>
            </ul>
        </div>
    @elseif (!empty($searchError))
        {{-- error summary already rendered above --}}
    @elseif ($total === 0)
        <div class="govuk-inset-text">
            <h2 class="govuk-heading-m">{{ __('govuk_alpha_search.states.empty_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha_search.states.empty', ['query' => $searchQuery]) }}</p>
            <ul class="govuk-list govuk-list--bullet">
                <li>{{ __('govuk_alpha_search.states.empty_tip_spelling') }}</li>
                <li>{{ __('govuk_alpha_search.states.empty_tip_filters') }}</li>
                <li>{{ __('govuk_alpha_search.states.empty_tip_broaden') }}</li>
            </ul>
        </div>
    @else
        <p class="govuk-body nexus-alpha-result-count" aria-live="polite">
            {{ trans_choice('govuk_alpha_search.results.count', $total, ['count' => $total]) }}
        </p>

        {{-- Tabs (rendered as a no-JS nav of links that re-request with ?tab=) --}}
        <nav class="govuk-!-margin-bottom-4" aria-label="{{ __('govuk_alpha_search.advanced.title') }}">
            <ul class="govuk-list nexus-alpha-inline-list">
                <li><a class="govuk-link {{ $activeTab === 'all' ? 'govuk-!-font-weight-bold' : '' }}" @if ($activeTab === 'all') aria-current="true" @endif href="{{ $tabHref('all') }}">{{ __('govuk_alpha_search.results.tab_all', ['count' => $total]) }}</a></li>
                <li><a class="govuk-link {{ $activeTab === 'listings' ? 'govuk-!-font-weight-bold' : '' }}" @if ($activeTab === 'listings') aria-current="true" @endif href="{{ $tabHref('listings') }}">{{ __('govuk_alpha_search.results.tab_listings', ['count' => $countListings]) }}</a></li>
                <li><a class="govuk-link {{ $activeTab === 'users' ? 'govuk-!-font-weight-bold' : '' }}" @if ($activeTab === 'users') aria-current="true" @endif href="{{ $tabHref('users') }}">{{ __('govuk_alpha_search.results.tab_users', ['count' => $countUsers]) }}</a></li>
                <li><a class="govuk-link {{ $activeTab === 'events' ? 'govuk-!-font-weight-bold' : '' }}" @if ($activeTab === 'events') aria-current="true" @endif href="{{ $tabHref('events') }}">{{ __('govuk_alpha_search.results.tab_events', ['count' => $countEvents]) }}</a></li>
                <li><a class="govuk-link {{ $activeTab === 'groups' ? 'govuk-!-font-weight-bold' : '' }}" @if ($activeTab === 'groups') aria-current="true" @endif href="{{ $tabHref('groups') }}">{{ __('govuk_alpha_search.results.tab_groups', ['count' => $countGroups]) }}</a></li>
            </ul>
        </nav>

        @if (($activeTab === 'all' || $activeTab === 'listings') && $countListings > 0)
            @include('accessible-frontend::partials.search-listings', [
                'tenantSlug' => $tenantSlug,
                'items' => $activeTab === 'all' ? array_slice($grouped['listings'], 0, 4) : $grouped['listings'],
                'showHeading' => $activeTab === 'all',
            ])
        @endif

        @if (($activeTab === 'all' || $activeTab === 'users') && $countUsers > 0)
            @include('accessible-frontend::partials.search-users', [
                'tenantSlug' => $tenantSlug,
                'items' => $activeTab === 'all' ? array_slice($grouped['users'], 0, 4) : $grouped['users'],
                'showHeading' => $activeTab === 'all',
            ])
        @endif

        @if (($activeTab === 'all' || $activeTab === 'events') && $countEvents > 0)
            @include('accessible-frontend::partials.search-events', [
                'tenantSlug' => $tenantSlug,
                'items' => $activeTab === 'all' ? array_slice($grouped['events'], 0, 4) : $grouped['events'],
                'showHeading' => $activeTab === 'all',
            ])
        @endif

        @if (($activeTab === 'all' || $activeTab === 'groups') && $countGroups > 0)
            @include('accessible-frontend::partials.search-groups', [
                'tenantSlug' => $tenantSlug,
                'items' => $activeTab === 'all' ? array_slice($grouped['groups'], 0, 4) : $grouped['groups'],
                'showHeading' => $activeTab === 'all',
            ])
        @endif
    @endif
@endsection
