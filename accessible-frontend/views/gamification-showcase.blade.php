{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.achievements', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_gamification.common.back_to_achievements') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_gamification.showcase.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_gamification.showcase.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_gamification.showcase.description') }}</p>

    @include('accessible-frontend::partials.gamification-nav', ['gamificationNavGroup' => 'achievements', 'gamificationActiveTab' => 'showcase'])

    @if ($status === 'showcase-updated')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="showcase-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="showcase-status">{{ __('govuk_alpha_gamification.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_gamification.showcase.states.showcase-updated') }}</p></div>
        </div>
    @elseif (in_array($status, ['showcase-failed', 'showcase-too-many', 'showcase-not-owned'], true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_gamification.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-error-summary__list">
                        <li><a href="#showcase-badges">{{ __('govuk_alpha_gamification.showcase.states.' . $status) }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    @if (empty($earnedBadges))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_gamification.showcase.empty') }}</p></div>
    @else
        <form method="post" action="{{ route('govuk-alpha.gamification.showcase.update', ['tenantSlug' => $tenantSlug]) }}">
            @csrf
            <fieldset class="govuk-fieldset" id="showcase-badges" aria-describedby="showcase-hint">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">{{ __('govuk_alpha_gamification.showcase.legend') }}</legend>
                <div id="showcase-hint" class="govuk-hint">{{ __('govuk_alpha_gamification.showcase.hint') }}</div>
                <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                    @foreach ($earnedBadges as $badge)
                        @php
                            $bKey = trim((string) ($badge['badge_key'] ?? ''));
                            $bName = trim((string) ($badge['name'] ?? '')) ?: $bKey;
                            $checked = in_array($bKey, $showcasedKeys, true);
                        @endphp
                        @if ($bKey !== '')
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="showcase-{{ $loop->index }}" name="badge_keys[]" type="checkbox" value="{{ $bKey }}" @checked($checked)>
                                <label class="govuk-label govuk-checkboxes__label" for="showcase-{{ $loop->index }}">
                                    {{ $bName }}
                                    @if (!empty($badge['description']))<span class="govuk-hint">{{ $badge['description'] }}</span>@endif
                                </label>
                            </div>
                        @endif
                    @endforeach
                </div>
            </fieldset>
            <button class="govuk-button govuk-!-margin-top-4" data-module="govuk-button">{{ __('govuk_alpha_gamification.showcase.save_button') }}</button>
        </form>
    @endif
@endsection
