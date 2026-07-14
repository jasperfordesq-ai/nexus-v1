{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $certificates = $certificates ?? [];
        $insuranceTypes = $insuranceTypes ?? [];
        $successStates = ['insurance-recorded'];
        $errorAnchors = [
            'insurance-type-invalid' => 'insurance_type',
            'insurance-expiry-required' => 'expiry_date',
            'insurance-file-forbidden' => 'insurance_type',
            'insurance-failed' => 'insurance_type',
        ];
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <a class="govuk-back-link" href="{{ route('govuk-alpha.profile.settings', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_settings.common.back_to_settings') }}</a>

            @if (in_array($status, $successStates, true))
                <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="insurance-status-title">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="insurance-status-title">{{ __('govuk_alpha_settings.common.success_title') }}</h2>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">{{ __('govuk_alpha_settings.states.' . $status) }}</p>
                    </div>
                </div>
            @elseif (array_key_exists($status ?? '', $errorAnchors))
                <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_settings.common.error_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <ul class="govuk-list govuk-error-summary__list">
                                <li><a href="#{{ $errorAnchors[$status] }}">{{ __('govuk_alpha_settings.states.' . $status) }}</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <span class="govuk-caption-xl">{{ __('govuk_alpha_settings.insurance.caption') }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha_settings.insurance.title') }}</h1>
            <p class="govuk-body-l">{{ __('govuk_alpha_settings.insurance.description') }}</p>

            <section aria-labelledby="insurance-certificates-heading" id="certificates">
                <h2 class="govuk-heading-l" id="insurance-certificates-heading">{{ __('govuk_alpha_settings.insurance.certificates_heading') }}</h2>
                @if (empty($certificates))
                    <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_settings.insurance.certificates_empty') }}</p></div>
                @else
                    @foreach ($certificates as $cert)
                        @php
                            $certType = (string) ($cert['insurance_type'] ?? 'other');
                            $typeKey = 'govuk_alpha_settings.insurance.types.' . $certType;
                            $typeLabel = \Illuminate\Support\Facades\Lang::has($typeKey) ? __($typeKey) : __('govuk_alpha_settings.insurance.types.other');
                            $certStatus = (string) ($cert['status'] ?? 'pending');
                            $statusKey = 'govuk_alpha_settings.insurance.statuses.' . $certStatus;
                            $statusLabel = \Illuminate\Support\Facades\Lang::has($statusKey) ? __($statusKey) : \Illuminate\Support\Str::headline($certStatus);
                            $statusTag = match ($certStatus) {
                                'verified' => 'govuk-tag--green',
                                'submitted', 'pending' => 'govuk-tag--yellow',
                                'rejected', 'revoked', 'expired' => 'govuk-tag--red',
                                default => 'govuk-tag--grey',
                            };
                            $provider = trim((string) ($cert['provider_name'] ?? ''));
                            $expiryRaw = trim((string) ($cert['expiry_date'] ?? ''));
                            $expiry = $expiryRaw !== '' ? \Illuminate\Support\Carbon::parse($expiryRaw)->translatedFormat('j F Y') : null;
                        @endphp
                        <div class="nexus-alpha-card govuk-!-margin-bottom-4">
                            <h3 class="govuk-heading-s govuk-!-margin-bottom-2">{{ $typeLabel }}
                                <strong class="govuk-tag {{ $statusTag }}">{{ $statusLabel }}</strong>
                            </h3>
                            <dl class="nexus-alpha-inline-list govuk-!-margin-bottom-0">
                                <div>
                                    <dt>{{ __('govuk_alpha_settings.insurance.provider_label_short') }}</dt>
                                    <dd>{{ $provider !== '' ? $provider : __('govuk_alpha_settings.insurance.no_provider') }}</dd>
                                </div>
                                <div>
                                    <dt>{{ __('govuk_alpha_settings.insurance.expiry_label_short') }}</dt>
                                    <dd>{{ $expiry ?? __('govuk_alpha_settings.insurance.no_expiry') }}</dd>
                                </div>
                            </dl>
                        </div>
                    @endforeach
                @endif
            </section>

            <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

            <section aria-labelledby="insurance-upload-heading" id="upload">
                <h2 class="govuk-heading-l" id="insurance-upload-heading">{{ __('govuk_alpha_settings.insurance.upload_heading') }}</h2>

                <form method="post" action="{{ route('govuk-alpha.settings.insurance.upload', ['tenantSlug' => $tenantSlug]) }}" novalidate>
                    @csrf
                    @php
                        $typeError = ($errorAnchors[$status ?? ''] ?? null) === 'insurance_type';
                    @endphp
                    <div class="govuk-form-group {{ $typeError ? 'govuk-form-group--error' : '' }}">
                        <label class="govuk-label" for="insurance_type">{{ __('govuk_alpha_settings.insurance.type_label') }}</label>
                        @if ($typeError)
                            <p id="insurance-type-error" class="govuk-error-message">
                                <span class="govuk-visually-hidden">{{ __('govuk_alpha_settings.common.error_title') }}:</span>
                                {{ __('govuk_alpha_settings.states.' . $status) }}
                            </p>
                        @endif
                        <select class="govuk-select" id="insurance_type" name="insurance_type" @if ($typeError) aria-describedby="insurance-type-error" @endif>
                            @foreach ($insuranceTypes as $type)
                                <option value="{{ $type }}">{{ __('govuk_alpha_settings.insurance.types.' . $type) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="provider_name">{{ __('govuk_alpha_settings.insurance.provider_label') }}</label>
                        <input class="govuk-input govuk-!-width-two-thirds" id="provider_name" name="provider_name" type="text" maxlength="255">
                    </div>

                    @php
                        $expiryError = ($errorAnchors[$status ?? ''] ?? null) === 'expiry_date';
                    @endphp
                    <div class="govuk-form-group {{ $expiryError ? 'govuk-form-group--error' : '' }}">
                        <label class="govuk-label" for="expiry_date">{{ __('govuk_alpha_settings.insurance.expiry_label') }}</label>
                        <div id="expiry-date-hint" class="govuk-hint">{{ __('govuk_alpha_settings.insurance.expiry_hint') }}</div>
                        @if ($expiryError)
                            <p id="expiry-date-error" class="govuk-error-message">
                                <span class="govuk-visually-hidden">{{ __('govuk_alpha_settings.common.error_title') }}:</span>
                                {{ __('govuk_alpha_settings.states.' . $status) }}
                            </p>
                        @endif
                        <input class="govuk-input govuk-input--width-10" id="expiry_date" name="expiry_date" type="date" required aria-describedby="expiry-date-hint{{ $expiryError ? ' expiry-date-error' : '' }}">
                    </div>

                    <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_settings.insurance.upload_button') }}</button>
                </form>
            </section>
        </div>
    </div>
@endsection
