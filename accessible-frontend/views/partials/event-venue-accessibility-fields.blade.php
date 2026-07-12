{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@php
    $venueAccess = is_array($venueAccess ?? null) ? $venueAccess : [];
    $accessStatus = static function (string $field) use ($venueAccess): string {
        $oldValue = old($field);
        if (is_string($oldValue) && in_array($oldValue, ['yes', 'no', 'unknown'], true)) {
            return $oldValue;
        }
        $key = match ($field) {
            'accessibility_step_free' => 'step_free_access',
            'accessibility_toilet' => 'accessible_toilet',
            'accessibility_hearing_loop' => 'hearing_loop',
            'accessibility_quiet_space' => 'quiet_space',
            'accessibility_seating' => 'seating_available',
            'accessibility_parking' => 'accessible_parking',
            default => '',
        };
        $value = $key !== '' ? ($venueAccess[$key] ?? null) : null;
        return $value === true || $value === 1 ? 'yes' : ($value === false || $value === 0 ? 'no' : 'unknown');
    };
    $accessFields = [
        'accessibility_step_free' => 'step_free_access',
        'accessibility_toilet' => 'accessible_toilet',
        'accessibility_hearing_loop' => 'hearing_loop',
        'accessibility_quiet_space' => 'quiet_space',
        'accessibility_seating' => 'seating_available',
        'accessibility_parking' => 'accessible_parking',
    ];
@endphp

<fieldset class="govuk-fieldset govuk-!-margin-top-7" aria-describedby="venue-accessibility-hint">
    <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
        <h2 class="govuk-fieldset__heading">{{ __('event_accessibility.form.title') }}</h2>
    </legend>
    <div id="venue-accessibility-hint" class="govuk-hint">{{ __('event_accessibility.form.hint') }}</div>

    <div class="govuk-grid-row">
        @foreach ($accessFields as $field => $key)
            <div class="govuk-grid-column-one-half">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="{{ $field }}">{{ __('event_accessibility.features.' . $key) }}</label>
                    <select class="govuk-select" id="{{ $field }}" name="{{ $field }}">
                        @foreach (['unknown', 'yes', 'no'] as $status)
                            <option value="{{ $status }}" @selected($accessStatus($field) === $status)>{{ __('event_accessibility.status.' . $status) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endforeach
    </div>

    <div class="govuk-form-group{{ $errors->has('venue_accessibility.parking_details') ? ' govuk-form-group--error' : '' }}">
        <label class="govuk-label" for="accessibility_parking_details">{{ __('event_accessibility.form.parking_details') }}</label>
        <div id="accessibility-parking-details-hint" class="govuk-hint">{{ __('event_accessibility.form.parking_details_hint') }}</div>
        @error('venue_accessibility.parking_details')
            <p id="accessibility-parking-details-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $message }}</p>
        @enderror
        <textarea class="govuk-textarea{{ $errors->has('venue_accessibility.parking_details') ? ' govuk-textarea--error' : '' }}" id="accessibility_parking_details" name="accessibility_parking_details" rows="3" maxlength="1000" aria-describedby="accessibility-parking-details-hint{{ $errors->has('venue_accessibility.parking_details') ? ' accessibility-parking-details-error' : '' }}">{{ old('accessibility_parking_details', $venueAccess['parking_details'] ?? '') }}</textarea>
    </div>

    <div class="govuk-form-group{{ $errors->has('venue_accessibility.transit_details') ? ' govuk-form-group--error' : '' }}">
        <label class="govuk-label" for="accessibility_transit_details">{{ __('event_accessibility.form.transit_details') }}</label>
        <div id="accessibility-transit-details-hint" class="govuk-hint">{{ __('event_accessibility.form.transit_details_hint') }}</div>
        @error('venue_accessibility.transit_details')
            <p id="accessibility-transit-details-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $message }}</p>
        @enderror
        <textarea class="govuk-textarea{{ $errors->has('venue_accessibility.transit_details') ? ' govuk-textarea--error' : '' }}" id="accessibility_transit_details" name="accessibility_transit_details" rows="3" maxlength="1000" aria-describedby="accessibility-transit-details-hint{{ $errors->has('venue_accessibility.transit_details') ? ' accessibility-transit-details-error' : '' }}">{{ old('accessibility_transit_details', $venueAccess['transit_details'] ?? '') }}</textarea>
    </div>

    <div class="govuk-form-group{{ $errors->has('venue_accessibility.assistance_contact') ? ' govuk-form-group--error' : '' }}">
        <label class="govuk-label" for="accessibility_assistance_contact">{{ __('event_accessibility.form.assistance_contact') }}</label>
        <div id="accessibility-assistance-contact-hint" class="govuk-hint">{{ __('event_accessibility.form.assistance_contact_hint') }}</div>
        @error('venue_accessibility.assistance_contact')
            <p id="accessibility-assistance-contact-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $message }}</p>
        @enderror
        <input class="govuk-input{{ $errors->has('venue_accessibility.assistance_contact') ? ' govuk-input--error' : '' }}" id="accessibility_assistance_contact" name="accessibility_assistance_contact" type="text" maxlength="500" value="{{ old('accessibility_assistance_contact', $venueAccess['assistance_contact'] ?? '') }}" aria-describedby="accessibility-assistance-contact-hint{{ $errors->has('venue_accessibility.assistance_contact') ? ' accessibility-assistance-contact-error' : '' }}">
    </div>

    <div class="govuk-form-group{{ $errors->has('venue_accessibility.notes') ? ' govuk-form-group--error' : '' }}">
        <label class="govuk-label" for="accessibility_notes">{{ __('event_accessibility.form.notes') }}</label>
        <div id="accessibility-notes-hint" class="govuk-hint">{{ __('event_accessibility.form.notes_hint') }}</div>
        @error('venue_accessibility.notes')
            <p id="accessibility-notes-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $message }}</p>
        @enderror
        <textarea class="govuk-textarea{{ $errors->has('venue_accessibility.notes') ? ' govuk-textarea--error' : '' }}" id="accessibility_notes" name="accessibility_notes" rows="5" maxlength="4000" aria-describedby="accessibility-notes-hint{{ $errors->has('venue_accessibility.notes') ? ' accessibility-notes-error' : '' }}">{{ old('accessibility_notes', $venueAccess['notes'] ?? '') }}</textarea>
    </div>

    <p class="govuk-body-s">{{ __('event_accessibility.form.privacy_note') }}</p>
</fieldset>
