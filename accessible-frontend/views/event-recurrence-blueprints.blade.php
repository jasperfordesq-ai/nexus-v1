{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $sectionKeys = ['agenda', 'ticket_types', 'registration', 'safety', 'staff'];
        $countKeys = [
            'sessions', 'speakers', 'resources', 'ticket_types',
            'registration_settings', 'published_forms', 'form_questions',
            'safety_requirements', 'staff_assignments',
        ];
        $historyItems = is_array($history['items'] ?? null) ? $history['items'] : [];
        $nextBeforeVersion = isset($history['next_before_version'])
            ? (int) $history['next_before_version']
            : null;
        $formatDate = static function (mixed $value): string {
            try {
                return \Carbon\CarbonImmutable::parse((string) $value)
                    ->locale(app()->getLocale())
                    ->isoFormat('LLL');
            } catch (\Throwable) {
                return __('event_recurrence_blueprints.time_unknown');
            }
        };
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">{{ __('govuk_alpha_events.common.back_to_event') }}</a>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-xl">{{ $event['title'] }}</span>
            <h1 class="govuk-heading-xl">{{ __('event_recurrence_blueprints.title') }}</h1>
            <p class="govuk-body-l">{{ __('event_recurrence_blueprints.description') }}</p>

            @if ($status === 'created' && $statusVersion !== null)
                <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="definition-created-title">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="definition-created-title">{{ __('event_recurrence_blueprints.success_created_title') }}</h2>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">{{ __('event_recurrence_blueprints.success_created_description', ['version' => $statusVersion]) }}</p>
                    </div>
                </div>
            @elseif ($status === 'replayed' && $statusVersion !== null)
                <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="definition-replayed-title">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="definition-replayed-title">{{ __('event_recurrence_blueprints.success_replay_title') }}</h2>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">{{ __('event_recurrence_blueprints.success_replay_description', ['version' => $statusVersion]) }}</p>
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_events.common.error_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <ul class="govuk-list govuk-error-summary__list">
                                @foreach ($errors->all() as $error)
                                    <li><a href="#definition-sections">{{ $error }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <div class="govuk-inset-text">
                <strong>{{ __('event_recurrence_blueprints.definition_only_title') }}</strong><br>
                {{ __('event_recurrence_blueprints.definition_only_description') }}
            </div>

            <h2 class="govuk-heading-m">{{ __('event_recurrence_blueprints.effective_from_label') }}</h2>
            <p class="govuk-body"><code>{{ $recurrenceId }}</code></p>
            <p class="govuk-hint">{{ __('event_recurrence_blueprints.effective_from_help') }}</p>

            <form method="post" action="{{ route('govuk-alpha.events.recurrence-definitions.preview', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}" autocomplete="off">
                @csrf
                <div class="govuk-form-group{{ $errors->has('sections') ? ' govuk-form-group--error' : '' }}" id="definition-sections">
                    <fieldset class="govuk-fieldset" aria-describedby="definition-sections-hint">
                        <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                            {{ __('event_recurrence_blueprints.sections_title') }}
                        </legend>
                        <div id="definition-sections-hint" class="govuk-hint">{{ __('event_recurrence_blueprints.sections_description') }}</div>
                        @if ($errors->has('sections'))
                            <p class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_events.common.error_prefix') }}</span> {{ $errors->first('sections') }}</p>
                        @endif
                        <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                            @foreach ($sectionKeys as $section)
                                @php
                                    $permitted = !empty($allowedSections[$section]);
                                @endphp
                                <div class="govuk-checkboxes__item">
                                    <input
                                        class="govuk-checkboxes__input"
                                        id="definition-section-{{ $section }}"
                                        name="sections[]"
                                        type="checkbox"
                                        value="{{ $section }}"
                                        @checked(!empty($selectedSections[$section]))
                                        @disabled(!$permitted)
                                    >
                                    <label class="govuk-label govuk-checkboxes__label" for="definition-section-{{ $section }}">
                                        {{ __('event_recurrence_blueprints.sections.' . $section . '.label') }}
                                    </label>
                                    <div class="govuk-hint govuk-checkboxes__hint">
                                        {{ __('event_recurrence_blueprints.sections.' . $section . '.description') }}
                                        @unless($permitted)
                                            {{ __('event_recurrence_blueprints.section_not_permitted') }}
                                        @endunless
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                </div>
                <button class="govuk-button" data-module="govuk-button" type="submit">{{ __('event_recurrence_blueprints.preview_button') }}</button>
            </form>

            @if (is_array($preview))
                @php
                    $previewCounts = is_array($preview['counts'] ?? null) ? $preview['counts'] : [];
                    $previewConflicts = is_array($preview['conflicts'] ?? null) ? $preview['conflicts'] : [];
                @endphp
                <hr class="govuk-section-break govuk-section-break--xl govuk-section-break--visible">
                <h2 class="govuk-heading-l">{{ __('event_recurrence_blueprints.preview_title') }}</h2>
                <p class="govuk-body">{{ __('event_recurrence_blueprints.preview_description') }}</p>

                <h3 class="govuk-heading-m">{{ __('event_recurrence_blueprints.history_sections') }}</h3>
                <ul class="govuk-list govuk-list--bullet">
                    @foreach ($sectionKeys as $section)
                        @if (!empty($preview['selected_sections'][$section]))
                            <li>{{ __('event_recurrence_blueprints.sections.' . $section . '.label') }}</li>
                        @endif
                    @endforeach
                </ul>

                <dl class="govuk-summary-list">
                    @forelse ($countKeys as $countKey)
                        @if ((int) ($previewCounts[$countKey] ?? 0) > 0)
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">{{ __('event_recurrence_blueprints.counts.' . $countKey) }}</dt>
                                <dd class="govuk-summary-list__value">{{ (int) $previewCounts[$countKey] }}</dd>
                            </div>
                        @endif
                    @empty
                    @endforelse
                    @if (array_sum(array_map('intval', $previewCounts)) === 0)
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">{{ __('event_recurrence_blueprints.preview_title') }}</dt>
                            <dd class="govuk-summary-list__value">{{ __('event_recurrence_blueprints.counts.none') }}</dd>
                        </div>
                    @endif
                </dl>

                <p class="govuk-body">{{ __('event_recurrence_blueprints.preview_expires', ['date' => $formatDate($preview['preview_expires_at'] ?? null)]) }}</p>

                @if ($previewConflicts !== [] || empty($preview['can_commit']))
                    <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                        <div role="alert">
                            <h2 class="govuk-error-summary__title">{{ __('event_recurrence_blueprints.conflicts_title') }}</h2>
                            <div class="govuk-error-summary__body">
                                <ul class="govuk-list govuk-list--bullet">
                                    @foreach ($previewConflicts as $conflict)
                                        @php
                                            $conflictCode = (string) ($conflict['code'] ?? 'unknown');
                                            $conflictKey = 'event_recurrence_blueprints.conflicts.' . $conflictCode;
                                            $section = (string) ($conflict['section'] ?? 'agenda');
                                        @endphp
                                        <li>{{ \Illuminate\Support\Facades\Lang::has($conflictKey)
                                            ? __($conflictKey, [
                                                'count' => (int) ($conflict['count'] ?? 0),
                                                'section' => __('event_recurrence_blueprints.sections.' . $section . '.label'),
                                            ])
                                            : __('event_recurrence_blueprints.errors.preview_error.description') }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @else
                    @if (!empty($preview['selected_sections']['staff']))
                        <div class="govuk-warning-text">
                            <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                            <strong class="govuk-warning-text__text">
                                <span class="govuk-visually-hidden">{{ __('govuk_alpha_events.common.warning') }}</span>
                                {{ __('event_recurrence_blueprints.staff_risk_description') }}
                            </strong>
                        </div>
                    @endif
                    <form method="post" action="{{ route('govuk-alpha.events.recurrence-definitions.commit', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}" autocomplete="off">
                        @csrf
                        <input type="hidden" name="preview_token" value="{{ $preview['preview_token'] }}">
                        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                        @foreach ($sectionKeys as $section)
                            @if (!empty($preview['selected_sections'][$section]))
                                <input type="hidden" name="sections[]" value="{{ $section }}">
                            @endif
                        @endforeach
                        <div class="govuk-checkboxes govuk-!-margin-bottom-5" data-module="govuk-checkboxes">
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="confirm-definition-version" name="confirm_definition_version" type="checkbox" value="1" required>
                                <label class="govuk-label govuk-checkboxes__label" for="confirm-definition-version">{{ __('event_recurrence_blueprints.confirm_ack') }}</label>
                                <div class="govuk-hint govuk-checkboxes__hint">{{ __('event_recurrence_blueprints.confirm_ack_description') }}</div>
                            </div>
                        </div>
                        <button class="govuk-button" data-module="govuk-button" type="submit">{{ __('event_recurrence_blueprints.commit_button') }}</button>
                    </form>
                @endif
            @endif

            <hr class="govuk-section-break govuk-section-break--xl govuk-section-break--visible">
            <h2 class="govuk-heading-l">{{ __('event_recurrence_blueprints.history_title') }}</h2>
            <p class="govuk-body">{{ __('event_recurrence_blueprints.history_description') }}</p>

            @if ($historyError)
                <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('event_recurrence_blueprints.history_error_title') }}</h2>
                        <div class="govuk-error-summary__body"><p>{{ __('event_recurrence_blueprints.history_error_description') }}</p></div>
                    </div>
                </div>
            @elseif ($historyItems === [])
                <h3 class="govuk-heading-m">{{ __('event_recurrence_blueprints.history_empty_title') }}</h3>
                <p class="govuk-body">{{ __('event_recurrence_blueprints.history_empty_description') }}</p>
            @else
                <ol class="govuk-list" aria-label="{{ __('event_recurrence_blueprints.history_list_label') }}">
                    @foreach ($historyItems as $item)
                        <li class="govuk-summary-card govuk-!-margin-bottom-6">
                            <div class="govuk-summary-card__title-wrapper">
                                <h3 class="govuk-summary-card__title">{{ __('event_recurrence_blueprints.history_version', ['version' => (int) $item['blueprint_version']]) }}</h3>
                            </div>
                            <div class="govuk-summary-card__content">
                                <dl class="govuk-summary-list">
                                    <div class="govuk-summary-list__row">
                                        <dt class="govuk-summary-list__key">{{ __('event_recurrence_blueprints.immutable') }}</dt>
                                        <dd class="govuk-summary-list__value">{{ __('govuk_alpha.cookie_settings.yes') }}</dd>
                                    </div>
                                    <div class="govuk-summary-list__row">
                                        <dt class="govuk-summary-list__key">{{ __('event_lifecycle_history.recorded_at') }}</dt>
                                        <dd class="govuk-summary-list__value">{{ $formatDate($item['created_at'] ?? null) }}</dd>
                                    </div>
                                    <div class="govuk-summary-list__row">
                                        <dt class="govuk-summary-list__key">{{ __('event_recurrence_blueprints.effective_from_label') }}</dt>
                                        <dd class="govuk-summary-list__value"><code>{{ $item['effective_from_recurrence_id'] }}</code></dd>
                                    </div>
                                    <div class="govuk-summary-list__row">
                                        <dt class="govuk-summary-list__key">{{ __('event_recurrence_blueprints.history_sections') }}</dt>
                                        <dd class="govuk-summary-list__value">
                                            <ul class="govuk-list govuk-list--bullet govuk-!-margin-bottom-0">
                                                @foreach ($sectionKeys as $section)
                                                    @if (!empty($item['selected_sections'][$section]))
                                                        <li>{{ __('event_recurrence_blueprints.sections.' . $section . '.label') }}</li>
                                                    @endif
                                                @endforeach
                                            </ul>
                                        </dd>
                                    </div>
                                    @foreach ($countKeys as $countKey)
                                        @if ((int) ($item['counts'][$countKey] ?? 0) > 0)
                                            <div class="govuk-summary-list__row">
                                                <dt class="govuk-summary-list__key">{{ __('event_recurrence_blueprints.counts.' . $countKey) }}</dt>
                                                <dd class="govuk-summary-list__value">{{ (int) $item['counts'][$countKey] }}</dd>
                                            </div>
                                        @endif
                                    @endforeach
                                </dl>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif

            @if ($nextBeforeVersion !== null)
                <nav class="govuk-pagination govuk-pagination--block" aria-label="{{ __('event_recurrence_blueprints.history_list_label') }}">
                    <div class="govuk-pagination__next">
                        <a class="govuk-link govuk-pagination__link" rel="next" href="{{ route('govuk-alpha.events.recurrence-definitions.index', ['tenantSlug' => $tenantSlug, 'id' => $event['id'], 'before_version' => $nextBeforeVersion]) }}">
                            <span class="govuk-pagination__link-title">{{ __('event_recurrence_blueprints.history_load_more') }}</span>
                        </a>
                    </div>
                </nav>
            @endif
        </div>
    </div>
@endsection
