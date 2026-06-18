{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $organizations = $organizations ?? [];
        $categories = $categories ?? [];
        $status = $status ?? null;
        $errorStates = [
            'opp-validation' => 'govuk_alpha_volunteering.create_opp.validation',
            'opp-forbidden' => 'govuk_alpha_volunteering.create_opp.forbidden',
            'opp-org-not-found' => 'govuk_alpha_volunteering.create_opp.org_not_found',
            'opp-create-failed' => 'govuk_alpha_volunteering.create_opp.create_failed',
        ];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.volunteering.my-organisations', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_volunteering.shared.back_to_my_organisations') }}</a>

    @if (isset($errorStates[$status]))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_volunteering.shared.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    @if ($status === 'opp-validation')
                        <ul class="govuk-list govuk-error-summary__list">
                            <li><a href="#organization_id">{{ __('govuk_alpha_volunteering.create_opp.error_org_required') }}</a></li>
                            <li><a href="#title">{{ __('govuk_alpha_volunteering.create_opp.error_title_required') }}</a></li>
                            <li><a href="#description">{{ __('govuk_alpha_volunteering.create_opp.error_description_required') }}</a></li>
                        </ul>
                    @else
                        <p>{{ __($errorStates[$status]) }}</p>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_volunteering.create_opp.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_volunteering.create_opp.description') }}</p>

    @if (empty($organizations))
        <div class="govuk-inset-text">
            <p class="govuk-body">{{ __('govuk_alpha_volunteering.create_opp.no_orgs') }}</p>
            <p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha_volunteering.create_opp.no_orgs_cta') }}</p>
        </div>
    @else
        <form method="post" action="{{ route('govuk-alpha.volunteering.opportunities.store', ['tenantSlug' => $tenantSlug]) }}">
            @csrf

            <div class="govuk-form-group">
                <label class="govuk-label" for="organization_id">{{ __('govuk_alpha_volunteering.create_opp.org_label') }}</label>
                <div id="organization_id-hint" class="govuk-hint">{{ __('govuk_alpha_volunteering.create_opp.org_hint') }}</div>
                <select class="govuk-select" id="organization_id" name="organization_id" aria-describedby="organization_id-hint" required>
                    <option value="">{{ __('govuk_alpha_volunteering.create_opp.org_select') }}</option>
                    @foreach ($organizations as $organization)
                        @php $oid = (int) ($organization['id'] ?? 0); @endphp
                        @if ($oid > 0)
                            <option value="{{ $oid }}">{{ $organization['name'] ?? ('#' . $oid) }}</option>
                        @endif
                    @endforeach
                </select>
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label" for="title">{{ __('govuk_alpha_volunteering.create_opp.opp_title_label') }}</label>
                <div id="title-hint" class="govuk-hint">{{ __('govuk_alpha_volunteering.create_opp.opp_title_hint') }}</div>
                <input class="govuk-input" id="title" name="title" type="text" maxlength="200" aria-describedby="title-hint" required>
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label" for="description">{{ __('govuk_alpha_volunteering.create_opp.opp_description_label') }}</label>
                <div id="description-hint" class="govuk-hint">{{ __('govuk_alpha_volunteering.create_opp.opp_description_hint') }}</div>
                <textarea class="govuk-textarea" id="description" name="description" rows="6" aria-describedby="description-hint" required></textarea>
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label" for="location">{{ __('govuk_alpha_volunteering.create_opp.location_label') }}</label>
                <div id="location-hint" class="govuk-hint">{{ __('govuk_alpha_volunteering.create_opp.location_hint') }}</div>
                <input class="govuk-input" id="location" name="location" type="text" maxlength="255" aria-describedby="location-hint">
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label" for="skills_needed">{{ __('govuk_alpha_volunteering.create_opp.skills_label') }}</label>
                <div id="skills_needed-hint" class="govuk-hint">{{ __('govuk_alpha_volunteering.create_opp.skills_hint') }}</div>
                <input class="govuk-input" id="skills_needed" name="skills_needed" type="text" maxlength="255" aria-describedby="skills_needed-hint">
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label" for="category_id">{{ __('govuk_alpha_volunteering.create_opp.category_label') }}</label>
                <select class="govuk-select" id="category_id" name="category_id">
                    <option value="">{{ __('govuk_alpha_volunteering.create_opp.category_select') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category['id'] }}">{{ $category['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="govuk-grid-row">
                <div class="govuk-grid-column-one-half">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="start_date">{{ __('govuk_alpha_volunteering.create_opp.start_date_label') }}</label>
                        <div id="start_date-hint" class="govuk-hint">{{ __('govuk_alpha_volunteering.create_opp.start_date_hint') }}</div>
                        <input class="govuk-input govuk-input--width-10" id="start_date" name="start_date" type="date" aria-describedby="start_date-hint">
                    </div>
                </div>
                <div class="govuk-grid-column-one-half">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="end_date">{{ __('govuk_alpha_volunteering.create_opp.end_date_label') }}</label>
                        <input class="govuk-input govuk-input--width-10" id="end_date" name="end_date" type="date">
                    </div>
                </div>
            </div>

            <fieldset class="govuk-fieldset govuk-!-margin-bottom-4">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_volunteering.create_opp.visibility_legend') }}</legend>
                <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                    <div class="govuk-checkboxes__item">
                        <input class="govuk-checkboxes__input" id="is_remote" name="is_remote" type="checkbox" value="1">
                        <label class="govuk-label govuk-checkboxes__label" for="is_remote">{{ __('govuk_alpha_volunteering.create_opp.remote_label') }}</label>
                    </div>
                    <div class="govuk-checkboxes__item">
                        <input class="govuk-checkboxes__input" id="federated_visibility" name="federated_visibility" type="checkbox" value="1" aria-describedby="federated-hint">
                        <label class="govuk-label govuk-checkboxes__label" for="federated_visibility">{{ __('govuk_alpha_volunteering.create_opp.federated_label') }}</label>
                        <div id="federated-hint" class="govuk-hint govuk-checkboxes__hint">{{ __('govuk_alpha_volunteering.create_opp.federated_hint') }}</div>
                    </div>
                </div>
            </fieldset>

            <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_volunteering.create_opp.submit_button') }}</button>
        </form>
    @endif
@endsection
