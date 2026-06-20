{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $cookieService = $tenant['name'] ?? __('govuk_alpha.service_name');
        $bag = $errors ?? new \Illuminate\Support\MessageBag();
        $summaryVal = old('summary', '');
        $descriptionVal = old('description', '');
        $impactVal = old('impact', '');
        $impacts = $impacts ?? ['blocked', 'major', 'minor', 'cosmetic'];
        $fieldErr = [
            'summary' => $bag->first('summary'),
            'description' => $bag->first('description'),
            'impact' => $bag->first('impact'),
        ];
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-l">{{ __('govuk_alpha.report_problem.caption', ['service' => $cookieService]) }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.report_problem.title') }}</h1>

            @if (($status ?? '') === 'sent')
                <div class="govuk-panel govuk-panel--confirmation">
                    <h2 class="govuk-panel__title">{{ __('govuk_alpha.report_problem.success_title') }}</h2>
                    @if (!empty($reference))
                        <div class="govuk-panel__body">{{ __('govuk_alpha.report_problem.success_body', ['ref' => $reference]) }}</div>
                    @endif
                </div>
                <p class="govuk-body govuk-!-margin-top-6">
                    <a class="govuk-link" href="{{ route('govuk-alpha.home', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.report_problem.back') }}</a>
                </p>
            @else
                @if ($bag->any())
                    <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                        <div role="alert">
                            <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                            <div class="govuk-error-summary__body">
                                <ul class="govuk-list govuk-error-summary__list">
                                    @foreach (['summary', 'description', 'impact'] as $f)
                                        @if (!empty($fieldErr[$f]))
                                            <li><a href="#{{ $f }}">{{ $fieldErr[$f] }}</a></li>
                                        @endif
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @elseif (($status ?? '') === 'failed')
                    <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="report-error-title">
                        <div class="govuk-notification-banner__header">
                            <h2 class="govuk-notification-banner__title" id="report-error-title">{{ __('govuk_alpha.states.error_title') }}</h2>
                        </div>
                        <div class="govuk-notification-banner__content">
                            <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.contact.error_fallback') }}</p>
                        </div>
                    </div>
                @endif

                <p class="govuk-body-l">{{ __('govuk_alpha.report_problem.intro') }}</p>

                <form method="post" action="{{ route('govuk-alpha.report-problem.store', ['tenantSlug' => $tenantSlug]) }}" novalidate>
                    @csrf
                    <input type="hidden" name="page_url" value="{{ $pageUrl ?? '' }}">

                    <p class="govuk-body">
                        {{ __('govuk_alpha.report_problem.page_label') }}:
                        <span class="govuk-!-font-weight-bold">{{ $pageUrl ?? '' }}</span>
                    </p>

                    <div class="govuk-form-group{{ $fieldErr['summary'] ? ' govuk-form-group--error' : '' }}">
                        <label class="govuk-label govuk-label--s" for="summary">{{ __('govuk_alpha.report_problem.summary_label') }}</label>
                        <div id="summary-hint" class="govuk-hint">{{ __('govuk_alpha.report_problem.summary_hint') }}</div>
                        @if ($fieldErr['summary'])
                            <p id="summary-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $fieldErr['summary'] }}</p>
                        @endif
                        <input class="govuk-input{{ $fieldErr['summary'] ? ' govuk-input--error' : '' }}" id="summary" name="summary" type="text" maxlength="180" value="{{ $summaryVal }}" aria-describedby="summary-hint{{ $fieldErr['summary'] ? ' summary-error' : '' }}">
                    </div>

                    <div class="govuk-form-group{{ $fieldErr['description'] ? ' govuk-form-group--error' : '' }}">
                        <label class="govuk-label govuk-label--s" for="description">{{ __('govuk_alpha.report_problem.description_label') }}</label>
                        <div id="description-hint" class="govuk-hint">{{ __('govuk_alpha.report_problem.description_hint') }}</div>
                        @if ($fieldErr['description'])
                            <p id="description-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $fieldErr['description'] }}</p>
                        @endif
                        <textarea class="govuk-textarea{{ $fieldErr['description'] ? ' govuk-textarea--error' : '' }}" id="description" name="description" rows="5" maxlength="5000" aria-describedby="description-hint{{ $fieldErr['description'] ? ' description-error' : '' }}">{{ $descriptionVal }}</textarea>
                    </div>

                    <div class="govuk-form-group{{ $fieldErr['impact'] ? ' govuk-form-group--error' : '' }}">
                        <fieldset class="govuk-fieldset" aria-describedby="{{ $fieldErr['impact'] ? 'impact-error' : '' }}">
                            <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha.report_problem.impact_legend') }}</legend>
                            @if ($fieldErr['impact'])
                                <p id="impact-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $fieldErr['impact'] }}</p>
                            @endif
                            <div class="govuk-radios" data-module="govuk-radios" id="impact">
                                @foreach ($impacts as $impactKey)
                                    <div class="govuk-radios__item">
                                        <input class="govuk-radios__input" id="impact-{{ $impactKey }}" name="impact" type="radio" value="{{ $impactKey }}" @checked($impactVal === $impactKey)>
                                        <label class="govuk-label govuk-radios__label" for="impact-{{ $impactKey }}">{{ __('govuk_alpha.report_problem.impacts.' . $impactKey) }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>
                    </div>

                    <button class="govuk-button" data-module="govuk-button" type="submit">{{ __('govuk_alpha.report_problem.submit') }}</button>
                </form>
            @endif
        </div>
    </div>
@endsection
