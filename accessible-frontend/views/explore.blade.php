{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        // Each discovery facility appears once its route exists and its feature is on.
        $candidates = [
            ['govuk-alpha.polls.index', 'polls', 'polls'],
            ['govuk-alpha.search', 'search', null],
            ['govuk-alpha.groups.index', 'groups', 'groups'],
            ['govuk-alpha.goals.index', 'goals', 'goals'],
            ['govuk-alpha.skills.index', 'skills', null],
            ['govuk-alpha.organisations.index', 'organisations', 'organisations'],
            ['govuk-alpha.blog.index', 'blog', 'blog'],
            ['govuk-alpha.resources.index', 'resources', 'resources'],
            ['govuk-alpha.marketplace.index', 'marketplace', 'marketplace'],
            ['govuk-alpha.jobs.index', 'jobs', 'job_vacancies'],
            ['govuk-alpha.courses.index', 'courses', 'courses'],
            ['govuk-alpha.podcasts.index', 'podcasts', 'podcasts'],
            ['govuk-alpha.coupons.index', 'coupons', 'merchant_coupons'],
            ['govuk-alpha.premium.index', 'premium', 'member_premium'],
            ['govuk-alpha.ideation.index', 'ideation', 'ideation_challenges'],
            ['govuk-alpha.federation.index', 'federation', 'federation'],
        ];
        $exploreLinks = [];

        // Exchanges is gated by the listings module AND the exchange workflow being
        // enabled (not a plain feature flag), so it is surfaced explicitly — and
        // first, as a core timebanking facility moved out of the service nav.
        if (\Illuminate\Support\Facades\Route::has('govuk-alpha.exchanges.index')
            && \App\Core\TenantContext::hasModule('listings')
            && \App\Services\BrokerControlConfigService::isExchangeWorkflowEnabled()) {
            $exploreLinks[] = [
                'title' => __('govuk_alpha.exchanges.title'),
                'description' => __('govuk_alpha.exchanges.description'),
                'href' => route('govuk-alpha.exchanges.index', ['tenantSlug' => $tenantSlug]),
            ];
        }

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

        // Clubs (Vereine) have no feature flag — they only make sense for tenants
        // that actually run club organisations, so surface the card only then.
        if (\Illuminate\Support\Facades\Route::has('govuk-alpha.clubs.index')) {
            $hasClubs = false;
            try {
                $hasClubs = \Illuminate\Support\Facades\DB::table('vol_organizations')
                    ->where('tenant_id', \App\Core\TenantContext::getId())
                    ->where('org_type', 'club')
                    ->where('status', 'active')
                    ->exists();
            } catch (\Throwable $e) {
                $hasClubs = false;
            }
            if ($hasClubs) {
                $exploreLinks[] = [
                    'title' => __('govuk_alpha.clubs.title'),
                    'description' => __('govuk_alpha.clubs.description'),
                    'href' => route('govuk-alpha.clubs.index', ['tenantSlug' => $tenantSlug]),
                ];
            }
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

    {{-- ===== Live content: recent listings + upcoming events ===== --}}
    @if (!empty($exploreRecentListings ?? []))
        <h2 class="govuk-heading-l govuk-!-margin-top-8">{{ __('govuk_alpha.polish_discovery.explore_listings_title') }}</h2>
        <ul class="govuk-list govuk-list--spaced govuk-!-margin-bottom-2">
            @foreach ($exploreRecentListings as $lr)
                @php
                    $lrTitle = trim((string) ($lr['title'] ?? ''));
                    $lrId = (int) ($lr['id'] ?? 0);
                    $lrType = (($lr['type'] ?? 'offer') === 'request') ? 'request' : 'offer';
                    $lrTagClass = $lrType === 'request' ? 'govuk-tag--purple' : 'govuk-tag--blue';
                    $lrUrl = ($lrId > 0 && \Illuminate\Support\Facades\Route::has('govuk-alpha.listings.show'))
                        ? route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $lrId])
                        : '';
                @endphp
                @if ($lrTitle !== '')
                    <li>
                        @if ($lrUrl !== '')
                            <a class="govuk-link" href="{{ $lrUrl }}">{{ $lrTitle }}</a>
                        @else
                            {{ $lrTitle }}
                        @endif
                        <strong class="govuk-tag {{ $lrTagClass }} govuk-!-margin-left-1">{{ __('govuk_alpha.listings.' . $lrType) }}</strong>
                    </li>
                @endif
            @endforeach
        </ul>
        @if (\Illuminate\Support\Facades\Route::has('govuk-alpha.listings.index'))
            <p class="govuk-body"><a class="govuk-link" href="{{ route('govuk-alpha.listings.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.polish_discovery.explore_view_all_listings') }}</a></p>
        @endif
    @endif

    @if (!empty($exploreUpcomingEvents ?? []))
        <h2 class="govuk-heading-l govuk-!-margin-top-6">{{ __('govuk_alpha.polish_discovery.explore_events_title') }}</h2>
        <ul class="govuk-list govuk-list--spaced govuk-!-margin-bottom-2">
            @foreach ($exploreUpcomingEvents as $ev)
                @php
                    $evTitle = trim((string) ($ev['title'] ?? ''));
                    $evId = (int) ($ev['id'] ?? 0);
                    $evDate = $ev['start_date'] ?? ($ev['event_date'] ?? null);
                    $evWhen = $evDate ? \Illuminate\Support\Carbon::parse($evDate)->format('j M Y') : null;
                    $evUrl = ($evId > 0 && \Illuminate\Support\Facades\Route::has('govuk-alpha.events.show'))
                        ? route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $evId])
                        : '';
                @endphp
                @if ($evTitle !== '')
                    <li>
                        @if ($evUrl !== '')
                            <a class="govuk-link" href="{{ $evUrl }}">{{ $evTitle }}</a>
                        @else
                            {{ $evTitle }}
                        @endif
                        @if ($evWhen) <span class="govuk-body-s nexus-alpha-meta">— {{ $evWhen }}</span>@endif
                    </li>
                @endif
            @endforeach
        </ul>
        @if (\Illuminate\Support\Facades\Route::has('govuk-alpha.events.index'))
            <p class="govuk-body"><a class="govuk-link" href="{{ route('govuk-alpha.events.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.polish_discovery.explore_view_all_events') }}</a></p>
        @endif
    @endif
@endsection
