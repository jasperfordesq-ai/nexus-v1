{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_events.common.back_to_events') }}</a>

    <span class="govuk-caption-l">{{ __('govuk_alpha_events.browse.caption') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_events.browse.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_events.browse.intro') }}</p>

    @if (empty($categories))
        <div class="govuk-inset-text">
            <p class="govuk-body">{{ __('govuk_alpha_events.browse.none_available') }}</p>
            <p class="govuk-body">
                <a class="govuk-link" href="{{ route('govuk-alpha.events.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_events.browse.view_all_link') }}</a>
            </p>
        </div>
    @else
        <form method="get" action="{{ route('govuk-alpha.events.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-7">
            <fieldset class="govuk-fieldset" aria-describedby="events-browse-hint">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                    <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha_events.browse.choose_legend') }}</h2>
                </legend>
                <div id="events-browse-hint" class="govuk-hint">{{ __('govuk_alpha_events.browse.choose_hint') }}</div>

                <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                    <div class="govuk-radios__item">
                        <input class="govuk-radios__input" id="category-all" name="category_id" type="radio" value="" @checked($selectedCategoryId === null)>
                        <label class="govuk-label govuk-radios__label" for="category-all">{{ __('govuk_alpha_events.browse.all_categories') }}</label>
                        <div class="govuk-hint govuk-radios__hint">{{ __('govuk_alpha_events.browse.all_categories_hint') }}</div>
                    </div>
                    @foreach ($categories as $category)
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="category-{{ $category['id'] }}" name="category_id" type="radio" value="{{ $category['id'] }}" @checked((int) ($selectedCategoryId ?? 0) === (int) $category['id'])>
                            <label class="govuk-label govuk-radios__label" for="category-{{ $category['id'] }}">{{ $category['name'] }}</label>
                        </div>
                    @endforeach
                </div>
            </fieldset>

            <button class="govuk-button govuk-!-margin-top-4" data-module="govuk-button">{{ __('govuk_alpha_events.browse.view_button') }}</button>
        </form>

        <p class="govuk-body">
            <a class="govuk-link" href="{{ route('govuk-alpha.events.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_events.browse.view_all_link') }}</a>
        </p>
    @endif
@endsection
