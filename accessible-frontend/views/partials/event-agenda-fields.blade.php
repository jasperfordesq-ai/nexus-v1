{{-- Copyright (c) 2024-2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@php
    $types = ['session', 'keynote', 'workshop', 'panel', 'break', 'networking', 'other'];
    $visibilities = ['public', 'registered', 'staff'];
    $value = static fn (string $name, $default = null) => $useOld ? old($name, $default) : $default;
    $speakerMemberIds = $useOld ? (array) old('speaker_member_id', []) : array_column($speakerRows, 'member_id');
    $speakerNames = $useOld ? (array) old('speaker_name', []) : array_column($speakerRows, 'display_name');
    $speakerRoles = $useOld ? (array) old('speaker_role', []) : array_column($speakerRows, 'role');
    $speakerCount = max(5, count($speakerRows), count($speakerNames));
    $resourceRows = $resourceRows ?? [];
    $resourceTypes = $useOld ? (array) old('resource_type', []) : array_column($resourceRows, 'type');
    $resourceTitles = $useOld ? (array) old('resource_title', []) : array_column($resourceRows, 'title');
    $resourceUrls = $useOld ? (array) old('resource_url', []) : array_column($resourceRows, 'url');
    $resourceVisibilities = $useOld ? (array) old('resource_visibility', []) : array_column($resourceRows, 'visibility');
    $resourceCount = max(3, count($resourceRows), count($resourceTypes));
    $agendaResourceTypes = ['link', 'document', 'slides', 'download', 'stream', 'recording'];
@endphp

<div class="govuk-form-group">
    <label class="govuk-label govuk-label--s" for="{{ $formKey }}-title">{{ __('govuk_alpha.events.agenda.fields.title') }}</label>
    <input class="govuk-input" id="{{ $formKey }}-title" name="title" type="text" maxlength="255" value="{{ $value('title', $values['title'] ?? '') }}" required>
</div>

<div class="govuk-form-group">
    <label class="govuk-label govuk-label--s" for="{{ $formKey }}-capacity">{{ __('event_agenda.capacity_label') }}</label>
    <div id="{{ $formKey }}-capacity-hint" class="govuk-hint">{{ __('event_agenda.capacity_hint') }}</div>
    <input class="govuk-input govuk-input--width-5" id="{{ $formKey }}-capacity" name="capacity" type="number" min="1" max="100000" inputmode="numeric" aria-describedby="{{ $formKey }}-capacity-hint" value="{{ $value('capacity', $values['capacity']['limit'] ?? '') }}">
</div>

<div class="govuk-form-group">
    <label class="govuk-label govuk-label--s" for="{{ $formKey }}-description">{{ __('govuk_alpha.events.agenda.fields.description') }}</label>
    <textarea class="govuk-textarea" id="{{ $formKey }}-description" name="description" rows="4" maxlength="4000">{{ $value('description', $values['description'] ?? '') }}</textarea>
</div>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-one-half">
        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="{{ $formKey }}-type">{{ __('govuk_alpha.events.agenda.fields.type') }}</label>
            <select class="govuk-select" id="{{ $formKey }}-type" name="session_type" required>
                @foreach ($types as $type)
                    <option value="{{ $type }}" @selected($value('session_type', $values['type'] ?? 'session') === $type)>{{ __('govuk_alpha.events.agenda.types.' . $type) }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="govuk-grid-column-one-half">
        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="{{ $formKey }}-visibility">{{ __('govuk_alpha.events.agenda.fields.visibility') }}</label>
            <div class="govuk-hint">{{ __('govuk_alpha.events.agenda.visibility_hint') }}</div>
            <select class="govuk-select" id="{{ $formKey }}-visibility" name="visibility" required>
                @foreach ($visibilities as $visibility)
                    <option value="{{ $visibility }}" @selected($value('visibility', $values['visibility'] ?? 'public') === $visibility)>{{ __('govuk_alpha.events.agenda.visibilities.' . $visibility) }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-one-half">
        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="{{ $formKey }}-start">{{ __('govuk_alpha.events.agenda.fields.start') }}</label>
            <input class="govuk-input" id="{{ $formKey }}-start" name="start_at" type="datetime-local" value="{{ $value('start_at', $values['start_at_local'] ?? '') }}" required>
        </div>
    </div>
    <div class="govuk-grid-column-one-half">
        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="{{ $formKey }}-end">{{ __('govuk_alpha.events.agenda.fields.end') }}</label>
            <input class="govuk-input" id="{{ $formKey }}-end" name="end_at" type="datetime-local" value="{{ $value('end_at', $values['end_at_local'] ?? '') }}" required>
        </div>
    </div>
</div>
<p class="govuk-hint">{{ __('govuk_alpha.events.agenda.timezone_hint', ['timezone' => $timezone]) }}</p>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-one-half">
        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="{{ $formKey }}-track">{{ __('govuk_alpha.events.agenda.fields.track') }}</label>
            <input class="govuk-input" id="{{ $formKey }}-track" name="track_name" type="text" maxlength="160" value="{{ $value('track_name', $values['track'] ?? '') }}">
        </div>
    </div>
    <div class="govuk-grid-column-one-half">
        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="{{ $formKey }}-room">{{ __('govuk_alpha.events.agenda.fields.room') }}</label>
            <input class="govuk-input" id="{{ $formKey }}-room" name="room_name" type="text" maxlength="160" value="{{ $value('room_name', $values['room'] ?? '') }}">
        </div>
    </div>
</div>

<fieldset class="govuk-fieldset govuk-!-margin-bottom-4">
    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha.events.agenda.fields.speakers') }}</legend>
    <div class="govuk-hint">{{ __('govuk_alpha.events.agenda.speakers_hint') }}</div>
    @for ($speakerIndex = 0; $speakerIndex < $speakerCount; $speakerIndex++)
        @php
            $memberId = $speakerMemberIds[$speakerIndex] ?? '';
            $speakerName = $speakerNames[$speakerIndex] ?? '';
            $speakerRole = $speakerRoles[$speakerIndex] ?? '';
        @endphp
        <div class="govuk-grid-row govuk-!-margin-bottom-2">
            <input type="hidden" name="speaker_member_id[]" value="{{ $memberId }}">
            <div class="govuk-grid-column-one-half">
                <label class="govuk-label" for="{{ $formKey }}-speaker-name-{{ $speakerIndex }}">{{ __('govuk_alpha.events.agenda.fields.speaker_name', ['number' => $speakerIndex + 1]) }}</label>
                <input class="govuk-input" id="{{ $formKey }}-speaker-name-{{ $speakerIndex }}" name="speaker_name[]" type="text" maxlength="160" value="{{ $speakerName }}" @readonly($memberId !== '')>
                @if ($memberId !== '')
                    <div class="govuk-hint">{{ __('govuk_alpha.events.agenda.linked_member') }}</div>
                @endif
            </div>
            <div class="govuk-grid-column-one-half">
                <label class="govuk-label" for="{{ $formKey }}-speaker-role-{{ $speakerIndex }}">{{ __('govuk_alpha.events.agenda.fields.speaker_role') }}</label>
                <input class="govuk-input" id="{{ $formKey }}-speaker-role-{{ $speakerIndex }}" name="speaker_role[]" type="text" maxlength="120" value="{{ $speakerRole }}">
            </div>
        </div>
    @endfor
</fieldset>


<fieldset class="govuk-fieldset govuk-!-margin-bottom-4">
    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('event_agenda.resources_title') }}</legend>
    <div class="govuk-hint">{{ __('event_agenda.resources_hint') }}</div>
    @for ($resourceIndex = 0; $resourceIndex < $resourceCount; $resourceIndex++)
        @php
            $resourceType = $resourceTypes[$resourceIndex] ?? 'link';
            $resourceTitle = $resourceTitles[$resourceIndex] ?? '';
            $resourceUrl = $resourceUrls[$resourceIndex] ?? '';
            $resourceVisibility = $resourceVisibilities[$resourceIndex] ?? 'public';
        @endphp
        <fieldset class="govuk-fieldset govuk-!-margin-bottom-4">
            <legend class="govuk-fieldset__legend">{{ __('event_agenda.resource_number', ['number' => $resourceIndex + 1]) }}</legend>
            <div class="govuk-grid-row">
                <div class="govuk-grid-column-one-half">
                    <label class="govuk-label" for="{{ $formKey }}-resource-type-{{ $resourceIndex }}">{{ __('event_agenda.resource_type') }}</label>
                    <select class="govuk-select" id="{{ $formKey }}-resource-type-{{ $resourceIndex }}" name="resource_type[]">
                        @foreach ($agendaResourceTypes as $agendaResourceType)
                            <option value="{{ $agendaResourceType }}" @selected($resourceType === $agendaResourceType)>{{ __('event_agenda.resource_types.' . $agendaResourceType) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="govuk-grid-column-one-half">
                    <label class="govuk-label" for="{{ $formKey }}-resource-visibility-{{ $resourceIndex }}">{{ __('event_agenda.resource_visibility') }}</label>
                    <select class="govuk-select" id="{{ $formKey }}-resource-visibility-{{ $resourceIndex }}" name="resource_visibility[]">
                        @foreach ($visibilities as $resourceVisibilityOption)
                            <option value="{{ $resourceVisibilityOption }}" @selected($resourceVisibility === $resourceVisibilityOption)>{{ __('govuk_alpha.events.agenda.visibilities.' . $resourceVisibilityOption) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="{{ $formKey }}-resource-title-{{ $resourceIndex }}">{{ __('event_agenda.resource_title') }}</label>
                <input class="govuk-input" id="{{ $formKey }}-resource-title-{{ $resourceIndex }}" name="resource_title[]" type="text" maxlength="191" value="{{ $resourceTitle }}">
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="{{ $formKey }}-resource-url-{{ $resourceIndex }}">{{ __('event_agenda.resource_url') }}</label>
                <div class="govuk-hint">{{ __('event_agenda.resource_url_hint') }}</div>
                <input class="govuk-input" id="{{ $formKey }}-resource-url-{{ $resourceIndex }}" name="resource_url[]" type="url" inputmode="url" maxlength="2048" value="{{ $resourceUrl }}">
            </div>
        </fieldset>
    @endfor
</fieldset>
