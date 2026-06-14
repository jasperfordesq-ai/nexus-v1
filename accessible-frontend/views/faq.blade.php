{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-xl">{{ __('govuk_alpha.faq.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.faq.title') }}</h1>
            <p class="govuk-body-l">{{ __('govuk_alpha.faq.intro') }}</p>

            <div class="govuk-accordion" data-module="govuk-accordion" id="faq-accordion">
                @foreach (['1', '2', '3', '4', '5'] as $i)
                    <div class="govuk-accordion__section">
                        <div class="govuk-accordion__section-header">
                            <h2 class="govuk-accordion__section-heading">
                                <span class="govuk-accordion__section-button" id="faq-heading-{{ $i }}">{{ __('govuk_alpha.faq.q' . $i) }}</span>
                            </h2>
                        </div>
                        <div id="faq-content-{{ $i }}" class="govuk-accordion__section-content">
                            <p class="govuk-body">{{ __('govuk_alpha.faq.a' . $i) }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
