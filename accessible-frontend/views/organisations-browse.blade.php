{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $orgs = isset($organisations) && is_array($organisations) ? $organisations : [];
        $query = $organisationsQuery ?? '';
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha_organisations.common.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_organisations.browse.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_organisations.browse.description') }}</p>

    @if (!empty($error))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_organisations.common.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list"><li><a href="#q">{{ __('govuk_alpha_organisations.common.load_error') }}</a></li></ul>
                </div>
            </div>
        </div>
    @endif

    <div class="nexus-alpha-actions govuk-!-margin-bottom-4">
        @if (($manageableCount ?? 0) > 0)
            <a class="govuk-link" href="{{ route('govuk-alpha.organisations.manage', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_organisations.browse.manage_link') }}</a>
        @endif
        <a class="govuk-link" href="{{ route('govuk-alpha.organisations.register.form', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_organisations.browse.register_link') }}</a>
    </div>

    <form method="get" action="{{ route('govuk-alpha.organisations.browse', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
        <div class="govuk-form-group">
            <label class="govuk-label" for="q">{{ __('govuk_alpha_organisations.browse.search_label') }}</label>
            <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha_organisations.browse.search_hint') }}</div>
            <input class="govuk-input govuk-!-width-two-thirds" id="q" name="q" type="search" value="{{ $query }}" aria-describedby="q-hint">
        </div>
        <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha_organisations.browse.search_submit') }}</button>
    </form>

    @if (empty($orgs))
        <div class="govuk-inset-text">
            <h2 class="govuk-heading-m govuk-!-margin-bottom-2">{{ __('govuk_alpha_organisations.browse.empty_title') }}</h2>
            <p class="govuk-body govuk-!-margin-bottom-0">{{ $query !== '' ? __('govuk_alpha_organisations.browse.empty_no_results') : __('govuk_alpha_organisations.browse.empty_none') }}</p>
        </div>
    @else
        <p class="govuk-body">{{ trans_choice('govuk_alpha_organisations.browse.count', count($orgs), ['count' => count($orgs)]) }}</p>
        <div class="nexus-alpha-card-list">
            @foreach ($orgs as $o)
                @continue(empty($o['id']))
                @php
                    $oName = trim((string) ($o['name'] ?? '')) ?: __('govuk_alpha_organisations.browse.title');
                    $oDesc = trim((string) ($o['description'] ?? ''));
                    $oppCount = (int) ($o['opportunity_count'] ?? 0);
                    $volCount = (int) ($o['volunteer_count'] ?? 0);
                    $hours = (float) ($o['total_hours'] ?? 0);
                    $rating = (float) ($o['average_rating'] ?? 0);
                    $hasWebsite = trim((string) ($o['website'] ?? '')) !== '';
                @endphp
                <article class="nexus-alpha-card">
                    <h2 class="govuk-heading-s govuk-!-margin-bottom-1">
                        <a class="govuk-link" href="{{ route('govuk-alpha.organisations.show', ['tenantSlug' => $tenantSlug, 'id' => $o['id']]) }}">{{ $oName }}</a>
                    </h2>
                    @if ($oDesc !== '')
                        <p class="govuk-body govuk-!-margin-bottom-2">{{ \Illuminate\Support\Str::limit($oDesc, 160) }}</p>
                    @endif
                    <dl class="nexus-alpha-inline-list nexus-alpha-meta">
                        <div>
                            <dt class="govuk-visually-hidden">{{ __('govuk_alpha_organisations.browse.search_label') }}</dt>
                            <dd>{{ trans_choice('govuk_alpha_organisations.browse.stat_opportunities', $oppCount, ['count' => $oppCount]) }}</dd>
                        </div>
                        @if ($volCount > 0)
                            <div>
                                <dt class="govuk-visually-hidden">{{ __('govuk_alpha_organisations.browse.search_label') }}</dt>
                                <dd>{{ trans_choice('govuk_alpha_organisations.browse.stat_volunteers', $volCount, ['count' => $volCount]) }}</dd>
                            </div>
                        @endif
                        @if ($hours > 0)
                            <div>
                                <dt class="govuk-visually-hidden">{{ __('govuk_alpha_organisations.browse.search_label') }}</dt>
                                <dd>{{ __('govuk_alpha_organisations.browse.stat_hours', ['count' => number_format($hours, ($hours == (int) $hours) ? 0 : 1)]) }}</dd>
                            </div>
                        @endif
                        @if ($rating > 0)
                            <div>
                                <dt class="govuk-visually-hidden">{{ __('govuk_alpha_organisations.browse.search_label') }}</dt>
                                <dd>
                                    <progress max="5" value="{{ number_format($rating, 1, '.', '') }}" aria-label="{{ __('govuk_alpha_organisations.browse.stat_rating', ['rating' => number_format($rating, 1)]) }}">{{ __('govuk_alpha_organisations.browse.stat_rating', ['rating' => number_format($rating, 1)]) }}</progress>
                                    <span class="govuk-!-margin-left-2">{{ number_format($rating, 1) }}</span>
                                </dd>
                            </div>
                        @endif
                        @if ($hasWebsite)
                            <div>
                                <dt class="govuk-visually-hidden">{{ __('govuk_alpha_organisations.browse.search_label') }}</dt>
                                <dd>{{ __('govuk_alpha_organisations.browse.has_website') }}</dd>
                            </div>
                        @endif
                    </dl>
                    <div class="nexus-alpha-actions">
                        <a class="govuk-link" href="{{ route('govuk-alpha.organisations.show', ['tenantSlug' => $tenantSlug, 'id' => $o['id']]) }}">{{ __('govuk_alpha_organisations.common.view_organisation') }}</a>
                    </div>
                </article>
            @endforeach
        </div>

        @if (!empty($hasMore) && !empty($nextCursor))
            <nav class="govuk-pagination govuk-pagination--block govuk-!-margin-top-6" aria-label="{{ __('govuk_alpha_organisations.browse.load_more') }}">
                <div class="govuk-pagination__next">
                    <a class="govuk-link govuk-pagination__link" rel="next" href="{{ route('govuk-alpha.organisations.browse', array_filter(['tenantSlug' => $tenantSlug, 'q' => $query !== '' ? $query : null, 'cursor' => $nextCursor])) }}">
                        <span class="govuk-pagination__link-title">{{ __('govuk_alpha_organisations.browse.load_more') }}</span>
                    </a>
                </div>
            </nav>
        @endif
    @endif
@endsection
