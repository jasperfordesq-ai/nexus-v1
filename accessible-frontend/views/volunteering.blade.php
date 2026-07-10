{{-- Copyright (c) 2024-2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $hasFilters = !empty($filters['search']) || !empty($filters['category_id']) || !empty($filters['is_remote']);
        $formatDate = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y') : null;
        $formatDateTime = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y, g:ia') : null;
        $selectedTab = $selectedTab ?? 'opportunities';
        $label = fn (string $ns, ?string $key): string => ($key !== null && $key !== '' && \Illuminate\Support\Facades\Lang::has("govuk_alpha.$ns.$key"))
            ? __("govuk_alpha.$ns.$key")
            : \Illuminate\Support\Str::headline((string) $key);
    @endphp

    <span class="govuk-caption-l">{{ __('govuk_alpha.volunteering.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.volunteering.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.volunteering.description') }}</p>

    {{-- Organisations no longer lives on the main service nav, so the volunteer
         hero carries the link to browse community organisations. --}}
    @unless ($moduleDisabled)
        <p class="govuk-body govuk-!-margin-bottom-6">
            <a class="govuk-link govuk-link--no-visited-state" href="{{ route('govuk-alpha.organisations.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.vol_org.browse_link') }}</a>
        </p>
    @endunless

    {{-- Plain-language "how volunteering works" so a first-time volunteer isn't
         guessing what to do or how they earn time credits. Mirrors the React
         how_it_works copy, presented as a numbered list for clear, scannable steps. --}}
    @unless ($moduleDisabled)
        <div class="govuk-inset-text" role="note" aria-labelledby="how-it-works-heading">
            <h2 class="govuk-heading-s govuk-!-margin-bottom-2" id="how-it-works-heading">{{ __('govuk_alpha.vol_clarity.how_it_works_title') }}</h2>
            <ol class="govuk-list govuk-list--number govuk-!-margin-bottom-0">
                <li>{{ __('govuk_alpha.vol_clarity.how_step_find') }}</li>
                <li>{{ __('govuk_alpha.vol_clarity.how_step_apply') }}</li>
                <li>{{ __('govuk_alpha.vol_clarity.how_step_log') }}</li>
                <li>{{ __('govuk_alpha.vol_clarity.how_step_approve') }}</li>
                <li>{{ __('govuk_alpha.vol_clarity.how_step_credit') }}</li>
            </ol>
        </div>
    @endunless

    @if ($moduleDisabled)
        <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="volunteering-disabled-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="volunteering-disabled-title">{{ __('govuk_alpha.states.error_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.states.volunteering_disabled') }}</p>
                <p class="govuk-body">{{ __('govuk_alpha.volunteering.module_disabled_detail', ['community' => $communityName]) }}</p>
            </div>
        </div>
    @else
        @if (($status ?? null) === 'application-withdrawn')
            <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="application-withdrawn-title">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="application-withdrawn-title">{{ __('govuk_alpha.states.success_title') }}</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.volunteering.application_withdrawn') }}</p>
                </div>
            </div>
        @elseif (($status ?? null) === 'application-withdraw-failed')
            <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                <div role="alert">
                    <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                    <div class="govuk-error-summary__body">
                        <p>{{ __('govuk_alpha.volunteering.application_withdraw_failed') }}</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- ───────── The "two hats" ORGANISATION door ─────────
             Mirrors the React VolunteeringPage: a volunteer is one hat, running an
             organisation is the other. Owners of an approved org get a prominent
             "Post an opportunity" + "Manage organisation" door (this is the link
             Jasper could not find); a pending org shows an awaiting-approval note;
             a member with no org gets a quiet "register your organisation" nudge;
             guests are invited to sign in. --}}
        @php
            $manageableOrgs = [];
            $pendingOwnedOrgs = [];
            foreach (($organizations ?? []) as $orgItem) {
                $orgRole = (string) ($orgItem['member_role'] ?? 'member');
                $orgStatus = (string) ($orgItem['status'] ?? 'pending');
                if (in_array($orgRole, ['owner', 'admin'], true)) {
                    if (in_array($orgStatus, ['approved', 'active'], true)) {
                        $manageableOrgs[] = $orgItem;
                    } else {
                        $pendingOwnedOrgs[] = $orgItem;
                    }
                }
            }
            $soleManagedOrg = count($manageableOrgs) === 1 ? $manageableOrgs[0] : null;
            $firstPendingOrg = $pendingOwnedOrgs[0] ?? null;
            $manageOrgHref = $soleManagedOrg
                ? route('govuk-alpha.volunteering.org.dashboard', ['tenantSlug' => $tenantSlug, 'id' => $soleManagedOrg['id']])
                : route('govuk-alpha.volunteering.my-organisations', ['tenantSlug' => $tenantSlug]);
        @endphp

        @if ($requiresAuth)
            <section class="nexus-alpha-card govuk-!-margin-bottom-6" aria-labelledby="vol-org-door-title">
                <span class="govuk-caption-m">{{ __('govuk_alpha.vol_org.door_eyebrow') }}</span>
                <h2 class="govuk-heading-m govuk-!-margin-bottom-2" id="vol-org-door-title">{{ __('govuk_alpha.vol_org.door_register_title') }}</h2>
                <p class="govuk-body">{{ __('govuk_alpha.vol_org.door_guest_desc') }}</p>
                <a class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button" role="button" draggable="false" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']) }}">{{ __('govuk_alpha.nav.login') }}</a>
            </section>
        @elseif (count($manageableOrgs) > 0)
            <section class="nexus-alpha-card govuk-!-margin-bottom-6" aria-labelledby="vol-org-door-title">
                <span class="govuk-caption-m">{{ __('govuk_alpha.vol_org.door_eyebrow') }}</span>
                <h2 class="govuk-heading-m govuk-!-margin-bottom-2" id="vol-org-door-title">
                    @if ($soleManagedOrg)
                        {{ __('govuk_alpha.vol_org.door_heading_one', ['name' => $soleManagedOrg['name'] ?? '']) }}
                    @else
                        {{ __('govuk_alpha.vol_org.door_heading_many', ['count' => count($manageableOrgs)]) }}
                    @endif
                </h2>
                <p class="govuk-body">{{ __('govuk_alpha.vol_org.door_desc') }}</p>
                <div class="govuk-button-group govuk-!-margin-bottom-0">
                    <a class="govuk-button" data-module="govuk-button" role="button" draggable="false" href="{{ route('govuk-alpha.volunteering.opportunities.create', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_volunteering.create_opp.submit_button') }}</a>
                    <a class="govuk-button govuk-button--secondary" data-module="govuk-button" role="button" draggable="false" href="{{ $manageOrgHref }}">{{ __('govuk_alpha.vol_org.manage_link') }}</a>
                </div>
            </section>
        @elseif (count($pendingOwnedOrgs) > 0)
            <div class="govuk-notification-banner govuk-!-margin-bottom-6" data-module="govuk-notification-banner" role="region" aria-labelledby="vol-org-pending-title">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="vol-org-pending-title">{{ __('govuk_alpha.vol_org.door_eyebrow') }}</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.vol_org.awaiting_approval') }}</p>
                    <p class="govuk-body">{{ __('govuk_alpha.vol_org.awaiting_approval_hint') }}</p>
                    @if (!empty($firstPendingOrg['name']))
                        <p class="govuk-body govuk-!-margin-bottom-0">{{ $firstPendingOrg['name'] }}</p>
                    @endif
                </div>
            </div>
        @else
            <section class="nexus-alpha-card govuk-!-margin-bottom-6" aria-labelledby="vol-org-door-title">
                <span class="govuk-caption-m">{{ __('govuk_alpha.vol_org.door_eyebrow') }}</span>
                <h2 class="govuk-heading-m govuk-!-margin-bottom-2" id="vol-org-door-title">{{ __('govuk_alpha.vol_org.door_register_title') }}</h2>
                <p class="govuk-body">{{ __('govuk_alpha.vol_org.door_register_desc') }}</p>
                <a class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button" role="button" draggable="false" href="{{ route('govuk-alpha.organisations.register', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_volunteering.create_opp.register_link') }}</a>
            </section>
        @endif

        @if ($requiresAuth)
            <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="volunteering-auth-title">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="volunteering-auth-title">{{ __('govuk_alpha.states.auth_required') }}</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-body">{{ __('govuk_alpha.volunteering.auth_required_detail') }}</p>
                </div>
            </div>
        @else
            @php
                $summary = $hoursSummary ?? [];
            @endphp
            <h2 class="govuk-heading-l">{{ __('govuk_alpha.volunteering.hours_summary_title') }}</h2>
            <dl class="nexus-alpha-stat-grid">
                <div class="nexus-alpha-stat">
                    <dt>{{ __('govuk_alpha.volunteering.approved_hours') }}</dt>
                    <dd>{{ number_format((float) ($summary['total_approved_hours'] ?? $summary['approved_hours'] ?? 0), 1) }}</dd>
                </div>
                <div class="nexus-alpha-stat">
                    <dt>{{ __('govuk_alpha.volunteering.pending_hours') }}</dt>
                    <dd>{{ number_format((float) ($summary['pending_hours'] ?? 0), 1) }}</dd>
                </div>
                <div class="nexus-alpha-stat">
                    <dt>{{ __('govuk_alpha.volunteering.this_month_hours') }}</dt>
                    <dd>{{ number_format((float) ($summary['this_month_hours'] ?? 0), 1) }}</dd>
                </div>
            </dl>
            {{-- Volunteer "tools" grouped under a clear heading so the page reads
                 as: your hours → your tools → browse. POLISH: govuk-list for
                 semantic nav instead of <p> with middot separators. --}}
            <h2 class="govuk-heading-m govuk-!-margin-top-6 govuk-!-margin-bottom-2">{{ __('govuk_alpha.vol_org.tools_title') }}</h2>
            <ul class="govuk-list govuk-list--inline govuk-!-margin-bottom-4">
                <li><a class="govuk-link" href="{{ route('govuk-alpha.volunteering.hours', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.volunteering.log_hours_title') }}</a></li>
                <li><a class="govuk-link" href="{{ route('govuk-alpha.volunteering.accessibility', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.volunteering.accessibility_link') }}</a></li>
                <li><a class="govuk-link" href="{{ route('govuk-alpha.volunteering.certificates', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.vol_depth.certificates_link') }}</a></li>
                <li><a class="govuk-link" href="{{ route('govuk-alpha.volunteering.waitlist', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.vol_depth.waitlist_link') }}</a></li>
                <li><a class="govuk-link" href="{{ route('govuk-alpha.volunteering.swaps', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.vol_depth.swaps_link') }}</a></li>
                <li><a class="govuk-link" href="{{ route('govuk-alpha.volunteering.group-signups', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_volunteering.group_signups.nav_link') }}</a></li>
                <li><a class="govuk-link" href="{{ route('govuk-alpha.volunteering.expenses', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_volunteering.expenses.nav_link') }}</a></li>
                <li><a class="govuk-link" href="{{ route('govuk-alpha.volunteering.donations', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_volunteering.donations.nav_link') }}</a></li>
            </ul>

            {{-- Server-side section switching (no JS) — sub-navigation of links,
                 not govuk-tabs, which is reserved for in-page panel switching.
                 Same pattern as partials/messages-subnav.blade.php. --}}
            <nav class="govuk-!-margin-top-6 govuk-!-margin-bottom-4" aria-label="{{ __('govuk_alpha.volunteering.tabs_title') }}">
                <ul class="govuk-list" style="display:flex;flex-wrap:wrap;gap:1rem;list-style:none;padding:0;margin:0 0 1rem">
                    <li>
                        <a class="govuk-link{{ $selectedTab === 'opportunities' ? ' govuk-link--no-visited-state' : '' }}"
                           href="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug]) }}"
                           @if ($selectedTab === 'opportunities') aria-current="page" @endif>{{ __('govuk_alpha.volunteering.tabs.opportunities') }}</a>
                    </li>
                    <li>
                        <a class="govuk-link{{ $selectedTab === 'applications' ? ' govuk-link--no-visited-state' : '' }}"
                           href="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug, 'tab' => 'applications']) }}"
                           @if ($selectedTab === 'applications') aria-current="page" @endif>{{ __('govuk_alpha.volunteering.tabs.applications') }}</a>
                    </li>
                    <li>
                        <a class="govuk-link{{ $selectedTab === 'recommended' ? ' govuk-link--no-visited-state' : '' }}"
                           href="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug, 'tab' => 'recommended']) }}"
                           @if ($selectedTab === 'recommended') aria-current="page" @endif>{{ __('govuk_alpha.volunteering.tabs.recommended') }}</a>
                    </li>
                    <li>
                        <a class="govuk-link{{ $selectedTab === 'community_projects' ? ' govuk-link--no-visited-state' : '' }}"
                           href="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug, 'tab' => 'community_projects']) }}"
                           @if ($selectedTab === 'community_projects') aria-current="page" @endif>{{ __('govuk_alpha.volunteering.community_projects_tab') }}</a>
                    </li>
                    <li>
                        <a class="govuk-link" href="{{ route('govuk-alpha.volunteering.hours', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.volunteering.tabs.hours') }}</a>
                    </li>
                </ul>
            </nav>
        @endif

        @if (!$requiresAuth && $selectedTab === 'applications')
            <h2 class="govuk-heading-l">{{ __('govuk_alpha.volunteering.applications_title') }}</h2>
            <form method="get" action="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
                <input type="hidden" name="tab" value="applications">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="app_status">{{ __('govuk_alpha.volunteering.application_status_label') }}</label>
                    <select class="govuk-select" id="app_status" name="app_status">
                        <option value="">{{ __('govuk_alpha.volunteering.application_status_all') }}</option>
                        @foreach (['pending', 'approved', 'declined', 'withdrawn'] as $appStatusOption)
                            <option value="{{ $appStatusOption }}" @selected(($applicationsStatus ?? null) === $appStatusOption)>{{ $label('volunteering.status_values', $appStatusOption) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="govuk-button-group">
                    <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.actions.apply_filters') }}</button>
                    @if (!empty($applicationsStatus))
                        <a class="govuk-link" href="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug, 'tab' => 'applications']) }}">{{ __('govuk_alpha.actions.clear_filters') }}</a>
                    @endif
                </div>
            </form>
            @if (empty($applications))
                <div class="govuk-inset-text">
                    <p class="govuk-body">{{ __('govuk_alpha.volunteering.empty_applications') }}</p>
                    <p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha.vol_clarity.empty_applications_cta') }}</p>
                    <a class="govuk-button govuk-button--secondary govuk-!-margin-top-2 govuk-!-margin-bottom-0" data-module="govuk-button" role="button" draggable="false" href="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.vol_clarity.browse_opportunities') }}</a>
                </div>
            @else
                <div class="nexus-alpha-card-list">
                    @foreach ($applications as $application)
                        @php
                            $opportunity = $application['opportunity'] ?? [];
                            $organization = $application['organization'] ?? [];
                            $statusValue = (string) ($application['status'] ?? 'pending');
                            $shift = $application['shift'] ?? null;
                        @endphp
                        <article class="nexus-alpha-card">
                            <h3 class="govuk-heading-m govuk-!-margin-bottom-2">
                                @if (!empty($opportunity['id']))
                                    <a class="govuk-link" href="{{ route('govuk-alpha.volunteering.show', ['tenantSlug' => $tenantSlug, 'id' => $opportunity['id']]) }}">{{ $opportunity['title'] ?? __('govuk_alpha.volunteering.detail_title') }}</a>
                                @else
                                    {{ $opportunity['title'] ?? __('govuk_alpha.volunteering.detail_title') }}
                                @endif
                            </h3>
                            @php
                                $appStatusTagClass = [
                                    'approved' => 'govuk-tag--green',
                                    'pending' => 'govuk-tag--yellow',
                                    'declined' => 'govuk-tag--red',
                                    'withdrawn' => 'govuk-tag--grey',
                                ][$statusValue] ?? 'govuk-tag--grey';
                            @endphp
                            <strong class="govuk-tag {{ $appStatusTagClass }}">{{ $label('volunteering.status_values', $statusValue) }}</strong>
                            <dl class="govuk-summary-list govuk-!-margin-top-4 govuk-!-margin-bottom-0">
                                @if (!empty($organization['name']))
                                    <div class="govuk-summary-list__row">
                                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha.volunteering.organization') }}</dt>
                                        <dd class="govuk-summary-list__value">{{ $organization['name'] }}</dd>
                                    </div>
                                @endif
                                @if (!empty($opportunity['location']))
                                    <div class="govuk-summary-list__row">
                                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha.volunteering.location') }}</dt>
                                        <dd class="govuk-summary-list__value">{{ $opportunity['location'] }}</dd>
                                    </div>
                                @endif
                                @if (is_array($shift) && !empty($shift['start_time']))
                                    <div class="govuk-summary-list__row">
                                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha.volunteering.shift_label') }}</dt>
                                        <dd class="govuk-summary-list__value">{{ $formatDateTime($shift['start_time']) }}</dd>
                                    </div>
                                @endif
                                @if (!empty($application['created_at']))
                                    <div class="govuk-summary-list__row">
                                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha.volunteering.applied_on') }}</dt>
                                        <dd class="govuk-summary-list__value">{{ $formatDate($application['created_at']) }}</dd>
                                    </div>
                                @endif
                            </dl>
                            @if (in_array($statusValue, ['approved', 'declined'], true) && !empty($application['org_note']))
                                <p class="govuk-body govuk-!-margin-top-3 govuk-!-margin-bottom-1"><strong>{{ __('govuk_alpha.volunteering.organiser_note') }}</strong></p>
                                <div class="govuk-inset-text govuk-!-margin-top-0">{{ $application['org_note'] }}</div>
                            @endif
                            @if ($statusValue === 'pending' && !empty($application['id']))
                                <form method="post" action="{{ route('govuk-alpha.volunteering.applications.withdraw', ['tenantSlug' => $tenantSlug, 'id' => $application['id']]) }}" class="govuk-!-margin-top-3">
                                    @csrf
                                    <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">
                                        {{ __('govuk_alpha.volunteering.withdraw_application') }}<span class="govuk-visually-hidden"> {{ __('govuk_alpha.volunteering.withdraw_application_for', ['title' => $opportunity['title'] ?? '']) }}</span>
                                    </button>
                                </form>
                            @endif
                        </article>
                    @endforeach
                </div>
                @if (!empty($applicationsMeta['has_more']) && !empty($applicationsMeta['cursor']))
                    <nav class="govuk-pagination govuk-pagination--block govuk-!-margin-top-6" aria-label="{{ __('govuk_alpha.volunteering.applications_pagination_label') }}">
                        <div class="govuk-pagination__next">
                            <a class="govuk-link govuk-pagination__link" href="{{ route('govuk-alpha.volunteering.index', array_filter(['tenantSlug' => $tenantSlug, 'tab' => 'applications', 'app_status' => $applicationsStatus ?? null, 'app_cursor' => $applicationsMeta['cursor']])) }}" rel="next">
                                <svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                                    <path d="m8.107-0.0078125-1.4136 1.414 4.2926 4.293h-12.986v2h12.896l-4.1855 3.9766 1.377 1.4492 6.7441-6.4062-6.7246-6.7266z"></path>
                                </svg>
                                <span class="govuk-pagination__link-title">{{ __('govuk_alpha.actions.load_more') }}</span>
                                <span class="govuk-visually-hidden">:</span>
                                <span class="govuk-pagination__link-label">{{ __('govuk_alpha.volunteering.applications_more_label') }}</span>
                            </a>
                        </div>
                    </nav>
                @endif
            @endif
        @elseif (!$requiresAuth && $selectedTab === 'recommended')
            <h2 class="govuk-heading-l">{{ __('govuk_alpha.volunteering.recommended_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha.volunteering.recommended_intro') }}</p>
            @if (empty($recommendedShifts))
                <div class="govuk-inset-text">{{ __('govuk_alpha.volunteering.empty_recommended') }}</div>
            @else
                <div class="nexus-alpha-card-list">
                    @foreach ($recommendedShifts as $shift)
                        <article class="nexus-alpha-card">
                            <h3 class="govuk-heading-m govuk-!-margin-bottom-2">
                                @if (!empty($shift['opportunity_id']))
                                    <a class="govuk-link" href="{{ route('govuk-alpha.volunteering.show', ['tenantSlug' => $tenantSlug, 'id' => $shift['opportunity_id']]) }}">{{ $shift['title'] ?? __('govuk_alpha.volunteering.detail_title') }}</a>
                                @else
                                    {{ $shift['title'] ?? __('govuk_alpha.volunteering.detail_title') }}
                                @endif
                            </h3>
                            <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.volunteering.match_score', ['score' => (int) ($shift['match_score'] ?? 0)]) }}</strong>
                            <dl class="govuk-summary-list govuk-!-margin-top-4 govuk-!-margin-bottom-0">
                                @if (!empty($shift['organization_name']))
                                    <div class="govuk-summary-list__row">
                                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha.volunteering.organization') }}</dt>
                                        <dd class="govuk-summary-list__value">{{ $shift['organization_name'] }}</dd>
                                    </div>
                                @endif
                                @if (!empty($shift['location']))
                                    <div class="govuk-summary-list__row">
                                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha.volunteering.location') }}</dt>
                                        <dd class="govuk-summary-list__value">{{ $shift['location'] }}</dd>
                                    </div>
                                @endif
                                @if (!empty($shift['start_time']))
                                    <div class="govuk-summary-list__row">
                                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha.volunteering.shift_label') }}</dt>
                                        <dd class="govuk-summary-list__value">{{ $formatDateTime($shift['start_time']) }}</dd>
                                    </div>
                                @endif
                                <div class="govuk-summary-list__row">
                                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha.volunteering.spots_remaining_label') }}</dt>
                                    <dd class="govuk-summary-list__value">{{ (int) ($shift['spots_remaining'] ?? 0) }}</dd>
                                </div>
                            </dl>
                        </article>
                    @endforeach
                </div>
            @endif
        @elseif ($selectedTab === 'community_projects')
            <h2 class="govuk-heading-l">{{ __('govuk_alpha.volunteering.community_projects_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha.volunteering.community_projects_intro') }}</p>
            @if (empty($communityProjects))
                <div class="govuk-inset-text">{{ __('govuk_alpha.volunteering.empty_community_projects') }}</div>
            @else
                <div class="nexus-alpha-card-list">
                    @foreach ($communityProjects as $project)
                        @php
                            $cpTitle = trim((string) ($project['title'] ?? '')) ?: __('govuk_alpha.volunteering.community_project_untitled');
                            $cpStatus = trim((string) ($project['status'] ?? ''));
                            $cpDesc = trim((string) ($project['description'] ?? ''));
                            $cpProposer = trim((string) ($project['proposer_name'] ?? ($project['proposer_first_name'] ?? '') . ' ' . ($project['proposer_last_name'] ?? '')));
                            $cpSupporters = (int) ($project['supporter_count'] ?? 0);
                            $cpStatusTag = $cpStatus === 'completed' ? 'govuk-tag--green' : ($cpStatus === 'active' ? 'govuk-tag--turquoise' : 'govuk-tag--blue');
                            // Public projects should only carry whitelisted statuses;
                            // headline() is a defensive fallback for anything else.
                            $cpStatusLabel = match ($cpStatus) {
                                'approved' => __('govuk_alpha.ux.project_status_approved'),
                                'active' => __('govuk_alpha.ux.project_status_active'),
                                'completed' => __('govuk_alpha.ux.project_status_completed'),
                                default => \Illuminate\Support\Str::headline($cpStatus),
                            };
                        @endphp
                        <article class="nexus-alpha-card">
                            <div class="nexus-alpha-module-row">
                                <h3 class="govuk-heading-m govuk-!-margin-bottom-1">{{ $cpTitle }}</h3>
                                @if ($cpStatus !== '')<strong class="govuk-tag {{ $cpStatusTag }}">{{ $cpStatusLabel }}</strong>@endif
                            </div>
                            @if ($cpProposer !== '')
                                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.volunteering.community_project_proposed_by', ['name' => $cpProposer]) }}</p>
                            @endif
                            @if ($cpDesc !== '')
                                <p class="govuk-body govuk-!-margin-bottom-1">{{ \Illuminate\Support\Str::limit(strip_tags($cpDesc), 200) }}</p>
                            @endif
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">{{ __('govuk_alpha.volunteering.community_project_supporters', ['count' => $cpSupporters]) }}</p>
                        </article>
                    @endforeach
                </div>
            @endif
        @else
        <form method="get" action="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-7 govuk-!-margin-top-7">
            <fieldset class="govuk-fieldset" aria-describedby="volunteering-filter-hint">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                    <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.volunteering.filters_title') }}</h2>
                </legend>
                <div id="volunteering-filter-hint" class="govuk-hint">{{ __('govuk_alpha.volunteering.filters_hint') }}</div>
                <div class="govuk-grid-row">
                    <div class="govuk-grid-column-one-half">
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="q">{{ __('govuk_alpha.volunteering.search_label') }}</label>
                            <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha.volunteering.search_hint') }}</div>
                            <input class="govuk-input" id="q" name="q" type="search" value="{{ $filters['search'] ?? '' }}" aria-describedby="q-hint">
                        </div>
                    </div>
                    <div class="govuk-grid-column-one-quarter">
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="category_id">{{ __('govuk_alpha.volunteering.category_label') }}</label>
                            <select class="govuk-select" id="category_id" name="category_id">
                                <option value="">{{ __('govuk_alpha.volunteering.all_categories') }}</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category['id'] }}" @selected((int) ($filters['category_id'] ?? 0) === (int) $category['id'])>{{ $category['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="govuk-grid-column-one-quarter">
                        <div class="govuk-form-group">
                            <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                                <div class="govuk-checkboxes__item">
                                    <input class="govuk-checkboxes__input" id="is_remote" name="is_remote" type="checkbox" value="1" @checked(!empty($filters['is_remote']))>
                                    <label class="govuk-label govuk-checkboxes__label" for="is_remote">{{ __('govuk_alpha.volunteering.remote_label') }}</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="govuk-button-group">
                    <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.actions.search') }}</button>
                    @if ($hasFilters)
                        <a class="govuk-link" href="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.clear_filters') }}</a>
                    @endif
                </div>
            </fieldset>
        </form>

        <h2 class="govuk-heading-l">{{ __('govuk_alpha.volunteering.results_title') }}</h2>
        <p class="govuk-body nexus-alpha-result-count" aria-live="polite">
            {{ trans_choice('govuk_alpha.volunteering.result_count', count($items), ['count' => count($items)]) }}
        </p>

        @if ($error)
            <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                <div role="alert">
                    <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                    <div class="govuk-error-summary__body">
                        <p>{{ __('govuk_alpha.volunteering.error_detail') }}</p>
                    </div>
                </div>
            </div>
        @elseif (empty($items))
            <div class="govuk-inset-text">
                <h2 class="govuk-heading-m">{{ __('govuk_alpha.states.empty_title') }}</h2>
                <p class="govuk-body">{{ __('govuk_alpha.volunteering.empty') }}</p>
                <p class="govuk-body govuk-!-margin-bottom-0">
                    @if ($hasFilters)
                        {{ __('govuk_alpha.vol_clarity.empty_opportunities_filtered_cta') }}
                        <a class="govuk-link" href="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.clear_filters') }}</a>.
                    @else
                        {{ __('govuk_alpha.vol_clarity.empty_opportunities_cta') }}
                    @endif
                </p>
            </div>
        @else
            <div class="nexus-alpha-card-list">
                @foreach ($items as $opportunity)
                    @php
                        $organizationName = $opportunity['organization']['name'] ?? null;
                        $categoryName = $opportunity['category']['name'] ?? $opportunity['category'] ?? null;
                        $start = $formatDate($opportunity['start_date'] ?? null);
                        $end = $formatDate($opportunity['end_date'] ?? null);
                    @endphp
                    <article class="nexus-alpha-card">
                        <h3 class="govuk-heading-m govuk-!-margin-bottom-2">
                            <a class="govuk-link" href="{{ route('govuk-alpha.volunteering.show', ['tenantSlug' => $tenantSlug, 'id' => $opportunity['id']]) }}">{{ $opportunity['title'] }}</a>
                        </h3>
                        @if (!empty($opportunity['is_remote']))
                            <strong class="govuk-tag govuk-tag--turquoise">{{ __('govuk_alpha.volunteering.remote') }}</strong>
                        @endif
                        <dl class="nexus-alpha-inline-list govuk-!-margin-top-2">
                            @if ($organizationName)
                                <div>
                                    <dt>{{ __('govuk_alpha.volunteering.organization') }}</dt>
                                    <dd>{{ $organizationName }}</dd>
                                </div>
                            @endif
                            @if (!empty($opportunity['location']))
                                <div>
                                    <dt>{{ __('govuk_alpha.volunteering.location') }}</dt>
                                    <dd>{{ $opportunity['location'] }}</dd>
                                </div>
                            @endif
                            @if ($categoryName)
                                <div>
                                    <dt>{{ __('govuk_alpha.volunteering.category_label') }}</dt>
                                    <dd>{{ $categoryName }}</dd>
                                </div>
                            @endif
                            @if ($start)
                                <div>
                                    <dt>{{ __('govuk_alpha.volunteering.start_date') }}</dt>
                                    <dd>{{ $start }}</dd>
                                </div>
                            @endif
                            @if ($end)
                                <div>
                                    <dt>{{ __('govuk_alpha.volunteering.end_date') }}</dt>
                                    <dd>{{ $end }}</dd>
                                </div>
                            @endif
                        </dl>
                        @if (!empty($opportunity['description']))
                            <p class="govuk-body govuk-!-margin-top-3">{{ \Illuminate\Support\Str::limit(strip_tags((string) $opportunity['description']), 220) }}</p>
                        @endif
                        <a class="govuk-link govuk-link--no-visited-state" href="{{ route('govuk-alpha.volunteering.show', ['tenantSlug' => $tenantSlug, 'id' => $opportunity['id']]) }}">{{ __('govuk_alpha.actions.view_details') }}</a>
                    </article>
                @endforeach
            </div>
            @if (!empty($meta['has_more']) && !empty($meta['cursor']))
                <nav class="govuk-pagination govuk-pagination--block govuk-!-margin-top-6" aria-label="{{ __('govuk_alpha.volunteering.pagination_label') }}">
                    <div class="govuk-pagination__next">
                        <a class="govuk-link govuk-pagination__link" href="{{ route('govuk-alpha.volunteering.index', array_filter(['tenantSlug' => $tenantSlug, 'q' => $filters['search'] ?? null, 'category_id' => $filters['category_id'] ?? null, 'is_remote' => !empty($filters['is_remote']) ? 1 : null, 'cursor' => $meta['cursor']])) }}" rel="next">
                            <svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                                <path d="m8.107-0.0078125-1.4136 1.414 4.2926 4.293h-12.986v2h12.896l-4.1855 3.9766 1.377 1.4492 6.7441-6.4062-6.7246-6.7266z"></path>
                            </svg>
                            <span class="govuk-pagination__link-title">{{ __('govuk_alpha.actions.load_more') }}</span>
                            <span class="govuk-visually-hidden">:</span>
                            <span class="govuk-pagination__link-label">{{ __('govuk_alpha.volunteering.more_results_label') }}</span>
                        </a>
                    </div>
                </nav>
            @endif
        @endif
        @endif
    @endif
@endsection
