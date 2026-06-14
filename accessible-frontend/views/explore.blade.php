{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        // Each discovery facility appears once its route exists and its feature is on.
        $candidates = [
            ['govuk-alpha.search', 'search', null],
            ['govuk-alpha.groups.index', 'groups', 'groups'],
            ['govuk-alpha.goals.index', 'goals', 'goals'],
            ['govuk-alpha.skills.index', 'skills', null],
            ['govuk-alpha.organisations.index', 'organisations', 'organisations'],
            ['govuk-alpha.resources.index', 'resources', 'resources'],
            ['govuk-alpha.marketplace.index', 'marketplace', 'marketplace'],
            ['govuk-alpha.jobs.index', 'jobs', 'job_vacancies'],
            ['govuk-alpha.courses.index', 'courses', 'courses'],
            ['govuk-alpha.podcasts.index', 'podcasts', 'podcasts'],
            ['govuk-alpha.coupons.index', 'coupons', 'marketplace'],
            ['govuk-alpha.premium.index', 'premium', 'member_premium'],
            ['govuk-alpha.clubs.index', 'clubs', null],
            ['govuk-alpha.ideation.index', 'ideation', 'ideation_challenges'],
            ['govuk-alpha.federation.index', 'federation', 'federation'],
        ];
        $exploreLinks = [];
        foreach ($candidates as [$routeName, $langKey, $feature]) {
            if (!\Illuminate\Support\Facades\Route::has($routeName)) {
                continue;
            }
            if ($feature !== null && !\App\Core\TenantContext::hasFeature($feature)) {
                continue;
            }
            $exploreLinks[] = [
                'title' => __('govuk_alpha.' . $langKey . '.title'),
                'description' => __('govuk_alpha.' . $langKey . '.description'),
                'href' => route($routeName, ['tenantSlug' => $tenantSlug]),
            ];
        }
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha.explore.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.explore.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.explore.description') }}</p>

    <div class="nexus-alpha-card-list govuk-!-margin-top-6">
        @foreach ($exploreLinks as $item)
            <article class="nexus-alpha-card">
                <h2 class="govuk-heading-m govuk-!-margin-bottom-2"><a class="govuk-link" href="{{ $item['href'] }}">{{ $item['title'] }}</a></h2>
                <p class="govuk-body govuk-!-margin-bottom-0">{{ $item['description'] }}</p>
            </article>
        @endforeach
    </div>
@endsection
