{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
{{--
    Shared pickup-slot create/edit form body (no JS). Inputs mirror the React
    seller page: start/end datetime-local, capacity, recurring + active toggles.
    Expects: $formAction, $slot (array|null), $submitLabel, $tenantSlug.
--}}
@php
    $slot = $slot ?? null;
    $oldVal = function (string $key, $fallback = '') use ($slot) {
        $current = old($key);
        if ($current !== null) {
            return $current;
        }
        if (is_array($slot) && array_key_exists($key, $slot)) {
            return $slot[$key];
        }
        return $fallback;
    };
    $formErrors = session('commercePickupSlotErrors', []);
    $isActive = (bool) $oldVal('is_active', true);
    $isRecurring = (bool) $oldVal('is_recurring', false);
@endphp

@if (!empty($formErrors))
    <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
        <div role="alert">
            <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_commerce.common.error_title') }}</h2>
            <div class="govuk-error-summary__body">
                <ul class="govuk-list govuk-error-summary__list">
                    @foreach ($formErrors as $msg)
                        <li><a href="#slot_start">{{ $msg }}</a></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif

<form method="post" action="{{ $formAction }}" novalidate>
    @csrf

    <fieldset class="govuk-fieldset">
        <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
            <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha_commerce.slots.section_times') }}</h2>
        </legend>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="slot_start">{{ __('govuk_alpha_commerce.slots.start_label') }}</label>
            <div id="slot_start-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.slots.start_hint') }}</div>
            <input class="govuk-input govuk-input--width-20" id="slot_start" name="slot_start" type="datetime-local" value="{{ $oldVal('slot_start', '') }}" aria-describedby="slot_start-hint">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="slot_end">{{ __('govuk_alpha_commerce.slots.end_label') }}</label>
            <div id="slot_end-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.slots.end_hint') }}</div>
            <input class="govuk-input govuk-input--width-20" id="slot_end" name="slot_end" type="datetime-local" value="{{ $oldVal('slot_end', '') }}" aria-describedby="slot_end-hint">
        </div>
    </fieldset>

    <fieldset class="govuk-fieldset govuk-!-margin-top-4">
        <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
            <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha_commerce.slots.section_options') }}</h2>
        </legend>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="capacity">{{ __('govuk_alpha_commerce.slots.capacity_label') }}</label>
            <div id="capacity-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.slots.capacity_hint') }}</div>
            <input class="govuk-input govuk-input--width-5" id="capacity" name="capacity" type="number" min="1" max="1000" value="{{ $oldVal('capacity', 5) }}" aria-describedby="capacity-hint">
        </div>

        <div class="govuk-form-group">
            <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                <div class="govuk-checkboxes__item">
                    <input class="govuk-checkboxes__input" id="is_recurring" name="is_recurring" type="checkbox" value="1" aria-describedby="is_recurring-hint" @checked($isRecurring)>
                    <label class="govuk-label govuk-checkboxes__label" for="is_recurring">{{ __('govuk_alpha_commerce.slots.recurring_label') }}</label>
                    <div id="is_recurring-hint" class="govuk-hint govuk-checkboxes__hint">{{ __('govuk_alpha_commerce.slots.recurring_hint') }}</div>
                </div>
                <div class="govuk-checkboxes__item">
                    <input class="govuk-checkboxes__input" id="is_active" name="is_active" type="checkbox" value="1" aria-describedby="is_active-hint" @checked($isActive)>
                    <label class="govuk-label govuk-checkboxes__label" for="is_active">{{ __('govuk_alpha_commerce.slots.active_label') }}</label>
                    <div id="is_active-hint" class="govuk-hint govuk-checkboxes__hint">{{ __('govuk_alpha_commerce.slots.active_hint') }}</div>
                </div>
            </div>
        </div>
    </fieldset>

    <div class="govuk-button-group govuk-!-margin-top-4">
        <button class="govuk-button" data-module="govuk-button">{{ $submitLabel }}</button>
        <a class="govuk-link" href="{{ route('govuk-alpha.marketplace.slots', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_commerce.common.cancel') }}</a>
    </div>
</form>
