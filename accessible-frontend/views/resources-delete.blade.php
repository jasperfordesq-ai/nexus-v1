{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $rTitle = trim((string) ($resourceTitle ?? '')) ?: __('govuk_alpha_resources.file_types.file');
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.resources.library', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_resources.actions.back_to_library') }}</a>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-l">{{ __('govuk_alpha_resources.delete.caption') }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha_resources.delete.title') }}</h1>

            <div class="govuk-warning-text">
                <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                <strong class="govuk-warning-text__text">
                    <span class="govuk-visually-hidden">{{ __('govuk_alpha_resources.delete.warning_prefix') }}</span>
                    {{ __('govuk_alpha_resources.delete.warning') }}
                </strong>
            </div>

            <p class="govuk-body">{{ __('govuk_alpha_resources.delete.confirm_question', ['title' => $rTitle]) }}</p>

            <form method="post" action="{{ route('govuk-alpha.resources.delete', ['tenantSlug' => $tenantSlug, 'id' => $resourceId]) }}" novalidate>
                @csrf
                <div class="govuk-button-group">
                    <button class="govuk-button govuk-button--warning" data-module="govuk-button" type="submit">{{ __('govuk_alpha_resources.delete.submit') }}</button>
                    <a class="govuk-link" href="{{ route('govuk-alpha.resources.library', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_resources.delete.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection
