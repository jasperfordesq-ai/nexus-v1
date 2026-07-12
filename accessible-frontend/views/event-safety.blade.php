{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">
        {{ __('event_safety.govuk.back') }}
    </a>

    <span class="govuk-caption-l">{{ $event['title'] }}</span>
    <h1 class="govuk-heading-xl">{{ __('event_safety.govuk.title') }}</h1>
    <p class="govuk-body-l">{{ __('event_safety.govuk.intro') }}</p>

    @if ($status === 'safety-updated')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('event_safety.govuk.updated') }}</p>
            </div>
        </div>
    @elseif ($status === 'safety-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p>{{ __('event_safety.govuk.failed') }}</p></div>
            </div>
        </div>
    @endif

    @if ($loadError || !is_array($safety))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('event_safety.govuk.unavailable_title') }}</h2>
                <div class="govuk-error-summary__body"><p>{{ __('event_safety.govuk.unavailable_body') }}</p></div>
            </div>
        </div>
    @else
        @php
            $requirements = is_array($safety['requirements'] ?? null) ? $safety['requirements'] : null;
            $version = is_array($requirements['version'] ?? null) ? $requirements['version'] : null;
            $code = is_array($version['code_of_conduct'] ?? null) ? $version['code_of_conduct'] : null;
            $eligibility = is_array($safety['eligibility'] ?? null) ? $safety['eligibility'] : [];
            $evidence = is_array($safety['evidence'] ?? null) ? $safety['evidence'] : [];
            $codeEvidence = is_array($evidence['code_of_conduct'] ?? null) ? $evidence['code_of_conduct'] : [];
            $guardianEvidence = is_array($evidence['guardian_consent'] ?? null) ? $evidence['guardian_consent'] : [];
            $permissions = is_array($safety['permissions'] ?? null) ? $safety['permissions'] : [];
            $rollout = is_array($safety['rollout'] ?? null) ? $safety['rollout'] : [];
            $localeReason = static function (string $reason): string {
                $key = 'event_safety.govuk.reasons.' . $reason;
                return \Illuminate\Support\Facades\Lang::has($key)
                    ? __($key)
                    : __('event_safety.govuk.reasons.unknown');
            };
        @endphp

        <p class="govuk-body">
            <strong class="govuk-tag{{ ($rollout['mode'] ?? '') === 'enforce' ? ' govuk-tag--green' : ' govuk-tag--yellow' }}">
                {{ __('event_safety.govuk.rollout.' . ($rollout['mode'] ?? 'off')) }}
            </strong>
            @if ($requirements)
                <strong class="govuk-tag govuk-tag--grey">
                    {{ __('event_safety.govuk.requirement_status.' . ($requirements['status'] ?? 'draft')) }}
                </strong>
            @endif
        </p>

        @if (($permissions['manage_requirements'] ?? false) === true)
            <section aria-labelledby="safety-policy-heading">
                <h2 class="govuk-heading-l" id="safety-policy-heading">{{ __('event_safety.govuk.policy_title') }}</h2>
                <p class="govuk-body">{{ __('event_safety.govuk.policy_intro') }}</p>

                <form method="post" action="{{ route('govuk-alpha.events.safety.update', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">
                    @csrf
                    <input type="hidden" name="action" value="save_requirements">
                    <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                    <input type="hidden" name="expected_revision" value="{{ $requirements['revision'] ?? '' }}">

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="minimum-age">{{ __('event_safety.govuk.minimum_age') }}</label>
                        <div class="govuk-hint">{{ __('event_safety.govuk.minimum_age_hint') }}</div>
                        <input class="govuk-input govuk-input--width-3" id="minimum-age" name="minimum_age" type="number" min="0" max="150" value="{{ $version['minimum_age'] ?? '' }}">
                    </div>

                    <div class="govuk-form-group">
                        <fieldset class="govuk-fieldset">
                            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">{{ __('event_safety.govuk.guardian_policy') }}</legend>
                            <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                                <div class="govuk-checkboxes__item">
                                    <input class="govuk-checkboxes__input" id="guardian-required" name="guardian_consent_required" type="checkbox" value="1" @checked($version['guardian_consent_required'] ?? false)>
                                    <label class="govuk-label govuk-checkboxes__label" for="guardian-required">{{ __('event_safety.govuk.guardian_required') }}</label>
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="minor-threshold">{{ __('event_safety.govuk.minor_threshold') }}</label>
                        <div class="govuk-hint">{{ __('event_safety.govuk.minor_threshold_hint') }}</div>
                        <input class="govuk-input govuk-input--width-3" id="minor-threshold" name="minor_age_threshold" type="number" min="1" max="150" value="{{ $version['minor_age_threshold'] ?? '' }}">
                    </div>

                    <div class="govuk-form-group">
                        <fieldset class="govuk-fieldset">
                            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">{{ __('event_safety.govuk.code_policy') }}</legend>
                            <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                                <div class="govuk-checkboxes__item">
                                    <input class="govuk-checkboxes__input" id="code-required" name="code_of_conduct_required" type="checkbox" value="1" @checked($code['required'] ?? false)>
                                    <label class="govuk-label govuk-checkboxes__label" for="code-required">{{ __('event_safety.govuk.code_required') }}</label>
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="code-version">{{ __('event_safety.govuk.code_version') }}</label>
                        <div class="govuk-hint">{{ __('event_safety.govuk.code_version_hint') }}</div>
                        <input class="govuk-input" id="code-version" name="code_of_conduct_text_version" maxlength="191" value="{{ $code['text_version'] ?? '' }}">
                    </div>
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="code-text">{{ __('event_safety.govuk.code_text') }}</label>
                        <textarea class="govuk-textarea" id="code-text" name="code_of_conduct_text" rows="10">{{ $code['text'] ?? '' }}</textarea>
                    </div>
                    <button class="govuk-button" data-module="govuk-button">{{ __('event_safety.govuk.save_draft') }}</button>
                </form>

                @if ($requirements)
                    <div class="govuk-button-group">
                        @if (($requirements['status'] ?? '') === 'draft')
                            <form method="post" action="{{ route('govuk-alpha.events.safety.update', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">
                                @csrf
                                <input type="hidden" name="action" value="publish_requirements">
                                <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                                <input type="hidden" name="expected_revision" value="{{ $requirements['revision'] }}">
                                <input type="hidden" name="expected_version" value="{{ $requirements['current_version'] }}">
                                <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('event_safety.govuk.publish') }}</button>
                            </form>
                        @endif
                        @if (($requirements['status'] ?? '') !== 'archived')
                            <details class="govuk-details">
                                <summary class="govuk-details__summary"><span class="govuk-details__summary-text">{{ __('event_safety.govuk.archive') }}</span></summary>
                                <div class="govuk-details__text">
                                    <p class="govuk-body">{{ __('event_safety.govuk.archive_warning') }}</p>
                                    <form method="post" action="{{ route('govuk-alpha.events.safety.update', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="archive_requirements">
                                        <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                                        <input type="hidden" name="expected_revision" value="{{ $requirements['revision'] }}">
                                        <input type="hidden" name="expected_version" value="{{ $requirements['current_version'] }}">
                                        <button class="govuk-button govuk-button--warning" data-module="govuk-button">{{ __('event_safety.govuk.confirm_archive') }}</button>
                                    </form>
                                </div>
                            </details>
                        @endif
                    </div>
                @endif
            </section>
        @else
            <section aria-labelledby="safety-attendee-heading">
                <h2 class="govuk-heading-l" id="safety-attendee-heading">{{ __('event_safety.govuk.attendee_title') }}</h2>
                <p class="govuk-body">{{ __('event_safety.govuk.eligibility.' . ($eligibility['status'] ?? 'not_evaluated')) }}</p>
                @if (!empty($eligibility['reason_codes']))
                    <div class="govuk-warning-text">
                        <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                        <strong class="govuk-warning-text__text">
                            <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.warning_prefix') }}</span>
                            {{ __('event_safety.govuk.action_required') }}
                        </strong>
                    </div>
                    <ul class="govuk-list govuk-list--bullet">
                        @foreach ($eligibility['reason_codes'] as $reasonCode)
                            <li>{{ $localeReason((string) $reasonCode) }}</li>
                        @endforeach
                    </ul>
                @endif

                @if (($code['required'] ?? false) === true)
                    <h3 class="govuk-heading-m">{{ __('event_safety.govuk.code_title') }}</h3>
                    <div class="govuk-inset-text">{!! nl2br(e((string) $code['text'])) !!}</div>
                    @if (($permissions['acknowledge_code_of_conduct'] ?? false) === true)
                        <form method="post" action="{{ route('govuk-alpha.events.safety.update', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">
                            @csrf
                            <input type="hidden" name="action" value="acknowledge_code">
                            <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                            <input type="hidden" name="text_version" value="{{ $code['text_version'] }}">
                            <input type="hidden" name="text_hash" value="{{ $code['text_hash'] }}">
                            <div class="govuk-checkboxes">
                                <div class="govuk-checkboxes__item">
                                    <input class="govuk-checkboxes__input" id="confirm-code" name="confirm_code" type="checkbox" value="1" required>
                                    <label class="govuk-label govuk-checkboxes__label" for="confirm-code">{{ __('event_safety.govuk.confirm_code') }}</label>
                                </div>
                            </div>
                            <button class="govuk-button govuk-!-margin-top-4" data-module="govuk-button">{{ __('event_safety.govuk.acknowledge') }}</button>
                        </form>
                    @elseif (($permissions['withdraw_code_of_conduct'] ?? false) === true)
                        <form method="post" action="{{ route('govuk-alpha.events.safety.update', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">
                            @csrf
                            <input type="hidden" name="action" value="withdraw_code">
                            <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                            <input type="hidden" name="acknowledgement_id" value="{{ $codeEvidence['acknowledgement_id'] ?? '' }}">
                            <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('event_safety.govuk.withdraw_acknowledgement') }}</button>
                        </form>
                    @endif
                @endif

                @if (($guardianEvidence['status'] ?? 'not_required') !== 'not_required')
                    <h3 class="govuk-heading-m">{{ __('event_safety.govuk.guardian_title') }}</h3>
                    <p class="govuk-body">{{ __('event_safety.govuk.guardian_status.' . ($guardianEvidence['status'] ?? 'required')) }}</p>
                    @if (($permissions['request_guardian_consent'] ?? false) === true)
                        <form method="post" action="{{ route('govuk-alpha.events.safety.update', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">
                            @csrf
                            <input type="hidden" name="action" value="request_guardian_consent">
                            <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                            <div class="govuk-form-group">
                                <label class="govuk-label" for="guardian-name">{{ __('event_safety.govuk.guardian_name') }}</label>
                                <input class="govuk-input" id="guardian-name" name="guardian_name" maxlength="191" required>
                            </div>
                            <div class="govuk-form-group">
                                <label class="govuk-label" for="guardian-email">{{ __('event_safety.govuk.guardian_email') }}</label>
                                <input class="govuk-input" id="guardian-email" name="guardian_email" type="email" maxlength="254" autocomplete="email" required>
                            </div>
                            <div class="govuk-form-group">
                                <label class="govuk-label" for="guardian-relationship">{{ __('event_safety.govuk.guardian_relationship') }}</label>
                                <select class="govuk-select" id="guardian-relationship" name="relationship_code" required>
                                    @foreach (['parent', 'guardian', 'legal_guardian', 'carer'] as $relationship)
                                        <option value="{{ $relationship }}">{{ __('event_safety.govuk.relationships.' . $relationship) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <p class="govuk-hint">{{ __('event_safety.govuk.guardian_privacy') }}</p>
                            <button class="govuk-button" data-module="govuk-button">{{ __('event_safety.govuk.request_guardian') }}</button>
                        </form>
                    @elseif (($permissions['withdraw_guardian_consent'] ?? false) === true)
                        <form method="post" action="{{ route('govuk-alpha.events.safety.update', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">
                            @csrf
                            <input type="hidden" name="action" value="withdraw_guardian_consent">
                            <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                            <input type="hidden" name="consent_id" value="{{ $guardianEvidence['consent_id'] ?? '' }}">
                            <button class="govuk-button govuk-button--warning" data-module="govuk-button">{{ __('event_safety.govuk.withdraw_guardian') }}</button>
                        </form>
                    @endif
                @endif
            </section>
        @endif

        @if (($permissions['review_participation'] ?? false) === true)
            <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">
            <section aria-labelledby="safety-reviews-heading">
                <h2 class="govuk-heading-l" id="safety-reviews-heading">{{ __('event_safety.govuk.reviews_title') }}</h2>
                <p class="govuk-body">{{ __('event_safety.govuk.reviews_intro') }}</p>

                @if (!empty($people))
                    <form method="post" action="{{ route('govuk-alpha.events.safety.update', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">
                        @csrf
                        <input type="hidden" name="action" value="record_review">
                        <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="review-member">{{ __('event_safety.govuk.review_member') }}</label>
                            <select class="govuk-select" id="review-member" name="user_id" required>
                                <option value="">{{ __('event_safety.govuk.review_member_choose') }}</option>
                                @foreach ($people as $person)
                                    <option value="{{ $person['id'] }}">{{ $person['display_name'] ?: __('event_safety.govuk.member_fallback', ['id' => $person['id']]) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="review-decision">{{ __('event_safety.govuk.review_decision') }}</label>
                            <select class="govuk-select" id="review-decision" name="decision" required>
                                @foreach (['deny', 'remove'] as $decision)
                                    <option value="{{ $decision }}">{{ __('event_safety.govuk.decisions.' . $decision) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="review-reason">{{ __('event_safety.govuk.review_reason') }}</label>
                            <select class="govuk-select" id="review-reason" name="reason_code" required>
                                @foreach (['safeguarding_policy', 'minimum_age', 'guardian_consent', 'code_of_conduct', 'conduct_violation', 'safety_review', 'user_block'] as $reason)
                                    <option value="{{ $reason }}">{{ __('event_safety.govuk.reasons.' . $reason) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="review-from">{{ __('event_safety.govuk.effective_from') }}</label>
                            <input class="govuk-input govuk-input--width-20" id="review-from" name="effective_from" type="datetime-local" value="{{ now()->format('Y-m-d\TH:i') }}" required>
                        </div>
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="review-until">{{ __('event_safety.govuk.effective_until') }}</label>
                            <div class="govuk-hint">{{ __('event_safety.govuk.effective_until_hint') }}</div>
                            <input class="govuk-input govuk-input--width-20" id="review-until" name="effective_until" type="datetime-local">
                        </div>
                        <p class="govuk-hint">{{ __('event_safety.govuk.no_notes') }}</p>
                        <button class="govuk-button" data-module="govuk-button">{{ __('event_safety.govuk.save_review') }}</button>
                    </form>
                @else
                    <p class="govuk-body">{{ __('event_safety.govuk.no_people') }}</p>
                    <p class="govuk-body"><a class="govuk-link" href="{{ route('govuk-alpha.events.people', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">{{ __('event_safety.govuk.open_people') }}</a></p>
                @endif

                <h3 class="govuk-heading-m">{{ __('event_safety.govuk.ledger_title') }}</h3>
                @forelse (($reviews['items'] ?? []) as $item)
                    <div class="govuk-summary-card">
                        <div class="govuk-summary-card__title-wrapper">
                            <h4 class="govuk-summary-card__title">{{ $item['member']['display_name'] ?: __('event_safety.govuk.member_fallback', ['id' => $item['member']['id']]) }}</h4>
                        </div>
                        <div class="govuk-summary-card__content">
                            <dl class="govuk-summary-list">
                                <div class="govuk-summary-list__row"><dt class="govuk-summary-list__key">{{ __('event_safety.govuk.review_decision') }}</dt><dd class="govuk-summary-list__value">{{ __('event_safety.govuk.decisions.' . $item['denial']['decision']) }}</dd></div>
                                <div class="govuk-summary-list__row"><dt class="govuk-summary-list__key">{{ __('event_safety.govuk.review_reason') }}</dt><dd class="govuk-summary-list__value">{{ $localeReason((string) $item['denial']['reason_code']) }}</dd></div>
                                <div class="govuk-summary-list__row"><dt class="govuk-summary-list__key">{{ __('event_safety.govuk.review_status') }}</dt><dd class="govuk-summary-list__value">{{ __('event_safety.govuk.review_states.' . $item['denial']['status']) }}</dd></div>
                                <div class="govuk-summary-list__row"><dt class="govuk-summary-list__key">{{ __('event_safety.govuk.reviewed_by') }}</dt><dd class="govuk-summary-list__value">{{ $item['reviewer']['display_name'] }}</dd></div>
                            </dl>
                            @if (($item['denial']['status'] ?? '') === 'active')
                                <form method="post" action="{{ route('govuk-alpha.events.safety.update', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="withdraw_review">
                                    <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                                    <input type="hidden" name="denial_id" value="{{ $item['denial']['id'] }}">
                                    <input type="hidden" name="expected_version" value="{{ $item['denial']['decision_version'] }}">
                                    <button class="govuk-button govuk-button--warning" data-module="govuk-button">{{ __('event_safety.govuk.withdraw_review') }}</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="govuk-body">{{ __('event_safety.govuk.ledger_empty') }}</p>
                @endforelse
            </section>
        @endif
    @endif
@endsection
