{{--
  Copyright © 2024–2026 Jasper Ford
  SPDX-License-Identifier: AGPL-3.0-or-later
  Author: Jasper Ford
  See NOTICE file for attribution and acknowledgements.
--}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $eventId]) }}">
        {{ __('govuk_alpha_events.common.back_to_event') }}
    </a>

    <span class="govuk-caption-l">{{ $event['title'] ?? '' }}</span>
    <h1 class="govuk-heading-xl">{{ __('event_registration.title') }}</h1>
    <p class="govuk-body-l">{{ __('event_registration.description') }}</p>

    @if ($errors->any())
        <div class="govuk-error-summary" data-module="govuk-error-summary" role="alert" tabindex="-1">
            <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_events.common.error_title') }}</h2>
            <div class="govuk-error-summary__body">
                <ul class="govuk-list govuk-error-summary__list">
                    @foreach ($errors->all() as $error)
                        <li><a href="#registration-content">{{ $error }}</a></li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if ($status)
        <div class="govuk-notification-banner govuk-notification-banner--success" role="alert" aria-labelledby="registration-success-title" data-module="govuk-notification-banner">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="registration-success-title">{{ __('govuk_alpha_events.common.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('event_registration.accessible.success') }}</p>
            </div>
        </div>
    @endif

    <div id="registration-content">
        <div class="govuk-inset-text">
            <strong>{{ __('event_registration.privacy.title') }}</strong><br>
            {{ __('event_registration.privacy.description') }}
        </div>

        <h2 class="govuk-heading-l">{{ __('event_registration.accessible.attendee_heading') }}</h2>

        @php
            $publishedForm = $attendee['form'] ?? null;
            $ownRegistrations = collect($attendee['registrations'] ?? []);
            $activeRegistration = $ownRegistrations->first(fn ($row) => in_array((string) data_get($row, 'registration_state'), ['invited', 'confirmed', 'pending'], true));
            $oldAnswers = old('answers', []);
            $oldAnswers = is_array($oldAnswers) ? $oldAnswers : [];
        @endphp

        @if ($publishedForm && $activeRegistration)
            <h3 class="govuk-heading-m">{{ $publishedForm->name }}</h3>
            @if ($publishedForm->description)
                <p class="govuk-body">{{ $publishedForm->description }}</p>
            @endif
            <form method="post" action="{{ route('govuk-alpha.events.registration.answers.submit', [
                'tenantSlug' => $tenantSlug,
                'id' => $eventId,
                'registrationId' => data_get($activeRegistration, 'id'),
                'formId' => $publishedForm->id,
            ]) }}" data-alpha-registration-form>
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                @foreach ($publishedForm->questions as $question)
                    @php
                        $questionType = $question->question_type->value;
                        $answerName = 'answers[' . $question->stable_key . ']';
                        $answerId = 'answer-' . $question->stable_key;
                        $answerValue = $oldAnswers[$question->stable_key] ?? null;
                        $validationRules = is_array($question->validation_rules) ? $question->validation_rules : [];
                        $visibilityRules = is_array($question->visibility_rules) ? $question->visibility_rules : null;
                        $nativeRequired = $question->is_required && $visibilityRules === null;
                        $encodedValidationRules = base64_encode(json_encode($validationRules, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                        $encodedVisibilityRules = $visibilityRules === null
                            ? ''
                            : base64_encode(json_encode($visibilityRules, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                        $minLength = isset($validationRules['min_length']) ? (int) $validationRules['min_length'] : null;
                        $configuredMaxLength = isset($validationRules['max_length']) ? (int) $validationRules['max_length'] : null;
                        $hardMaxLength = $questionType === 'short_text' ? 500 : 10000;
                        $maxLength = min($configuredMaxLength ?? $hardMaxLength, $hardMaxLength);
                        $minSelections = isset($validationRules['min_selections']) ? (int) $validationRules['min_selections'] : null;
                        $maxSelections = isset($validationRules['max_selections']) ? (int) $validationRules['max_selections'] : null;
                    @endphp
                    <div
                        class="govuk-form-group"
                        data-alpha-registration-question="{{ $question->stable_key }}"
                        data-alpha-registration-type="{{ $questionType }}"
                        data-alpha-registration-required="{{ $question->is_required ? '1' : '0' }}"
                        data-alpha-registration-validation="{{ $encodedValidationRules }}"
                        @if ($encodedVisibilityRules !== '') data-alpha-registration-visibility="{{ $encodedVisibilityRules }}" @endif
                        data-alpha-registration-required-message="{{ __('event_registration.answers.validation.required') }}"
                        @if ($minLength !== null) data-alpha-registration-min-length-message="{{ __('event_registration.answers.validation.min_length', ['limit' => $minLength]) }}" @endif
                        @if ($configuredMaxLength !== null) data-alpha-registration-max-length-message="{{ __('event_registration.answers.validation.max_length', ['limit' => $configuredMaxLength]) }}" @endif
                        @if ($minSelections !== null) data-alpha-registration-min-selections-message="{{ trans_choice('event_registration.answers.validation.min_selections', $minSelections, ['count' => $minSelections]) }}" @endif
                        @if ($maxSelections !== null) data-alpha-registration-max-selections-message="{{ trans_choice('event_registration.answers.validation.max_selections', $maxSelections, ['count' => $maxSelections]) }}" @endif
                    >
                        @if (in_array($questionType, ['single_choice', 'multiple_choice'], true))
                            <fieldset class="govuk-fieldset" @if ($question->is_required) aria-required="true" @endif>
                                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">{{ $question->prompt }}</legend>
                                @if ($question->help_text)<div class="govuk-hint">{{ $question->help_text }}</div>@endif
                                <div class="{{ $questionType === 'single_choice' ? 'govuk-radios' : 'govuk-checkboxes' }}" data-module="{{ $questionType === 'single_choice' ? 'govuk-radios' : 'govuk-checkboxes' }}">
                                    @foreach (($question->choice_options ?? []) as $choiceIndex => $choice)
                                        <div class="{{ $questionType === 'single_choice' ? 'govuk-radios__item' : 'govuk-checkboxes__item' }}">
                                            <input
                                                class="{{ $questionType === 'single_choice' ? 'govuk-radios__input' : 'govuk-checkboxes__input' }}"
                                                id="{{ $answerId }}-{{ $choiceIndex }}"
                                                name="{{ $answerName }}{{ $questionType === 'multiple_choice' ? '[]' : '' }}"
                                                type="{{ $questionType === 'single_choice' ? 'radio' : 'checkbox' }}"
                                                value="{{ $choice }}"
                                                @if ($questionType === 'single_choice' && $nativeRequired && $choiceIndex === 0) required @endif
                                                @checked($questionType === 'multiple_choice'
                                                    ? in_array($choice, is_array($answerValue) ? $answerValue : [], true)
                                                    : $answerValue === $choice)
                                            >
                                            <label class="{{ $questionType === 'single_choice' ? 'govuk-label govuk-radios__label' : 'govuk-label govuk-checkboxes__label' }}" for="{{ $answerId }}-{{ $choiceIndex }}">{{ $choice }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </fieldset>
                        @elseif (in_array($questionType, ['consent', 'waiver'], true))
                            <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                                <div class="govuk-checkboxes__item">
                                    <input
                                        class="govuk-checkboxes__input"
                                        id="{{ $answerId }}"
                                        name="{{ $answerName }}"
                                        type="checkbox"
                                        value="1"
                                        @if ($nativeRequired) required @endif
                                        @checked(filter_var($answerValue, FILTER_VALIDATE_BOOL))
                                    >
                                    <label class="govuk-label govuk-checkboxes__label" for="{{ $answerId }}">{{ $question->prompt }}</label>
                                    <div class="govuk-hint govuk-checkboxes__hint">{{ $question->displayed_text }}</div>
                                </div>
                            </div>
                        @elseif ($questionType === 'short_text')
                            <label class="govuk-label govuk-label--m" for="{{ $answerId }}">{{ $question->prompt }}</label>
                            @if ($question->help_text)<div class="govuk-hint" id="{{ $answerId }}-hint">{{ $question->help_text }}</div>@endif
                            <input
                                class="govuk-input"
                                id="{{ $answerId }}"
                                name="{{ $answerName }}"
                                type="text"
                                value="{{ is_string($answerValue) ? $answerValue : '' }}"
                                @if ($nativeRequired) required @endif
                                @if ($minLength !== null) minlength="{{ $minLength }}" @endif
                                maxlength="{{ $maxLength }}"
                            >
                        @else
                            <label class="govuk-label govuk-label--m" for="{{ $answerId }}">{{ $question->prompt }}</label>
                            @if ($question->help_text)<div class="govuk-hint" id="{{ $answerId }}-hint">{{ $question->help_text }}</div>@endif
                            <textarea
                                class="govuk-textarea"
                                id="{{ $answerId }}"
                                name="{{ $answerName }}"
                                rows="5"
                                @if ($nativeRequired) required @endif
                                @if ($minLength !== null) minlength="{{ $minLength }}" @endif
                                maxlength="{{ $maxLength }}"
                            >{{ is_string($answerValue) ? $answerValue : '' }}</textarea>
                        @endif
                    </div>
                @endforeach
                <button class="govuk-button" data-module="govuk-button" type="submit">{{ __('event_registration.accessible.submit_answers') }}</button>
            </form>
        @elseif ($publishedForm)
            <p class="govuk-body">{{ __('event_registration.forms.not_published') }}</p>
        @endif

        <h3 class="govuk-heading-m">{{ __('event_registration.accessible.your_invitations') }}</h3>
        @forelse (($attendee['invitations'] ?? []) as $invitation)
            @php
                $invitationStatus = (string) data_get($invitation, 'status');
            @endphp
            <div class="govuk-summary-card">
                <div class="govuk-summary-card__title-wrapper">
                    <h4 class="govuk-summary-card__title">{{ __('event_registration.invitations.types.member') }}</h4>
                    <strong class="govuk-tag">{{ __('event_registration.statuses.' . $invitationStatus) }}</strong>
                </div>
                <div class="govuk-summary-card__content">
                    @if ($invitationStatus === 'issued')
                        <form method="post" action="{{ route('govuk-alpha.events.registration.invitations.accept', ['tenantSlug' => $tenantSlug, 'id' => $eventId, 'invitationId' => data_get($invitation, 'id')]) }}">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button" type="submit">{{ __('event_registration.accessible.accept_invitation') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <p class="govuk-body">{{ __('event_registration.invitations.empty') }}</p>
        @endforelse

        @if ($activeRegistration && data_get($attendee, 'settings.guests_enabled'))
            <h3 class="govuk-heading-m">{{ __('event_registration.accessible.add_guest') }}</h3>
            <form method="post" action="{{ route('govuk-alpha.events.registration.guests.capture', ['tenantSlug' => $tenantSlug, 'id' => $eventId, 'registrationId' => data_get($activeRegistration, 'id')]) }}" novalidate>
                @csrf
                <input type="hidden" name="expected_registration_version" value="{{ data_get($activeRegistration, 'registration_version') }}">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="guest-name">{{ __('event_registration.accessible.guest_name') }}</label>
                    <input class="govuk-input" id="guest-name" name="display_name" type="text" required>
                </div>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="guest-email">{{ __('event_registration.accessible.guest_email') }}</label>
                    <input class="govuk-input" id="guest-email" name="email" type="email">
                </div>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="guest-phone">{{ __('event_registration.accessible.guest_phone') }}</label>
                    <input class="govuk-input" id="guest-phone" name="phone" type="tel">
                </div>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="guest-ticket">{{ __('event_registration.accessible.ticket_entitlement') }}</label>
                    <input class="govuk-input govuk-input--width-10" id="guest-ticket" name="ticket_entitlement_id" type="number" min="1">
                </div>
                <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                    <div class="govuk-checkboxes__item">
                        <input class="govuk-checkboxes__input" id="guest-consent" name="consent_accepted" type="checkbox" value="1" required>
                        <label class="govuk-label govuk-checkboxes__label" for="guest-consent">{{ __('event_registration.accessible.privacy_consent_label') }}</label>
                        <div class="govuk-hint govuk-checkboxes__hint">{{ __('event_registration.accessible.privacy_consent_text') }}</div>
                    </div>
                    <div class="govuk-checkboxes__item">
                        <input class="govuk-checkboxes__input" id="guest-notifications" name="notification_consent" type="checkbox" value="1">
                        <label class="govuk-label govuk-checkboxes__label" for="guest-notifications">{{ __('event_registration.accessible.notification_consent_label') }}</label>
                        <div class="govuk-hint govuk-checkboxes__hint">{{ __('event_registration.accessible.notification_consent_text') }}</div>
                    </div>
                </div>
                <button class="govuk-button govuk-!-margin-top-4" data-module="govuk-button" type="submit">{{ __('event_registration.accessible.add_guest') }}</button>
            </form>
        @endif

        @if (collect($attendee['guests'] ?? [])->isNotEmpty())
            <h3 class="govuk-heading-m">{{ __('event_registration.guests.title') }}</h3>
            <dl class="govuk-summary-list">
                @foreach (($attendee['guests'] ?? []) as $guest)
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ data_get($guest, 'display_name') ?? __('event_registration.guests.name_hidden') }}</dt>
                        <dd class="govuk-summary-list__value">{{ __('event_registration.statuses.' . data_get($guest, 'status')) }}</dd>
                        @if (data_get($guest, 'status') === 'captured')
                            <dd class="govuk-summary-list__actions">
                                <form method="post" action="{{ route('govuk-alpha.events.registration.guests.cancel', ['tenantSlug' => $tenantSlug, 'id' => $eventId, 'guestId' => data_get($guest, 'id')]) }}">
                                    @csrf
                                    <input type="hidden" name="expected_revision" value="{{ data_get($guest, 'revision') }}">
                                    <label class="govuk-label" for="guest-cancel-reason-{{ data_get($guest, 'id') }}">{{ __('event_registration.guests.cancel_reason') }}</label>
                                    <input class="govuk-input" id="guest-cancel-reason-{{ data_get($guest, 'id') }}" name="reason" type="text" required>
                                    <div class="govuk-checkboxes govuk-!-margin-top-3">
                                        <div class="govuk-checkboxes__item">
                                            <input class="govuk-checkboxes__input" id="guest-cancel-confirm-{{ data_get($guest, 'id') }}" name="confirm_destructive" type="checkbox" value="1" required>
                                            <label class="govuk-label govuk-checkboxes__label" for="guest-cancel-confirm-{{ data_get($guest, 'id') }}">{{ __('event_registration.guests.cancel_confirm_body', ['name' => data_get($guest, 'display_name') ?? __('event_registration.guests.name_hidden')]) }}</label>
                                        </div>
                                    </div>
                                    <button class="govuk-button govuk-button--warning govuk-!-margin-top-3 govuk-!-margin-bottom-0" data-module="govuk-button" type="submit">{{ __('event_registration.guests.cancel') }}</button>
                                </form>
                            </dd>
                        @endif
                    </div>
                @endforeach
            </dl>
        @endif

        @if ($organizer)
            <hr class="govuk-section-break govuk-section-break--xl govuk-section-break--visible">
            <h2 class="govuk-heading-l">{{ __('event_registration.accessible.organizer_heading') }}</h2>

            @php
                $policySettings = data_get($organizer, 'settings');
                $policyTimezone = $policySettings?->event_timezone_snapshot ?? 'UTC';
                $policyInstant = static function ($value) use ($policyTimezone): string {
                    if (!$value) {
                        return '';
                    }
                    return \Carbon\CarbonImmutable::parse((string) $value, 'UTC')
                        ->setTimezone((string) $policyTimezone)
                        ->format('Y-m-d\TH:i');
                };
                $policyApproval = $policySettings?->approval_mode?->value ?? 'auto';
                $registrationPageHref = static function (string $collection, int $page) use ($tenantSlug, $eventId): string {
                    $pageKey = $collection . '_page';
                    $query = request()->except($pageKey);

                    return route('govuk-alpha.events.registration.index', array_merge($query, [
                        'tenantSlug' => $tenantSlug,
                        'id' => $eventId,
                        $pageKey => $page,
                    ])) . '#registration-' . $collection;
                };
            @endphp
            <h3 class="govuk-heading-m">{{ __('event_registration.settings.title') }}</h3>
            <p class="govuk-body">{{ __('event_registration.settings.description') }}</p>
            <p class="govuk-body">
                <strong class="govuk-tag">
                    {{ $policySettings ? __('event_registration.statuses.' . $policySettings->status->value) : __('event_registration.settings.not_created') }}
                </strong>
                @if ($policySettings)
                    {{ __('event_registration.settings.revision', ['revision' => $policySettings->revision]) }}
                @endif
            </p>
            <form method="post" action="{{ route('govuk-alpha.events.registration.settings.save', ['tenantSlug' => $tenantSlug, 'id' => $eventId]) }}" novalidate>
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <input type="hidden" name="expected_revision" value="{{ $policySettings?->revision ?? 0 }}">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="registration-approval-mode">{{ __('event_registration.settings.approval_mode') }}</label>
                    <select class="govuk-select" id="registration-approval-mode" name="approval_mode">
                        @foreach (['auto', 'manual'] as $approvalMode)
                            <option value="{{ $approvalMode }}" @selected($policyApproval === $approvalMode)>{{ __('event_registration.settings.approval_modes.' . $approvalMode) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="registration-opens-at">{{ __('event_registration.settings.opens_at') }}</label>
                    <div class="govuk-hint">{{ __('event_registration.settings.window_hint') }}</div>
                    <input class="govuk-input govuk-input--width-20" id="registration-opens-at" name="opens_at" type="datetime-local" value="{{ $policyInstant($policySettings?->opens_at_utc) }}">
                </div>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="registration-closes-at">{{ __('event_registration.settings.closes_at') }}</label>
                    <input class="govuk-input govuk-input--width-20" id="registration-closes-at" name="closes_at" type="datetime-local" value="{{ $policyInstant($policySettings?->closes_at_utc) }}">
                </div>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="registration-cutoff">{{ __('event_registration.settings.cancellation_cutoff') }}</label>
                    <input class="govuk-input govuk-input--width-20" id="registration-cutoff" name="cancellation_cutoff_at" type="datetime-local" value="{{ $policyInstant($policySettings?->cancellation_cutoff_at_utc) }}">
                </div>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="registration-member-limit">{{ __('event_registration.settings.per_member_limit') }}</label>
                    <input class="govuk-input govuk-input--width-3" id="registration-member-limit" name="per_member_limit" type="number" min="1" max="10" value="{{ $policySettings?->per_member_limit ?? 1 }}" required>
                </div>
                <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                    <div class="govuk-checkboxes__item">
                        <input class="govuk-checkboxes__input" id="registration-guests-enabled" name="guests_enabled" type="checkbox" value="1" @checked((bool) ($policySettings?->guests_enabled ?? false))>
                        <label class="govuk-label govuk-checkboxes__label" for="registration-guests-enabled">{{ __('event_registration.settings.guests_enabled') }}</label>
                    </div>
                </div>
                <div class="govuk-form-group govuk-!-margin-top-4">
                    <label class="govuk-label" for="registration-guest-limit">{{ __('event_registration.settings.max_guests') }}</label>
                    <input class="govuk-input govuk-input--width-3" id="registration-guest-limit" name="max_guests_per_registration" type="number" min="1" max="10" value="{{ max(1, (int) ($policySettings?->max_guests_per_registration ?? 1)) }}" required>
                </div>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="registration-guest-retention">{{ __('event_registration.settings.guest_retention') }}</label>
                    <input class="govuk-input govuk-input--width-5" id="registration-guest-retention" name="guest_retention_days" type="number" min="1" max="36500" value="{{ $policySettings?->guest_retention_days ?? 30 }}" required>
                </div>
                <button class="govuk-button" data-module="govuk-button" type="submit">{{ __('event_registration.settings.save') }}</button>
            </form>
            @if ($policySettings?->status->value === 'draft')
                <form method="post" action="{{ route('govuk-alpha.events.registration.settings.publish', ['tenantSlug' => $tenantSlug, 'id' => $eventId]) }}">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <input type="hidden" name="expected_revision" value="{{ $policySettings->revision }}">
                    <button class="govuk-button govuk-button--secondary" data-module="govuk-button" type="submit">{{ __('event_registration.settings.publish') }}</button>
                </form>
            @endif

            <h3 class="govuk-heading-m">{{ __('event_registration.forms.title') }}</h3>
            <p class="govuk-body"><a class="govuk-link" href="{{ route('govuk-alpha.events.registration.forms.new', ['tenantSlug' => $tenantSlug, 'id' => $eventId]) }}">{{ __('event_registration.forms.new') }}</a></p>
            @forelse ($organizer['forms'] as $form)
                <div class="govuk-summary-card">
                    <div class="govuk-summary-card__title-wrapper">
                        <h4 class="govuk-summary-card__title">{{ $form->name }}</h4>
                        <strong class="govuk-tag">{{ __('event_registration.statuses.' . $form->status->value) }}</strong>
                    </div>
                    <div class="govuk-summary-card__content">
                        <p class="govuk-body">{{ __('event_registration.forms.version', ['version' => $form->version_number]) }}</p>
                        @if ($form->status->value === 'draft')
                            <a class="govuk-button govuk-button--secondary" href="{{ route('govuk-alpha.events.registration.forms.edit', ['tenantSlug' => $tenantSlug, 'id' => $eventId, 'formId' => $form->id]) }}">{{ __('event_registration.forms.edit') }}</a>
                            <form class="govuk-!-display-inline-block" method="post" action="{{ route('govuk-alpha.events.registration.forms.publish', ['tenantSlug' => $tenantSlug, 'id' => $eventId, 'formId' => $form->id]) }}">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <input type="hidden" name="expected_form_revision" value="{{ $form->revision }}">
                                <input type="hidden" name="expected_settings_revision" value="{{ data_get($organizer, 'settings.revision') }}">
                                <button class="govuk-button" data-module="govuk-button" type="submit">{{ __('event_registration.forms.publish') }}</button>
                            </form>
                        @else
                            <form method="post" action="{{ route('govuk-alpha.events.registration.forms.fork', ['tenantSlug' => $tenantSlug, 'id' => $eventId, 'formId' => $form->id]) }}">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <input type="hidden" name="expected_settings_revision" value="{{ data_get($organizer, 'settings.revision') }}">
                                <button class="govuk-button govuk-button--secondary" data-module="govuk-button" type="submit">{{ __('event_registration.forms.create_revision') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <p class="govuk-body">{{ __('event_registration.forms.empty') }}</p>
            @endforelse

            <h3 class="govuk-heading-m govuk-!-margin-top-7" id="registration-submissions">{{ __('event_registration.submissions.title') }}</h3>
            @forelse ($organizer['submissions'] as $submission)
                <details class="govuk-details">
                    <summary class="govuk-details__summary">
                        <span class="govuk-details__summary-text">{{ __('event_registration.accessible.review_submission', ['id' => data_get($submission, 'id')]) }}</span>
                    </summary>
                    <div class="govuk-details__text">
                        <form method="post" action="{{ route('govuk-alpha.events.registration.answers.review', ['tenantSlug' => $tenantSlug, 'id' => $eventId, 'submissionId' => data_get($submission, 'id')]) }}">
                            @csrf
                            <div class="govuk-form-group">
                                <label class="govuk-label" for="purpose-{{ data_get($submission, 'id') }}">{{ __('event_registration.submissions.purpose') }}</label>
                                <textarea class="govuk-textarea" id="purpose-{{ data_get($submission, 'id') }}" name="purpose" rows="3" required></textarea>
                            </div>
                            <input type="hidden" name="correlation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            @if (data_get($organizer, 'permissions.view_sensitive_answers'))
                                <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                                    <div class="govuk-checkboxes__item">
                                        <input class="govuk-checkboxes__input" id="sensitive-{{ data_get($submission, 'id') }}" name="include_sensitive" type="checkbox" value="1">
                                        <label class="govuk-label govuk-checkboxes__label" for="sensitive-{{ data_get($submission, 'id') }}">{{ __('event_registration.submissions.include_sensitive') }}</label>
                                    </div>
                                </div>
                            @endif
                            <button class="govuk-button govuk-!-margin-top-3" data-module="govuk-button" type="submit">{{ __('event_registration.submissions.open_answers') }}</button>
                        </form>
                    </div>
                </details>
            @empty
                <p class="govuk-body">{{ __('event_registration.submissions.empty') }}</p>
            @endforelse
            @php
                $submissionPagination = data_get($organizer, 'pagination.submissions');
            @endphp
            @if (data_get($submissionPagination, 'previous_page') || data_get($submissionPagination, 'next_page'))
                <nav class="govuk-pagination" aria-label="{{ __('event_registration.submissions.title') }}">
                    @if (data_get($submissionPagination, 'previous_page'))
                        <div class="govuk-pagination__prev">
                            <a class="govuk-link govuk-pagination__link" rel="prev" href="{{ $registrationPageHref('submissions', (int) data_get($submissionPagination, 'previous_page')) }}">{{ __('event_registration.common.previous') }}</a>
                        </div>
                    @endif
                    @if (data_get($submissionPagination, 'next_page'))
                        <div class="govuk-pagination__next">
                            <a class="govuk-link govuk-pagination__link" rel="next" href="{{ $registrationPageHref('submissions', (int) data_get($submissionPagination, 'next_page')) }}">{{ __('event_registration.common.next') }}</a>
                        </div>
                    @endif
                </nav>
            @endif

            @if (data_get($organizer, 'permissions.export_answers'))
                <details class="govuk-details">
                    <summary class="govuk-details__summary"><span class="govuk-details__summary-text">{{ __('event_registration.submissions.export') }}</span></summary>
                    <div class="govuk-details__text">
                        <form method="post" action="{{ route('govuk-alpha.events.registration.answers.export', ['tenantSlug' => $tenantSlug, 'id' => $eventId]) }}">
                            @csrf
                            <label class="govuk-label" for="export-purpose">{{ __('event_registration.submissions.purpose') }}</label>
                            <textarea class="govuk-textarea" id="export-purpose" name="purpose" rows="3" required></textarea>
                            <input type="hidden" name="correlation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            <button class="govuk-button govuk-button--secondary govuk-!-margin-top-3" data-module="govuk-button" type="submit">{{ __('event_registration.submissions.download_csv') }}</button>
                        </form>
                    </div>
                </details>
            @endif

            <h3 class="govuk-heading-m govuk-!-margin-top-7">{{ __('event_registration.invitations.builder_title') }}</h3>
            <p class="govuk-body">{{ __('event_registration.invitations.builder_description') }}</p>
            <form method="post" action="{{ route('govuk-alpha.events.registration.campaigns.preview', ['tenantSlug' => $tenantSlug, 'id' => $eventId]) }}">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="campaign-type">{{ __('event_registration.invitations.type_label') }}</label>
                    <select class="govuk-select" id="campaign-type" name="campaign_type">
                        @foreach (['member', 'email', 'group', 'audience', 'csv'] as $campaignType)
                            <option value="{{ $campaignType }}">{{ __('event_registration.invitations.types.' . $campaignType) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="campaign-source">{{ __('event_registration.accessible.source_value') }}</label>
                    <div class="govuk-hint">{{ __('event_registration.accessible.source_hint') }}</div>
                    <textarea class="govuk-textarea" id="campaign-source" name="source" rows="6" required></textarea>
                </div>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="campaign-locale">{{ __('event_registration.invitations.locale_label') }}</label>
                    <select class="govuk-select" id="campaign-locale" name="default_locale">
                        @foreach (['en', 'ga', 'de', 'fr', 'it', 'pt', 'es', 'nl', 'pl', 'ja', 'ar'] as $locale)
                            <option value="{{ $locale }}">{{ __('event_registration.locales.' . $locale) }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="govuk-button" data-module="govuk-button" type="submit">{{ __('event_registration.invitations.preview') }}</button>
            </form>

            @if ($campaignPreview)
                <div class="govuk-notification-banner" role="region" aria-labelledby="campaign-preview-title">
                    <div class="govuk-notification-banner__header"><h4 class="govuk-notification-banner__title" id="campaign-preview-title">{{ __('event_registration.invitations.preview_title') }}</h4></div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-body">{{ __('event_registration.invitations.snapshot_notice') }}</p>
                        <p class="govuk-body">{{ __('event_registration.invitations.valid_count', ['count' => $campaignPreview->valid_count]) }}</p>
                    </div>
                </div>
                <h4 class="govuk-heading-s">{{ __('event_registration.accessible.schedule_or_send') }}</h4>
                <form method="post" action="{{ route('govuk-alpha.events.registration.campaigns.issue', ['tenantSlug' => $tenantSlug, 'id' => $eventId, 'campaignId' => $campaignPreview->id]) }}">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <input type="hidden" name="expected_revision" value="{{ $campaignPreview->revision }}">
                    <label class="govuk-label" for="campaign-expiry">{{ __('event_registration.accessible.expiry') }}</label>
                    <input class="govuk-input govuk-input--width-20" id="campaign-expiry" name="expires_at" type="datetime-local" required>
                    <button class="govuk-button govuk-!-margin-top-3" data-module="govuk-button" type="submit">{{ __('event_registration.invitations.send_now') }}</button>
                </form>
                <form method="post" action="{{ route('govuk-alpha.events.registration.campaigns.schedule', ['tenantSlug' => $tenantSlug, 'id' => $eventId, 'campaignId' => $campaignPreview->id]) }}">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <input type="hidden" name="expected_revision" value="{{ $campaignPreview->revision }}">
                    <label class="govuk-label" for="campaign-scheduled">{{ __('event_registration.invitations.scheduled_for') }}</label>
                    <input class="govuk-input govuk-input--width-20" id="campaign-scheduled" name="scheduled_for" type="datetime-local" required>
                    <button class="govuk-button govuk-button--secondary govuk-!-margin-top-3" data-module="govuk-button" type="submit">{{ __('event_registration.invitations.schedule') }}</button>
                </form>
            @endif

            <h3 class="govuk-heading-m govuk-!-margin-top-7" id="registration-campaigns">{{ __('event_registration.invitations.history_title') }}</h3>
            @forelse ($organizer['campaigns'] as $campaign)
                <div class="govuk-summary-card">
                    <div class="govuk-summary-card__title-wrapper">
                        <h4 class="govuk-summary-card__title">{{ __('event_registration.invitations.types.' . $campaign->campaign_type->value) }}</h4>
                        <strong class="govuk-tag">{{ __('event_registration.statuses.' . $campaign->status->value) }}</strong>
                    </div>
                    <div class="govuk-summary-card__content">
                        <p class="govuk-body">{{ __('event_registration.invitations.valid_count', ['count' => $campaign->valid_count]) }}</p>
                        @if (in_array($campaign->status->value, ['previewed', 'scheduled'], true))
                            <form method="post" action="{{ route('govuk-alpha.events.registration.campaigns.cancel', ['tenantSlug' => $tenantSlug, 'id' => $eventId, 'campaignId' => $campaign->id]) }}">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <input type="hidden" name="expected_revision" value="{{ $campaign->revision }}">
                                <label class="govuk-label" for="cancel-reason-{{ $campaign->id }}">{{ __('event_registration.invitations.cancel_reason') }}</label>
                                <input class="govuk-input" id="cancel-reason-{{ $campaign->id }}" name="reason" type="text" required>
                                <button class="govuk-button govuk-button--warning govuk-!-margin-top-3" data-module="govuk-button" type="submit">{{ __('event_registration.invitations.confirm_cancel') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <p class="govuk-body">{{ __('event_registration.invitations.empty') }}</p>
            @endforelse
            @php
                $campaignPagination = data_get($organizer, 'pagination.campaigns');
            @endphp
            @if (data_get($campaignPagination, 'previous_page') || data_get($campaignPagination, 'next_page'))
                <nav class="govuk-pagination" aria-label="{{ __('event_registration.invitations.history_title') }}">
                    @if (data_get($campaignPagination, 'previous_page'))
                        <div class="govuk-pagination__prev">
                            <a class="govuk-link govuk-pagination__link" rel="prev" href="{{ $registrationPageHref('campaigns', (int) data_get($campaignPagination, 'previous_page')) }}">{{ __('event_registration.common.previous') }}</a>
                        </div>
                    @endif
                    @if (data_get($campaignPagination, 'next_page'))
                        <div class="govuk-pagination__next">
                            <a class="govuk-link govuk-pagination__link" rel="next" href="{{ $registrationPageHref('campaigns', (int) data_get($campaignPagination, 'next_page')) }}">{{ __('event_registration.common.next') }}</a>
                        </div>
                    @endif
                </nav>
            @endif

            <h3 class="govuk-heading-m govuk-!-margin-top-7" id="registration-guests">{{ __('event_registration.guests.title') }}</h3>
            @forelse ($organizer['guests'] as $guest)
                @php
                    $attendance = data_get($guest, 'attendance');
                @endphp
                <div class="govuk-summary-card">
                    <div class="govuk-summary-card__title-wrapper">
                        <h4 class="govuk-summary-card__title">{{ data_get($guest, 'display_name') ?? __('event_registration.guests.name_hidden') }}</h4>
                        <strong class="govuk-tag">{{ __('event_registration.attendance.' . (data_get($attendance, 'status') ?? 'not_checked_in')) }}</strong>
                    </div>
                    @if (data_get($organizer, 'permissions.manage_attendance') && data_get($guest, 'status') === 'captured')
                        <div class="govuk-summary-card__content">
                            @php
                                $attendanceStatus = data_get($attendance, 'status', 'not_checked_in');
                                $attendanceActions = $attendanceStatus === 'checked_in'
                                    ? ['check_out']
                                    : (in_array($attendanceStatus, ['checked_out', 'attended'], true) ? [] : ['check_in', 'no_show']);
                            @endphp
                            @foreach ($attendanceActions as $action)
                                <form class="govuk-!-display-inline-block" method="post" action="{{ route('govuk-alpha.events.registration.guests.attendance', ['tenantSlug' => $tenantSlug, 'id' => $eventId, 'guestId' => data_get($guest, 'id'), 'action' => $action]) }}">
                                    @csrf
                                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                    <input type="hidden" name="expected_version" value="{{ data_get($attendance, 'version', 0) }}">
                                    <button class="govuk-button govuk-button--secondary" data-module="govuk-button" type="submit">{{ __('event_registration.guests.' . $action) }}</button>
                                </form>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <p class="govuk-body">{{ __('event_registration.guests.empty') }}</p>
            @endforelse
            @php
                $guestPagination = data_get($organizer, 'pagination.guests');
            @endphp
            @if (data_get($guestPagination, 'previous_page') || data_get($guestPagination, 'next_page'))
                <nav class="govuk-pagination" aria-label="{{ __('event_registration.guests.title') }}">
                    @if (data_get($guestPagination, 'previous_page'))
                        <div class="govuk-pagination__prev">
                            <a class="govuk-link govuk-pagination__link" rel="prev" href="{{ $registrationPageHref('guests', (int) data_get($guestPagination, 'previous_page')) }}">{{ __('event_registration.common.previous') }}</a>
                        </div>
                    @endif
                    @if (data_get($guestPagination, 'next_page'))
                        <div class="govuk-pagination__next">
                            <a class="govuk-link govuk-pagination__link" rel="next" href="{{ $registrationPageHref('guests', (int) data_get($guestPagination, 'next_page')) }}">{{ __('event_registration.common.next') }}</a>
                        </div>
                    @endif
                </nav>
            @endif

            @if (data_get($organizer, 'permissions.manage_retention'))
                <h3 class="govuk-heading-m govuk-!-margin-top-7">{{ __('event_registration.retention.title') }}</h3>
                <div class="govuk-warning-text">
                    <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                    <strong class="govuk-warning-text__text"><span class="govuk-visually-hidden">{{ __('govuk_alpha_events.common.warning') }}</span>{{ __('event_registration.retention.warning_description') }}</strong>
                </div>
                <form method="post" action="{{ route('govuk-alpha.events.registration.retention.preview', ['tenantSlug' => $tenantSlug, 'id' => $eventId]) }}">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <label class="govuk-label" for="retention-as-of">{{ __('event_registration.retention.as_of') }}</label>
                    <div class="govuk-hint">{{ __('event_registration.accessible.retention_as_of_hint') }}</div>
                    <input class="govuk-input govuk-input--width-20" id="retention-as-of" name="as_of" type="date" required>
                    <button class="govuk-button govuk-button--secondary govuk-!-margin-top-3" data-module="govuk-button" type="submit">{{ __('event_registration.retention.preview') }}</button>
                </form>
                @if ($retentionRun)
                    <p class="govuk-body">{{ __('event_registration.retention.eligible') }}: {{ $retentionRun->eligible_count }}</p>
                    <form method="post" action="{{ route('govuk-alpha.events.registration.retention.apply', ['tenantSlug' => $tenantSlug, 'id' => $eventId, 'runId' => $retentionRun->id]) }}">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <div class="govuk-checkboxes govuk-!-margin-bottom-4">
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="retention-apply-confirm" name="confirm_destructive" type="checkbox" value="1" required>
                                <label class="govuk-label govuk-checkboxes__label" for="retention-apply-confirm">{{ __('event_registration.retention.confirm_apply') }}</label>
                            </div>
                        </div>
                        <button class="govuk-button govuk-button--warning" data-module="govuk-button" type="submit">{{ __('event_registration.retention.apply') }}</button>
                    </form>
                @endif
            @endif
        @endif
    </div>
@endsection
