{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $f = $talentFilters ?? ['keywords' => '', 'skills' => '', 'location' => ''];
        $meta = $talentMeta ?? ['offset' => 0, 'per_page' => 20, 'has_more' => false];
        $list = $candidates ?? [];
        $total = (int) ($talentTotal ?? 0);
        $searched = (bool) ($hasSearched ?? false);
    @endphp

    <a href="{{ route('govuk-alpha.jobs.mine', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha_jobs.shared.back_to_my_postings') }}</a>

    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_jobs.talent.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_jobs.talent.description') }}</p>

    <form method="get" action="{{ route('govuk-alpha.jobs.talent', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="talent-keywords">{{ __('govuk_alpha_jobs.talent.keywords_label') }}</label>
            <div id="talent-keywords-hint" class="govuk-hint">{{ __('govuk_alpha_jobs.talent.keywords_hint') }}</div>
            <input class="govuk-input govuk-!-width-two-thirds" id="talent-keywords" name="keywords" type="text" maxlength="120" value="{{ $f['keywords'] }}" aria-describedby="talent-keywords-hint">
        </div>
        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="talent-skills">{{ __('govuk_alpha_jobs.talent.skills_label') }}</label>
            <div id="talent-skills-hint" class="govuk-hint">{{ __('govuk_alpha_jobs.talent.skills_hint') }}</div>
            <input class="govuk-input govuk-!-width-two-thirds" id="talent-skills" name="skills" type="text" maxlength="200" value="{{ $f['skills'] }}" aria-describedby="talent-skills-hint">
        </div>
        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="talent-location">{{ __('govuk_alpha_jobs.talent.location_label') }}</label>
            <input class="govuk-input govuk-!-width-two-thirds" id="talent-location" name="location" type="text" maxlength="120" value="{{ $f['location'] }}">
        </div>
        <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_jobs.talent.search_button') }}</button>
        @if ($searched)
            <a class="govuk-link govuk-!-margin-left-3" href="{{ route('govuk-alpha.jobs.talent', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_jobs.talent.clear_button') }}</a>
        @endif
    </form>

    @if (!$searched)
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_jobs.talent.prompt') }}</p></div>
    @elseif (empty($list))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_jobs.talent.empty') }}</p></div>
    @else
        <p class="govuk-body">{{ trans_choice('govuk_alpha_jobs.talent.results_count', $total, ['count' => $total]) }}</p>
        <div class="nexus-alpha-card-list">
            @foreach ($list as $c)
                @php
                    $cId = (int) ($c['id'] ?? 0);
                    $cName = trim((string) ($c['name'] ?? '')) ?: __('govuk_alpha_jobs.shared.anonymous');
                    $cHeadline = trim((string) ($c['headline'] ?? ''));
                    $cLocation = trim((string) ($c['location'] ?? ''));
                    $cSkills = is_array($c['skills'] ?? null) ? array_slice(array_filter($c['skills']), 0, 6) : [];
                    $cAvatar = trim((string) ($c['avatar_url'] ?? ''));
                    $cActive = !empty($c['last_active']) ? \Illuminate\Support\Carbon::parse($c['last_active'])->translatedFormat('j F Y') : null;
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        @if ($cAvatar !== '')
                            <img class="nexus-alpha-avatar" src="{{ $cAvatar }}" alt="" aria-hidden="true" loading="lazy" decoding="async" width="48" height="48">
                        @else
                            <span class="nexus-alpha-avatar nexus-alpha-avatar--placeholder" aria-hidden="true">{{ mb_strtoupper(mb_substr($cName, 0, 1)) }}</span>
                        @endif
                        <div>
                            <h2 class="govuk-heading-s govuk-!-margin-bottom-1">
                                <a class="govuk-link" href="{{ route('govuk-alpha.jobs.talent.profile', ['tenantSlug' => $tenantSlug, 'candidateId' => $cId]) }}">{{ $cName }}</a>
                            </h2>
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">{{ $cHeadline !== '' ? $cHeadline : __('govuk_alpha_jobs.talent.headline_none') }}</p>
                        </div>
                    </div>

                    @if (!empty($cSkills))
                        <ul class="govuk-list nexus-alpha-tag-list govuk-!-margin-top-1 govuk-!-margin-bottom-1">
                            @foreach ($cSkills as $sk)
                                <li><strong class="govuk-tag govuk-tag--grey">{{ $sk }}</strong></li>
                            @endforeach
                        </ul>
                    @endif

                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">
                        @if ($cLocation !== ''){{ $cLocation }}@endif
                        @if ($cActive !== null) &middot; {{ __('govuk_alpha_jobs.talent.last_active', ['date' => $cActive]) }}@endif
                    </p>
                </article>
            @endforeach
        </div>

        @if (!empty($meta['has_more']))
            @php
                $nextOffset = (int) ($meta['offset'] ?? 0) + (int) ($meta['per_page'] ?? 20);
                $nextQuery = array_filter(['keywords' => $f['keywords'], 'skills' => $f['skills'], 'location' => $f['location'], 'offset' => $nextOffset], fn ($v) => $v !== '' && $v !== null);
            @endphp
            <p class="govuk-!-margin-top-4">
                <a class="govuk-link" href="{{ route('govuk-alpha.jobs.talent', array_merge(['tenantSlug' => $tenantSlug], $nextQuery)) }}">{{ __('govuk_alpha_jobs.talent.show_more') }}</a>
            </p>
        @endif
    @endif
@endsection
