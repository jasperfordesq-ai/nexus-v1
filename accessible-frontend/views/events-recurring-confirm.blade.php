{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $impact = is_array($preview['impact'] ?? null) ? $preview['impact'] : [];
        $blockingCount = count($impact['blocking_conflicts'] ?? []);
        $customizedCount = count($impact['customized_exception_conflicts'] ?? []);
    @endphp
    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.recurring.edit', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">{{ __('govuk_alpha_events.recurring_edit.back_to_edit') }}</a>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-l">{{ $event['title'] ?? __('govuk_alpha_events.recurring_edit.caption') }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha_events.recurring_edit.confirm_title') }}</h1>
            <p class="govuk-body-l">{{ __('govuk_alpha_events.recurring_edit.confirm_intro') }}</p>

            @if ($blockingCount > 0 || !($preview['can_commit'] ?? false))
                <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_events.recurring_edit.confirm_blocked_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <p class="govuk-body">{{ trans_choice('govuk_alpha_events.recurring_edit.confirm_blocked', $blockingCount, ['count' => $blockingCount]) }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <h2 class="govuk-heading-l">{{ __('govuk_alpha_events.recurring_edit.impact_heading') }}</h2>
            <dl class="govuk-summary-list">
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_events.recurring_edit.impact_occurrences') }}</dt>
                    <dd class="govuk-summary-list__value">{{ (int) ($impact['changed_count'] ?? 0) }}</dd>
                </div>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_events.recurring_edit.impact_registrations') }}</dt>
                    <dd class="govuk-summary-list__value">{{ (int) ($impact['registrations_count'] ?? 0) }}</dd>
                </div>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_events.recurring_edit.impact_waitlist') }}</dt>
                    <dd class="govuk-summary-list__value">{{ (int) ($impact['waitlist_count'] ?? 0) }}</dd>
                </div>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_events.recurring_edit.impact_tickets') }}</dt>
                    <dd class="govuk-summary-list__value">{{ (int) ($impact['ticket_count'] ?? 0) }}</dd>
                </div>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_events.recurring_edit.impact_reminders') }}</dt>
                    <dd class="govuk-summary-list__value">{{ (int) ($impact['reminder_count'] ?? 0) }}</dd>
                </div>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_events.recurring_edit.impact_recipients') }}</dt>
                    <dd class="govuk-summary-list__value">{{ (int) ($impact['unique_recipient_count'] ?? 0) }}</dd>
                </div>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">{{ __('govuk_alpha_events.recurring_edit.impact_customized') }}</dt>
                    <dd class="govuk-summary-list__value">{{ $customizedCount }}</dd>
                </div>
            </dl>

            <p class="govuk-body">{{ __('govuk_alpha_events.recurring_edit.confirm_privacy') }}</p>

            @if (($preview['can_commit'] ?? false) && $blockingCount === 0)
                <form method="post" action="{{ route('govuk-alpha.events.recurring.commit', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}" autocomplete="off">
                    @csrf
                    <input type="hidden" name="preview_token" value="{{ $preview['preview_token'] }}">
                    <input type="hidden" name="patch_json" value="{{ $patchJson }}">
                    <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                    <button class="govuk-button" data-module="govuk-button" type="submit">{{ __('govuk_alpha_events.recurring_edit.confirm_submit') }}</button>
                </form>
            @endif
        </div>
    </div>
@endsection
