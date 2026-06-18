{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $opp = isset($opportunity) && is_array($opportunity) ? $opportunity : [];
        $oppId = (int) ($opportunityId ?? ($opp['id'] ?? 0));
        $oppTitle = trim((string) ($opp['title'] ?? '')) ?: __('govuk_alpha_organisations.apply.title');
        $orgName = trim((string) ($opp['org_name'] ?? ($opp['organization']['name'] ?? '')));
        $orgId = (int) ($orgId ?? 0);
        $applied = ! empty($hasApplied);
    @endphp

    @if ($orgId > 0)
        <a class="govuk-back-link" href="{{ route('govuk-alpha.organisations.show', ['tenantSlug' => $tenantSlug, 'id' => $orgId]) }}">{{ $orgName !== '' ? $orgName : __('govuk_alpha_organisations.common.back_to_organisations') }}</a>
    @else
        <a class="govuk-back-link" href="{{ route('govuk-alpha.volunteering.show', ['tenantSlug' => $tenantSlug, 'id' => $oppId]) }}">{{ __('govuk_alpha_organisations.apply.view_opportunity') }}</a>
    @endif

    <span class="govuk-caption-xl">{{ __('govuk_alpha_organisations.common.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_organisations.apply.heading') }}</h1>

    <dl class="govuk-summary-list govuk-!-margin-bottom-6">
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_organisations.apply.opportunity_label') }}</dt>
            <dd class="govuk-summary-list__value">
                <a class="govuk-link" href="{{ route('govuk-alpha.volunteering.show', ['tenantSlug' => $tenantSlug, 'id' => $oppId]) }}">{{ $oppTitle }}</a>
            </dd>
        </div>
        @if ($orgName !== '')
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_organisations.apply.organisation_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $orgName }}</dd>
            </div>
        @endif
    </dl>

    @if ($applied)
        <div class="govuk-inset-text">
            <h2 class="govuk-heading-m govuk-!-margin-bottom-2">{{ __('govuk_alpha_organisations.apply.already_applied_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha_organisations.apply.already_applied_body') }}</p>
            <a class="govuk-link" href="{{ route('govuk-alpha.volunteering.show', ['tenantSlug' => $tenantSlug, 'id' => $oppId]) }}">{{ __('govuk_alpha_organisations.apply.view_opportunity') }}</a>
        </div>
    @else
        {{-- Posts to the EXISTING volunteering.apply.store route — no new apply logic. --}}
        <form method="post" action="{{ route('govuk-alpha.volunteering.apply.store', ['tenantSlug' => $tenantSlug, 'id' => $oppId]) }}">
            @csrf
            <div class="govuk-form-group">
                <label class="govuk-label" for="message">{{ __('govuk_alpha_organisations.apply.message_label') }}</label>
                <div id="message-hint" class="govuk-hint">{{ __('govuk_alpha_organisations.apply.message_hint') }}</div>
                <textarea class="govuk-textarea" id="message" name="message" rows="5" maxlength="2000" aria-describedby="message-hint">{{ old('message') }}</textarea>
            </div>

            <div class="govuk-inset-text">
                <p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha_organisations.apply.notice') }}</p>
            </div>

            <div class="govuk-button-group">
                <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_organisations.apply.submit') }}</button>
                <a class="govuk-link" href="{{ route('govuk-alpha.volunteering.show', ['tenantSlug' => $tenantSlug, 'id' => $oppId]) }}">{{ __('govuk_alpha_organisations.apply.cancel') }}</a>
            </div>
        </form>
    @endif
@endsection
