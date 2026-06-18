{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $requestTypes = $requestTypes ?? [];
        $requests = $requests ?? [];
        $successStates = ['gdpr-requested'];
        $infoStates = ['gdpr-duplicate'];
        $errorStates = ['gdpr-invalid', 'gdpr-failed'];
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <a class="govuk-back-link" href="{{ route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_settings.common.back_to_settings') }}</a>

            @if (in_array($status, $successStates, true))
                <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="data-rights-status-title">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="data-rights-status-title">{{ __('govuk_alpha_settings.common.success_title') }}</h2>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">{{ __('govuk_alpha_settings.states.' . $status) }}</p>
                    </div>
                </div>
            @elseif (in_array($status, $infoStates, true))
                <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="data-rights-status-title">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="data-rights-status-title">{{ __('govuk_alpha.states.important') }}</h2>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">{{ __('govuk_alpha_settings.states.' . $status) }}</p>
                    </div>
                </div>
            @elseif (in_array($status, $errorStates, true))
                <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_settings.common.error_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <ul class="govuk-list govuk-error-summary__list">
                                <li><a href="#request_type">{{ __('govuk_alpha_settings.states.' . $status) }}</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <span class="govuk-caption-xl">{{ __('govuk_alpha_settings.gdpr.caption') }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha_settings.gdpr.title') }}</h1>
            <p class="govuk-body-l">{{ __('govuk_alpha_settings.gdpr.description') }}</p>

            <section aria-labelledby="data-rights-request-heading" id="request">
                <h2 class="govuk-heading-l" id="data-rights-request-heading">{{ __('govuk_alpha_settings.gdpr.request_heading') }}</h2>
                <p class="govuk-body">{{ __('govuk_alpha_settings.gdpr.request_description') }}</p>

                <form method="post" action="{{ route('govuk-alpha.settings.data-rights.request', ['tenantSlug' => $tenantSlug]) }}" novalidate>
                    @csrf
                    <div class="govuk-form-group">
                        <fieldset class="govuk-fieldset">
                            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                                <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha_settings.gdpr.type_label') }}</h2>
                            </legend>
                            <div class="govuk-radios" data-module="govuk-radios">
                                @foreach ($requestTypes as $typeIndex => $type)
                                    <div class="govuk-radios__item">
                                        <input class="govuk-radios__input" id="{{ $typeIndex === 0 ? 'request_type' : 'request_type_' . $type }}" name="request_type" type="radio" value="{{ $type }}" aria-describedby="request_type_{{ $type }}-hint">
                                        <label class="govuk-label govuk-radios__label" for="{{ $typeIndex === 0 ? 'request_type' : 'request_type_' . $type }}">{{ __('govuk_alpha_settings.gdpr.types.' . $type) }}</label>
                                        <div id="request_type_{{ $type }}-hint" class="govuk-hint govuk-radios__hint">{{ __('govuk_alpha_settings.gdpr.type_descriptions.' . $type) }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>
                    </div>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="notes">{{ __('govuk_alpha_settings.gdpr.notes_label') }}</label>
                        <div id="notes-hint" class="govuk-hint">{{ __('govuk_alpha_settings.gdpr.notes_hint') }}</div>
                        <textarea class="govuk-textarea" id="notes" name="notes" rows="4" maxlength="2000" aria-describedby="notes-hint"></textarea>
                    </div>

                    <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_settings.gdpr.submit_button') }}</button>
                </form>
            </section>

            <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

            <section aria-labelledby="your-requests-heading" id="your-requests">
                <h2 class="govuk-heading-l" id="your-requests-heading">{{ __('govuk_alpha_settings.gdpr.your_requests_heading') }}</h2>
                @if (empty($requests))
                    <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_settings.gdpr.your_requests_empty') }}</p></div>
                @else
                    <table class="govuk-table">
                        <caption class="govuk-table__caption govuk-table__caption--s govuk-visually-hidden">{{ __('govuk_alpha_settings.gdpr.your_requests_heading') }}</caption>
                        <thead class="govuk-table__head">
                            <tr class="govuk-table__row">
                                <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_settings.gdpr.type_label') }}</th>
                                <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_settings.gdpr.request_status_label') }}</th>
                                <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_settings.gdpr.request_date_label') }}</th>
                            </tr>
                        </thead>
                        <tbody class="govuk-table__body">
                            @foreach ($requests as $req)
                                @php
                                    $reqType = (string) ($req['type'] ?? '');
                                    $reqStatus = (string) ($req['status'] ?? 'pending');
                                    $reqDateRaw = (string) ($req['requested_at'] ?? '');
                                    $reqDate = $reqDateRaw !== '' ? \Illuminate\Support\Carbon::parse($reqDateRaw)->translatedFormat('j F Y') : '—';
                                    $statusKey = 'govuk_alpha_settings.gdpr.request_status.' . $reqStatus;
                                    $statusLabel = \Illuminate\Support\Facades\Lang::has($statusKey) ? __($statusKey) : \Illuminate\Support\Str::headline($reqStatus);
                                @endphp
                                <tr class="govuk-table__row">
                                    <td class="govuk-table__cell">{{ __('govuk_alpha_settings.gdpr.types.' . $reqType) }}</td>
                                    <td class="govuk-table__cell">{{ $statusLabel }}</td>
                                    <td class="govuk-table__cell">{{ $reqDate }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        </div>
    </div>
@endsection
