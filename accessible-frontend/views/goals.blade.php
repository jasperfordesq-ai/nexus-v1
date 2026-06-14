{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <span class="govuk-caption-xl">{{ __('govuk_alpha.goals.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.goals.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.goals.description') }}</p>

    @if (in_array($status, ['goal-created', 'goal-completed'], true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-live="polite" aria-labelledby="goal-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="goal-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.goals.states.' . $status) }}</p></div>
        </div>
    @elseif (in_array($status, ['goal-failed', 'goal-invalid'], true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert"><h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li><a href="#title">{{ __('govuk_alpha.goals.states.' . $status) }}</a></li></ul></div></div>
        </div>
    @endif

    @if (empty($goals))
        <p class="govuk-inset-text">{{ __('govuk_alpha.goals.empty') }}</p>
    @else
        <div class="nexus-alpha-card-list govuk-!-margin-bottom-8">
            @foreach ($goals as $g)
                @php
                    $gTitle = trim((string) ($g['title'] ?? '')) ?: __('govuk_alpha.goals.title');
                    $cur = (float) ($g['current_value'] ?? 0);
                    $tgt = (float) ($g['target_value'] ?? 0);
                    $pct = $tgt > 0 ? (int) round(min(100, ($cur / $tgt) * 100)) : 0;
                    $done = in_array($g['status'] ?? 'active', ['completed', 'achieved'], true);
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1"><a class="govuk-link" href="{{ route('govuk-alpha.goals.show', ['tenantSlug' => $tenantSlug, 'id' => $g['id']]) }}">{{ $gTitle }}</a></h2>
                        <strong class="govuk-tag {{ $done ? 'govuk-tag--green' : 'govuk-tag--blue' }}">{{ $done ? __('govuk_alpha.goals.status_completed') : __('govuk_alpha.goals.status_active') }}</strong>
                    </div>
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha.goals.progress_label', ['current' => rtrim(rtrim(number_format($cur, 2), '0'), '.'), 'target' => rtrim(rtrim(number_format($tgt, 2), '0'), '.')]) }}</p>
                    <progress max="100" value="{{ $pct }}" aria-label="{{ $pct }}%">{{ $pct }}%</progress>
                </article>
            @endforeach
        </div>
    @endif

    <h2 class="govuk-heading-l">{{ __('govuk_alpha.goals.create_title') }}</h2>
    <form method="post" action="{{ route('govuk-alpha.goals.store', ['tenantSlug' => $tenantSlug]) }}" class="govuk-grid-row">
        @csrf
        <div class="govuk-grid-column-two-thirds">
            <div class="govuk-form-group">
                <label class="govuk-label" for="title">{{ __('govuk_alpha.goals.title_label') }}</label>
                <input class="govuk-input" id="title" name="title" type="text" maxlength="255" required>
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="target_value">{{ __('govuk_alpha.goals.target_label') }}</label>
                <div id="tv-hint" class="govuk-hint">{{ __('govuk_alpha.goals.target_hint') }}</div>
                <input class="govuk-input govuk-input--width-5" id="target_value" name="target_value" type="number" min="0.25" step="0.25" inputmode="decimal" required aria-describedby="tv-hint">
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="description">{{ __('govuk_alpha.goals.description_label') }}</label>
                <textarea class="govuk-textarea" id="description" name="description" rows="2" maxlength="1000"></textarea>
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="deadline">{{ __('govuk_alpha.goals.deadline_label') }}</label>
                <input class="govuk-input govuk-input--width-10" id="deadline" name="deadline" type="date">
            </div>
            <div class="govuk-checkboxes govuk-checkboxes--small govuk-form-group" data-module="govuk-checkboxes">
                <div class="govuk-checkboxes__item">
                    <input class="govuk-checkboxes__input" id="is_public" name="is_public" type="checkbox" value="1">
                    <label class="govuk-label govuk-checkboxes__label" for="is_public">{{ __('govuk_alpha.goals.public_label') }}</label>
                </div>
            </div>
            <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.goals.create_button') }}</button>
        </div>
    </form>
@endsection
