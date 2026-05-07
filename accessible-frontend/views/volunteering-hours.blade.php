{{-- Copyright (c) 2024-2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $formatDate = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y') : null;
        $summary = $summary ?? [];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_volunteering') }}</a>

    @if ($status === 'hours-created')
        <div class="govuk-notification-banner govuk-notification-banner--success" role="region" aria-labelledby="hours-success-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="hours-success-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.volunteering.hours_created') }}</p>
            </div>
        </div>
    @elseif ($status === 'hours-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p>{{ __('govuk_alpha.volunteering.hours_failed') }}</p>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ __('govuk_alpha.volunteering.title') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.volunteering.hours_title') }}</h1>

    <dl class="nexus-alpha-stat-grid">
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha.volunteering.approved_hours') }}</dt>
            <dd>{{ number_format((float) ($summary['total_approved_hours'] ?? $summary['approved_hours'] ?? 0), 1) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha.volunteering.pending_hours') }}</dt>
            <dd>{{ number_format((float) ($summary['pending_hours'] ?? 0), 1) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha.volunteering.this_month_hours') }}</dt>
            <dd>{{ number_format((float) ($summary['this_month_hours'] ?? 0), 1) }}</dd>
        </div>
    </dl>

    <form method="post" action="{{ route('govuk-alpha.volunteering.hours.store', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-top-7">
        @csrf
        <fieldset class="govuk-fieldset">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.volunteering.log_hours_title') }}</h2>
            </legend>
            <div class="govuk-grid-row">
                <div class="govuk-grid-column-one-half">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="organization_id">{{ __('govuk_alpha.volunteering.organization_label') }}</label>
                        <select class="govuk-select" id="organization_id" name="organization_id">
                            @foreach ($organizations as $organization)
                                <option value="{{ $organization['id'] }}">{{ $organization['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="govuk-grid-column-one-half">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="opportunity_id">{{ __('govuk_alpha.volunteering.opportunity_label') }}</label>
                        <select class="govuk-select" id="opportunity_id" name="opportunity_id">
                            <option value="">{{ __('govuk_alpha.volunteering.no_related_opportunity') }}</option>
                            @foreach ($applications as $application)
                                <option value="{{ $application['opportunity']['id'] }}">{{ $application['opportunity']['title'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="govuk-grid-row">
                <div class="govuk-grid-column-one-half">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="date">{{ __('govuk_alpha.volunteering.date_label') }}</label>
                        <input class="govuk-input govuk-input--width-10" id="date" name="date" type="date" value="{{ now()->toDateString() }}">
                    </div>
                </div>
                <div class="govuk-grid-column-one-half">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="hours">{{ __('govuk_alpha.volunteering.hours_label') }}</label>
                        <input class="govuk-input govuk-input--width-5" id="hours" name="hours" type="number" min="0.25" max="24" step="0.25">
                    </div>
                </div>
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="description">{{ __('govuk_alpha.volunteering.description_label') }}</label>
                <textarea class="govuk-textarea" id="description" name="description" rows="5"></textarea>
            </div>
        </fieldset>
        <button class="govuk-button" data-module="govuk-button" @disabled(empty($organizations))>{{ __('govuk_alpha.actions.log_hours') }}</button>
    </form>

    <h2 class="govuk-heading-l govuk-!-margin-top-8">{{ __('govuk_alpha.volunteering.recent_hours_title') }}</h2>
    @if (empty($logs))
        <div class="govuk-inset-text">{{ __('govuk_alpha.volunteering.empty_hours') }}</div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($logs as $log)
                <article class="nexus-alpha-card">
                    <h3 class="govuk-heading-m govuk-!-margin-bottom-2">{{ $formatDate($log['date'] ?? $log['logged_at'] ?? $log['created_at'] ?? null) }}</h3>
                    <dl class="nexus-alpha-inline-list">
                        <div>
                            <dt>{{ __('govuk_alpha.volunteering.hours_label') }}</dt>
                            <dd>{{ number_format((float) ($log['hours'] ?? 0), 1) }}</dd>
                        </div>
                        @if (!empty($log['organization']['name']))
                            <div>
                                <dt>{{ __('govuk_alpha.volunteering.organization') }}</dt>
                                <dd>{{ $log['organization']['name'] }}</dd>
                            </div>
                        @endif
                        @if (!empty($log['status']))
                            <div>
                                <dt>{{ __('govuk_alpha.volunteering.status') }}</dt>
                                <dd>{{ __('govuk_alpha.volunteering.status_values.' . $log['status']) }}</dd>
                            </div>
                        @endif
                    </dl>
                    @if (!empty($log['description']))
                        <p class="govuk-body govuk-!-margin-top-3">{{ $log['description'] }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
