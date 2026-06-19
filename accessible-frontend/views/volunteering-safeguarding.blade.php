{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $trainings     = $trainings ?? [];
        $incidents     = $incidents ?? [];
        $status        = $status ?? null;
        $subView       = $subView ?? 'training';
        $formatDate    = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y') : null;

        $trainingTypeLabel = [
            'children_first'    => __('govuk_alpha_volunteering.safeguarding.training_type_children_first'),
            'vulnerable_adults' => __('govuk_alpha_volunteering.safeguarding.training_type_vulnerable_adults'),
            'first_aid'         => __('govuk_alpha_volunteering.safeguarding.training_type_first_aid'),
            'manual_handling'   => __('govuk_alpha_volunteering.safeguarding.training_type_manual_handling'),
            'other'             => __('govuk_alpha_volunteering.safeguarding.training_type_other'),
        ];

        $trainingStatusTag = [
            'pending'  => 'govuk-tag--yellow',
            'verified' => 'govuk-tag--green',
            'expired'  => 'govuk-tag--grey',
            'rejected' => 'govuk-tag--red',
        ];
        $trainingStatusLabel = [
            'pending'  => __('govuk_alpha_volunteering.safeguarding.status_pending'),
            'verified' => __('govuk_alpha_volunteering.safeguarding.status_verified'),
            'expired'  => __('govuk_alpha_volunteering.safeguarding.status_expired'),
            'rejected' => __('govuk_alpha_volunteering.safeguarding.status_rejected'),
        ];

        $severityTag = [
            'low'      => 'govuk-tag--blue',
            'medium'   => 'govuk-tag--yellow',
            'high'     => 'govuk-tag--orange',
            'critical' => 'govuk-tag--red',
        ];
        $severityLabel = [
            'low'      => __('govuk_alpha_volunteering.safeguarding.severity_low'),
            'medium'   => __('govuk_alpha_volunteering.safeguarding.severity_medium'),
            'high'     => __('govuk_alpha_volunteering.safeguarding.severity_high'),
            'critical' => __('govuk_alpha_volunteering.safeguarding.severity_critical'),
        ];

        $incidentStatusTag = [
            'open'          => 'govuk-tag--yellow',
            'investigating' => 'govuk-tag--blue',
            'escalated'     => 'govuk-tag--red',
            'resolved'      => 'govuk-tag--green',
            'closed'        => 'govuk-tag--grey',
        ];
        $incidentStatusLabel = [
            'open'          => __('govuk_alpha_volunteering.safeguarding.incident_status_open'),
            'investigating' => __('govuk_alpha_volunteering.safeguarding.incident_status_investigating'),
            'escalated'     => __('govuk_alpha_volunteering.safeguarding.incident_status_escalated'),
            'resolved'      => __('govuk_alpha_volunteering.safeguarding.incident_status_resolved'),
            'closed'        => __('govuk_alpha_volunteering.safeguarding.incident_status_closed'),
        ];

        $successStatuses = ['training-added', 'incident-reported'];
        $errorStatuses   = [
            'training-type-required', 'training-name-required', 'training-date-required',
            'training-failed', 'incident-title-required', 'incident-description-too-short',
            'incident-failed',
        ];
        $errorMessages = [
            'training-type-required'          => __('govuk_alpha_volunteering.safeguarding.error_training_type_required'),
            'training-name-required'          => __('govuk_alpha_volunteering.safeguarding.error_training_name_required'),
            'training-date-required'          => __('govuk_alpha_volunteering.safeguarding.error_training_date_required'),
            'training-failed'                 => __('govuk_alpha_volunteering.safeguarding.error_training_failed'),
            'incident-title-required'         => __('govuk_alpha_volunteering.safeguarding.error_incident_title_required'),
            'incident-description-too-short'  => __('govuk_alpha_volunteering.safeguarding.error_incident_description_short'),
            'incident-failed'                 => __('govuk_alpha_volunteering.safeguarding.error_incident_failed'),
        ];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_volunteering.shared.back_to_volunteering') }}</a>

    {{-- Success banner --}}
    @if (in_array($status, $successStatuses, true))
        <div class="govuk-notification-banner govuk-notification-banner--success"
             data-module="govuk-notification-banner" role="alert"
             aria-labelledby="sg-success-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="sg-success-title">
                    {{ __('govuk_alpha_volunteering.shared.success_title') }}
                </h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">
                    @if ($status === 'training-added')
                        {{ __('govuk_alpha_volunteering.safeguarding.success_training_added') }}
                    @else
                        {{ __('govuk_alpha_volunteering.safeguarding.success_incident_reported') }}
                    @endif
                </p>
            </div>
        </div>
    @endif

    {{-- Error summary --}}
    @if (in_array($status, $errorStatuses, true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_volunteering.shared.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p>{{ $errorMessages[$status] ?? __('govuk_alpha_volunteering.safeguarding.error_generic') }}</p>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ __('govuk_alpha.volunteering.title') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_volunteering.safeguarding.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_volunteering.safeguarding.description') }}</p>

    {{-- GOV.UK-style tab navigation (no JS — plain anchors with ?tab= query param) --}}
    <nav class="govuk-!-margin-bottom-6" aria-label="{{ __('govuk_alpha_volunteering.safeguarding.tab_nav_label') }}">
        <ul class="govuk-list" style="display:flex;gap:1rem;flex-wrap:wrap;list-style:none;padding:0;margin:0;">
            <li>
                <a class="govuk-link {{ $subView === 'training' ? 'govuk-link--no-visited-state' : '' }}"
                   href="{{ route('govuk-alpha.volunteering.training', ['tenantSlug' => $tenantSlug]) }}"
                   @if ($subView === 'training') aria-current="page" @endif>
                    {{ __('govuk_alpha_volunteering.safeguarding.tab_training') }}
                </a>
            </li>
            <li>
                <a class="govuk-link {{ $subView === 'incidents' ? 'govuk-link--no-visited-state' : '' }}"
                   href="{{ route('govuk-alpha.volunteering.incidents', ['tenantSlug' => $tenantSlug]) }}"
                   @if ($subView === 'incidents') aria-current="page" @endif>
                    {{ __('govuk_alpha_volunteering.safeguarding.tab_incidents') }}
                </a>
            </li>
        </ul>
    </nav>

    {{-- ===== TRAINING RECORDS TAB ===== --}}
    @if ($subView === 'training')

        {{-- Log training form --}}
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_volunteering.safeguarding.add_training_title') }}</h2>
        <form method="POST"
              action="{{ route('govuk-alpha.volunteering.training.store', ['tenantSlug' => $tenantSlug]) }}"
              novalidate>
            @csrf

            {{-- Training type --}}
            <div class="govuk-form-group {{ in_array($status, ['training-type-required'], true) ? 'govuk-form-group--error' : '' }}">
                <label class="govuk-label govuk-label--s" for="training_type">
                    {{ __('govuk_alpha_volunteering.safeguarding.training_type_label') }}
                </label>
                @if ($status === 'training-type-required')
                    <p id="training-type-error" class="govuk-error-message">
                        <span class="govuk-visually-hidden">{{ __('govuk_alpha.forms.error_prefix') }}</span>
                        {{ __('govuk_alpha_volunteering.safeguarding.error_training_type_required') }}
                    </p>
                @endif
                <select class="govuk-select {{ in_array($status, ['training-type-required'], true) ? 'govuk-select--error' : '' }}"
                        id="training_type" name="training_type"
                        @if ($status === 'training-type-required') aria-describedby="training-type-error" @endif>
                    <option value="">{{ __('govuk_alpha_volunteering.safeguarding.training_type_choose') }}</option>
                    @foreach (['children_first', 'vulnerable_adults', 'first_aid', 'manual_handling', 'other'] as $typeKey)
                        <option value="{{ $typeKey }}">{{ $trainingTypeLabel[$typeKey] ?? $typeKey }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Training name --}}
            <div class="govuk-form-group {{ in_array($status, ['training-name-required'], true) ? 'govuk-form-group--error' : '' }}">
                <label class="govuk-label govuk-label--s" for="training_name">
                    {{ __('govuk_alpha_volunteering.safeguarding.training_name_label') }}
                </label>
                @if ($status === 'training-name-required')
                    <p id="training-name-error" class="govuk-error-message">
                        <span class="govuk-visually-hidden">{{ __('govuk_alpha.forms.error_prefix') }}</span>
                        {{ __('govuk_alpha_volunteering.safeguarding.error_training_name_required') }}
                    </p>
                @endif
                <input class="govuk-input {{ $status === 'training-name-required' ? 'govuk-input--error' : '' }}"
                       id="training_name" name="training_name" type="text" maxlength="255"
                       @if ($status === 'training-name-required') aria-describedby="training-name-error" @endif>
            </div>

            {{-- Provider --}}
            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="provider">
                    {{ __('govuk_alpha_volunteering.safeguarding.provider_label') }}
                    <span class="govuk-hint">{{ __('govuk_alpha_volunteering.shared.optional') }}</span>
                </label>
                <input class="govuk-input" id="provider" name="provider" type="text" maxlength="255">
            </div>

            {{-- Completed at --}}
            <div class="govuk-form-group {{ in_array($status, ['training-date-required'], true) ? 'govuk-form-group--error' : '' }}">
                <label class="govuk-label govuk-label--s" for="completed_at">
                    {{ __('govuk_alpha_volunteering.safeguarding.completed_at_label') }}
                </label>
                @if ($status === 'training-date-required')
                    <p id="completed-at-error" class="govuk-error-message">
                        <span class="govuk-visually-hidden">{{ __('govuk_alpha.forms.error_prefix') }}</span>
                        {{ __('govuk_alpha_volunteering.safeguarding.error_training_date_required') }}
                    </p>
                @endif
                <input class="govuk-input govuk-input--width-10 {{ $status === 'training-date-required' ? 'govuk-input--error' : '' }}"
                       id="completed_at" name="completed_at" type="date"
                       @if ($status === 'training-date-required') aria-describedby="completed-at-error" @endif>
            </div>

            {{-- Expires at --}}
            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="expires_at">
                    {{ __('govuk_alpha_volunteering.safeguarding.expires_at_label') }}
                    <span class="govuk-hint">{{ __('govuk_alpha_volunteering.shared.optional') }}</span>
                </label>
                <input class="govuk-input govuk-input--width-10" id="expires_at" name="expires_at" type="date">
            </div>

            <button type="submit" class="govuk-button govuk-!-margin-top-2">
                {{ __('govuk_alpha_volunteering.safeguarding.submit_training') }}
            </button>
        </form>

        <hr class="govuk-section-break govuk-section-break--m govuk-section-break--visible">

        {{-- Training records list --}}
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_volunteering.safeguarding.training_list_title') }}</h2>

        @if (empty($trainings))
            <div class="govuk-inset-text">
                {{ __('govuk_alpha_volunteering.safeguarding.no_training') }}
            </div>
        @else
            <table class="govuk-table">
                <caption class="govuk-table__caption govuk-visually-hidden">
                    {{ __('govuk_alpha_volunteering.safeguarding.training_list_title') }}
                </caption>
                <thead class="govuk-table__head">
                    <tr class="govuk-table__row">
                        <th class="govuk-table__header" scope="col">{{ __('govuk_alpha_volunteering.safeguarding.col_training_name') }}</th>
                        <th class="govuk-table__header" scope="col">{{ __('govuk_alpha_volunteering.safeguarding.col_type') }}</th>
                        <th class="govuk-table__header" scope="col">{{ __('govuk_alpha_volunteering.safeguarding.col_completed') }}</th>
                        <th class="govuk-table__header" scope="col">{{ __('govuk_alpha_volunteering.safeguarding.col_expires') }}</th>
                        <th class="govuk-table__header" scope="col">{{ __('govuk_alpha_volunteering.safeguarding.col_status') }}</th>
                    </tr>
                </thead>
                <tbody class="govuk-table__body">
                    @foreach ($trainings as $tr)
                        @php
                            $trType   = is_array($tr) ? ($tr['training_type'] ?? '') : ($tr->training_type ?? '');
                            $trName   = is_array($tr) ? ($tr['training_name'] ?? '') : ($tr->training_name ?? '');
                            $trProv   = is_array($tr) ? ($tr['provider'] ?? null) : ($tr->provider ?? null);
                            $trDone   = is_array($tr) ? ($tr['completed_at'] ?? null) : ($tr->completed_at ?? null);
                            $trExp    = is_array($tr) ? ($tr['expires_at'] ?? null) : ($tr->expires_at ?? null);
                            $trStatus = is_array($tr) ? ($tr['status'] ?? 'pending') : ($tr->status ?? 'pending');
                        @endphp
                        <tr class="govuk-table__row">
                            <td class="govuk-table__cell">
                                <strong>{{ $trName }}</strong>
                                @if ($trProv)
                                    <br><span class="govuk-hint govuk-!-font-size-16">{{ $trProv }}</span>
                                @endif
                            </td>
                            <td class="govuk-table__cell">
                                {{ $trainingTypeLabel[$trType] ?? \Illuminate\Support\Str::headline($trType) }}
                            </td>
                            <td class="govuk-table__cell">{{ $formatDate($trDone) ?? '—' }}</td>
                            <td class="govuk-table__cell">{{ $trExp ? $formatDate($trExp) : '—' }}</td>
                            <td class="govuk-table__cell">
                                <strong class="govuk-tag {{ $trainingStatusTag[$trStatus] ?? 'govuk-tag--grey' }}">
                                    {{ $trainingStatusLabel[$trStatus] ?? \Illuminate\Support\Str::headline($trStatus) }}
                                </strong>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

    {{-- ===== INCIDENT REPORTS TAB ===== --}}
    @else

        {{-- Report incident form --}}
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_volunteering.safeguarding.report_incident_title') }}</h2>
        <div class="govuk-inset-text" role="note">
            {{ __('govuk_alpha_volunteering.safeguarding.report_notice') }}
        </div>

        <form method="POST"
              action="{{ route('govuk-alpha.volunteering.incidents.store', ['tenantSlug' => $tenantSlug]) }}"
              novalidate>
            @csrf

            {{-- Title --}}
            <div class="govuk-form-group {{ in_array($status, ['incident-title-required'], true) ? 'govuk-form-group--error' : '' }}">
                <label class="govuk-label govuk-label--s" for="title">
                    {{ __('govuk_alpha_volunteering.safeguarding.incident_title_label') }}
                </label>
                @if ($status === 'incident-title-required')
                    <p id="incident-title-error" class="govuk-error-message">
                        <span class="govuk-visually-hidden">{{ __('govuk_alpha.forms.error_prefix') }}</span>
                        {{ __('govuk_alpha_volunteering.safeguarding.error_incident_title_required') }}
                    </p>
                @endif
                <input class="govuk-input {{ $status === 'incident-title-required' ? 'govuk-input--error' : '' }}"
                       id="title" name="title" type="text" maxlength="255"
                       @if ($status === 'incident-title-required') aria-describedby="incident-title-error" @endif>
            </div>

            {{-- Description --}}
            <div class="govuk-form-group {{ in_array($status, ['incident-description-too-short'], true) ? 'govuk-form-group--error' : '' }}">
                <label class="govuk-label govuk-label--s" for="description">
                    {{ __('govuk_alpha_volunteering.safeguarding.incident_description_label') }}
                </label>
                <div id="description-hint" class="govuk-hint">
                    {{ __('govuk_alpha_volunteering.safeguarding.incident_description_hint') }}
                </div>
                @if ($status === 'incident-description-too-short')
                    <p id="incident-desc-error" class="govuk-error-message">
                        <span class="govuk-visually-hidden">{{ __('govuk_alpha.forms.error_prefix') }}</span>
                        {{ __('govuk_alpha_volunteering.safeguarding.error_incident_description_short') }}
                    </p>
                @endif
                <textarea class="govuk-textarea {{ $status === 'incident-description-too-short' ? 'govuk-textarea--error' : '' }}"
                          id="description" name="description" rows="5" maxlength="2000"
                          aria-describedby="description-hint{{ $status === 'incident-description-too-short' ? ' incident-desc-error' : '' }}"></textarea>
            </div>

            {{-- Severity --}}
            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">
                        {{ __('govuk_alpha_volunteering.safeguarding.severity_label') }}
                    </legend>
                    <div class="govuk-radios govuk-radios--inline">
                        @foreach (['low', 'medium', 'high', 'critical'] as $sev)
                            <div class="govuk-radios__item">
                                <input class="govuk-radios__input" id="severity-{{ $sev }}"
                                       name="severity" type="radio" value="{{ $sev }}"
                                       @if ($sev === 'low') checked @endif>
                                <label class="govuk-label govuk-radios__label" for="severity-{{ $sev }}">
                                    {{ $severityLabel[$sev] ?? \Illuminate\Support\Str::headline($sev) }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            </div>

            {{-- Category --}}
            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="category">
                    {{ __('govuk_alpha_volunteering.safeguarding.category_label') }}
                    <span class="govuk-hint">{{ __('govuk_alpha_volunteering.shared.optional') }}</span>
                </label>
                <input class="govuk-input govuk-input--width-20" id="category" name="category" type="text" maxlength="100">
            </div>

            <button type="submit" class="govuk-button govuk-button--warning govuk-!-margin-top-2">
                {{ __('govuk_alpha_volunteering.safeguarding.submit_incident') }}
            </button>
        </form>

        <hr class="govuk-section-break govuk-section-break--m govuk-section-break--visible">

        {{-- Incident list --}}
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_volunteering.safeguarding.incidents_list_title') }}</h2>

        @if (empty($incidents))
            <div class="govuk-inset-text">
                {{ __('govuk_alpha_volunteering.safeguarding.no_incidents') }}
            </div>
        @else
            <table class="govuk-table">
                <caption class="govuk-table__caption govuk-visually-hidden">
                    {{ __('govuk_alpha_volunteering.safeguarding.incidents_list_title') }}
                </caption>
                <thead class="govuk-table__head">
                    <tr class="govuk-table__row">
                        <th class="govuk-table__header" scope="col">{{ __('govuk_alpha_volunteering.safeguarding.col_incident_title') }}</th>
                        <th class="govuk-table__header" scope="col">{{ __('govuk_alpha_volunteering.safeguarding.col_severity') }}</th>
                        <th class="govuk-table__header" scope="col">{{ __('govuk_alpha_volunteering.safeguarding.col_incident_status') }}</th>
                        <th class="govuk-table__header" scope="col">{{ __('govuk_alpha_volunteering.safeguarding.col_reported') }}</th>
                    </tr>
                </thead>
                <tbody class="govuk-table__body">
                    @foreach ($incidents as $inc)
                        @php
                            $incTitle  = is_array($inc) ? ($inc['title'] ?? '') : ($inc->title ?? '');
                            $incDesc   = is_array($inc) ? ($inc['description'] ?? '') : ($inc->description ?? '');
                            $incSev    = is_array($inc) ? ($inc['severity'] ?? 'low') : ($inc->severity ?? 'low');
                            $incStat   = is_array($inc) ? ($inc['status'] ?? 'open') : ($inc->status ?? 'open');
                            $incCat    = is_array($inc) ? ($inc['category'] ?? null) : ($inc->category ?? null);
                            $incDate   = is_array($inc) ? ($inc['created_at'] ?? null) : ($inc->created_at ?? null);
                        @endphp
                        <tr class="govuk-table__row">
                            <td class="govuk-table__cell">
                                <strong>{{ $incTitle }}</strong>
                                @if ($incCat && $incCat !== 'general')
                                    <br><span class="govuk-hint govuk-!-font-size-16">{{ $incCat }}</span>
                                @endif
                                @if ($incDesc)
                                    <br><span class="govuk-hint govuk-!-font-size-16">{{ \Illuminate\Support\Str::limit($incDesc, 100) }}</span>
                                @endif
                            </td>
                            <td class="govuk-table__cell">
                                <strong class="govuk-tag {{ $severityTag[$incSev] ?? 'govuk-tag--grey' }}">
                                    {{ $severityLabel[$incSev] ?? \Illuminate\Support\Str::headline($incSev) }}
                                </strong>
                            </td>
                            <td class="govuk-table__cell">
                                <strong class="govuk-tag {{ $incidentStatusTag[$incStat] ?? 'govuk-tag--grey' }}">
                                    {{ $incidentStatusLabel[$incStat] ?? \Illuminate\Support\Str::headline($incStat) }}
                                </strong>
                            </td>
                            <td class="govuk-table__cell">{{ $formatDate($incDate) ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif

@endsection
