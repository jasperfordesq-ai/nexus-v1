{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $gTitle = trim((string) ($goal['title'] ?? '')) ?: __('govuk_alpha.goals.title');
        $cur = (float) ($goal['current_value'] ?? 0);
        $tgt = (float) ($goal['target_value'] ?? 0);
        $pct = $tgt > 0 ? (int) round(min(100, ($cur / $tgt) * 100)) : 0;
        $done = in_array($goal['status'] ?? 'active', ['completed', 'achieved'], true);
        $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    @endphp

    @if ($status === 'goal-updated')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-live="polite" aria-labelledby="gd-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="gd-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.goals.states.goal-updated') }}</p></div>
        </div>
    @elseif (in_array($status, ['goal-failed', 'goal-invalid', 'goal-completed'], true))
        <div class="govuk-notification-banner @if ($status === 'goal-completed') govuk-notification-banner--success @endif" data-module="govuk-notification-banner" role="region" aria-labelledby="gd-status2">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="gd-status2">{{ $status === 'goal-completed' ? __('govuk_alpha.states.success_title') : __('govuk_alpha.states.error_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.goals.states.' . $status) }}</p></div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ __('govuk_alpha.goals.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <div class="nexus-alpha-module-row">
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">{{ $gTitle }}</h1>
        <strong class="govuk-tag {{ $done ? 'govuk-tag--green' : 'govuk-tag--blue' }}">{{ $done ? __('govuk_alpha.goals.status_completed') : __('govuk_alpha.goals.status_active') }}</strong>
    </div>

    @if (trim((string) ($goal['description'] ?? '')) !== '')
        <p class="govuk-body-l">{{ $goal['description'] }}</p>
    @endif

    <p class="govuk-body govuk-!-margin-bottom-1">{{ __('govuk_alpha.goals.progress_label', ['current' => $num($cur), 'target' => $num($tgt)]) }}</p>
    <progress max="100" value="{{ $pct }}" aria-label="{{ $pct }}%">{{ $pct }}%</progress>

    @if ($isOwner && !$done)
        <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.goals.update_title') }}</h2>
        <form method="post" action="{{ route('govuk-alpha.goals.progress', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}" class="govuk-!-margin-bottom-4">
            @csrf
            <div class="govuk-form-group">
                <label class="govuk-label" for="increment">{{ __('govuk_alpha.goals.increment_label') }}</label>
                <input class="govuk-input govuk-input--width-5" id="increment" name="increment" type="number" min="0.25" step="0.25" inputmode="decimal" required>
            </div>
            <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.goals.increment_button') }}</button>
        </form>
        <form method="post" action="{{ route('govuk-alpha.goals.complete', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}">
            @csrf
            <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.goals.mark_complete') }}</button>
        </form>
    @endif
@endsection
