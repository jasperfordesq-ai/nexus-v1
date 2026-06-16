{{-- Copyright (c) 2024-2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $formatDate = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y') : null;
        $certificates = $certificates ?? [];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_volunteering') }}</a>

    @if ($status === 'certificate-generated')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-labelledby="certificate-success-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="certificate-success-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.vol_depth.certificate_generated') }}</p>
            </div>
        </div>
    @elseif ($status === 'certificate-no-hours')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p>{{ __('govuk_alpha.vol_depth.certificate_no_hours') }}</p>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ __('govuk_alpha.volunteering.title') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.vol_depth.certificates_title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.vol_depth.certificates_description') }}</p>

    <form method="post" action="{{ route('govuk-alpha.volunteering.certificates.generate', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-7">
        @csrf
        <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.vol_depth.certificate_generate') }}</button>
    </form>

    @if ($error)
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p>{{ $error }}</p>
                </div>
            </div>
        </div>
    @elseif (empty($certificates))
        <div class="govuk-inset-text">
            <h2 class="govuk-heading-m">{{ __('govuk_alpha.vol_depth.certificates_empty_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha.vol_depth.certificates_empty') }}</p>
        </div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($certificates as $certificate)
                @php
                    $code = (string) ($certificate['verification_code'] ?? '');
                    $organizations = is_array($certificate['organizations'] ?? null) ? $certificate['organizations'] : [];
                    $rangeStart = $formatDate($certificate['date_range']['start'] ?? null);
                    $rangeEnd = $formatDate($certificate['date_range']['end'] ?? null);
                @endphp
                <article class="nexus-alpha-card">
                    <h2 class="govuk-heading-m govuk-!-margin-bottom-2">{{ __('govuk_alpha.vol_depth.certificate_hours', ['hours' => number_format((float) ($certificate['total_hours'] ?? 0), 1)]) }}</h2>
                    <dl class="govuk-summary-list govuk-!-margin-bottom-3">
                        @if ($rangeStart && $rangeEnd)
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.vol_depth.certificate_period') }}</dt>
                                <dd class="govuk-summary-list__value">{{ $rangeStart }} &ndash; {{ $rangeEnd }}</dd>
                            </div>
                        @endif
                        @if (!empty($certificate['generated_at']))
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.vol_depth.certificate_generated_on') }}</dt>
                                <dd class="govuk-summary-list__value">{{ $formatDate($certificate['generated_at']) }}</dd>
                            </div>
                        @endif
                        @if ($code !== '')
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.vol_depth.certificate_code') }}</dt>
                                <dd class="govuk-summary-list__value"><span class="govuk-!-font-weight-bold">{{ $code }}</span></dd>
                            </div>
                        @endif
                    </dl>
                    @if (!empty($organizations))
                        <p class="govuk-body govuk-!-margin-bottom-1"><strong>{{ __('govuk_alpha.vol_depth.certificate_organisations') }}</strong></p>
                        <ul class="govuk-list govuk-list--bullet">
                            @foreach ($organizations as $org)
                                <li>{{ __('govuk_alpha.vol_depth.certificate_org_hours', ['name' => $org['name'] ?? __('govuk_alpha.vol_depth.certificate_independent'), 'hours' => number_format((float) ($org['hours'] ?? 0), 1)]) }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($code !== '')
                        <a class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button" href="{{ route('govuk-alpha.volunteering.certificates.download', ['tenantSlug' => $tenantSlug, 'code' => $code]) }}">{{ __('govuk_alpha.vol_depth.certificate_download') }}</a>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
