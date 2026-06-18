{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $orgId = (int) ($orgId ?? 0);
        $orgName = (string) ($orgName ?? '');
        $orgStatus = (string) ($orgStatus ?? 'pending');
        $stats = $stats ?? [];
        $walletBalance = (float) ($walletBalance ?? 0);
        $autoPayEnabled = (bool) ($autoPayEnabled ?? false);
        $isApproved = in_array($orgStatus, ['approved', 'active'], true);
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.volunteering.my-organisations', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_volunteering.shared.back_to_my_organisations') }}</a>

    <span class="govuk-caption-l">{{ $orgName }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_volunteering.org_dashboard.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_volunteering.org_dashboard.description') }}</p>

    @unless ($isApproved)
        <div class="govuk-warning-text">
            <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
            <strong class="govuk-warning-text__text">
                <span class="govuk-visually-hidden">{{ __('govuk_alpha_volunteering.shared.error_title') }}</span>
                {{ __('govuk_alpha_volunteering.org_dashboard.awaiting_approval') }}
            </strong>
        </div>
    @endunless

    {{-- Stats --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha_volunteering.org_dashboard.stats_title') }}</h2>
    <dl class="nexus-alpha-stat-grid">
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_volunteering.org_dashboard.active_opportunities') }}</dt>
            <dd>{{ (int) ($stats['active_opportunities'] ?? 0) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_volunteering.org_dashboard.pending_applications') }}</dt>
            <dd>{{ (int) ($stats['pending_applications'] ?? 0) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_volunteering.org_dashboard.pending_hours') }}</dt>
            <dd>{{ (int) ($stats['pending_hours'] ?? 0) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_volunteering.org_dashboard.total_volunteers') }}</dt>
            <dd>{{ (int) ($stats['total_volunteers'] ?? 0) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_volunteering.org_dashboard.total_approved_hours') }}</dt>
            <dd>{{ number_format((float) ($stats['total_approved_hours'] ?? 0), 1) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_volunteering.org_dashboard.wallet_balance') }}</dt>
            <dd>{{ number_format($walletBalance, 1) }}</dd>
        </div>
    </dl>
    <p class="govuk-body">
        <strong class="govuk-tag {{ $autoPayEnabled ? 'govuk-tag--green' : 'govuk-tag--grey' }}">
            {{ $autoPayEnabled ? __('govuk_alpha_volunteering.org_dashboard.auto_pay_on') : __('govuk_alpha_volunteering.org_dashboard.auto_pay_off') }}
        </strong>
    </p>

    {{-- Quick actions --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha_volunteering.org_dashboard.quick_actions_title') }}</h2>
    <ul class="govuk-list">
        <li>
            <a class="govuk-link" href="{{ route('govuk-alpha.volunteering.org.manage', ['tenantSlug' => $tenantSlug, 'id' => $orgId]) }}">{{ __('govuk_alpha_volunteering.org_dashboard.action_applications') }}</a>
        </li>
        <li>
            <a class="govuk-link" href="{{ route('govuk-alpha.volunteering.opportunities.create', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_volunteering.org_dashboard.action_create') }}</a>
        </li>
        <li>
            <a class="govuk-link" href="{{ route('govuk-alpha.volunteering.org.settings', ['tenantSlug' => $tenantSlug, 'id' => $orgId]) }}">{{ __('govuk_alpha_volunteering.org_dashboard.action_settings') }}</a>
        </li>
        <li>
            <a class="govuk-link" href="{{ route('govuk-alpha.volunteering.org.wallet', ['tenantSlug' => $tenantSlug, 'id' => $orgId]) }}">{{ __('govuk_alpha_volunteering.org_dashboard.action_wallet') }}</a>
        </li>
    </ul>
@endsection
