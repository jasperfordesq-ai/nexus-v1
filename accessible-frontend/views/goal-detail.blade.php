{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.goals.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.goals.back_to_goals') }}</a>

    @php
        $gTitle = trim((string) ($goal['title'] ?? '')) ?: __('govuk_alpha.goals.title');
        $cur = (float) ($goal['current_value'] ?? 0);
        $tgt = (float) ($goal['target_value'] ?? 0);
        $pct = $tgt > 0 ? (int) round(min(100, ($cur / $tgt) * 100)) : 0;
        $done = in_array($goal['status'] ?? 'active', ['completed', 'achieved'], true);
        $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
        $historyTypes = ['created', 'progress_update', 'checkin', 'milestone', 'buddy_joined', 'buddy_action', 'completed'];
        $successStates = ['goal-updated', 'goal-edited', 'goal-completed', 'buddy-joined'];
    @endphp

    @if (in_array($status, $successStates, true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="gd-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="gd-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.goals.states.' . $status) }}</p></div>
        </div>
    @elseif (in_array($status, ['goal-failed', 'goal-invalid', 'buddy-failed', 'buddy-safeguarding-restricted', 'buddy-safeguarding-unavailable'], true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        <li><a href="{{ str_starts_with((string) $status, 'buddy-') ? '#buddy-section' : '#increment' }}">{{ match ($status) {
                            'buddy-safeguarding-restricted' => __('safeguarding.errors.interaction_not_allowed'),
                            'buddy-safeguarding-unavailable' => __('safeguarding.errors.policy_unavailable'),
                            default => __('govuk_alpha.goals.states.' . $status),
                        } }}</a></li>
                    </ul>
                </div>
            </div>
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

    <p class="nexus-alpha-actions govuk-!-margin-top-4">
        @if ($isOwner)
            <a class="govuk-link" href="{{ route('govuk-alpha.goals.edit', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}">{{ __('govuk_alpha.goals.edit_goal') }}</a>
        @endif
        @if ($isOwner || $isBuddy)
            <a class="govuk-link" href="{{ route('govuk-alpha.goals.insights', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}">{{ __('govuk_alpha_goals.nav.insights') }}</a>
        @endif
        <a class="govuk-link" href="{{ route('govuk-alpha.goals.social', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}">{{ __('govuk_alpha_goals.nav.social') }}</a>
        <a class="govuk-link" href="{{ route('govuk-alpha.goals.history', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}">{{ __('govuk_alpha_goals.nav.history') }}</a>
    </p>

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

    {{-- Buddy system --}}
    @if ($isBuddy)
        <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.goals.buddy_section_title') }}</h2>
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.goals.buddy_you_are_buddy') }}</p></div>
    @elseif (!empty($canBecomeBuddy))
        <h2 class="govuk-heading-l govuk-!-margin-top-7" id="buddy-section">{{ __('govuk_alpha.goals.become_buddy_title') }}</h2>
        <p class="govuk-body">{{ __('govuk_alpha.goals.become_buddy_intro') }}</p>
        <form method="post" action="{{ route('govuk-alpha.goals.buddy', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}">
            @csrf
            <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button" type="submit">{{ __('govuk_alpha.goals.become_buddy_button') }}</button>
        </form>
    @elseif ($isOwner && ($goal['mentor_id'] ?? null) !== null)
        <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.goals.buddy_section_title') }}</h2>
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.goals.buddy_has_buddy') }}</p></div>
    @endif

    {{-- Buddy updates (notes left by the buddy, visible to owner + buddy) --}}
    @if (($isOwner || $isBuddy) && !empty($buddyNotes))
        <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.goals.buddy_notes_title') }}</h2>
        <ul class="govuk-list nexus-alpha-card-list">
            @foreach ($buddyNotes as $note)
                <li class="nexus-alpha-card">
                    <p class="govuk-body govuk-!-margin-bottom-1">{{ $note['message'] ?? '' }}</p>
                    <p class="govuk-body-s nexus-alpha-meta">
                        {{ trim((string) ($note['buddy_name'] ?? '')) ?: __('govuk_alpha.goals.a_member') }}
                        @if (!empty($note['created_at']))
                            · {{ \Illuminate\Support\Carbon::parse($note['created_at'])->isoFormat('D MMM YYYY') }}
                        @endif
                    </p>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- Progress history timeline --}}
    @if ($isOwner || $isBuddy)
        <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.goals.history_title') }}</h2>
        @if (empty($goalHistory))
            <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.goals.history_empty') }}</p></div>
        @else
            <dl class="govuk-summary-list">
                @foreach ($goalHistory as $event)
                    @php
                        $type = (string) ($event['type'] ?? $event['event_type'] ?? '');
                        $typeKey = in_array($type, $historyTypes, true) ? $type : 'progress_update';
                        $when = $event['created_at'] ?? null;
                    @endphp
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">
                            {{ __('govuk_alpha.goals.history_type_' . $typeKey) }}
                        </dt>
                        <dd class="govuk-summary-list__value">
                            {{ trim((string) ($event['description'] ?? '')) }}
                            @if ($when)
                                <span class="govuk-!-display-block govuk-body-s nexus-alpha-meta">{{ \Illuminate\Support\Carbon::parse($when)->isoFormat('D MMM YYYY, HH:mm') }}</span>
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endif
    @endif
@endsection
