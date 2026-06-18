{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $s = $savedSearch ?? ['id' => 0, 'name' => '', 'query' => ''];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.search.advanced', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_search.back_to_search') }}</a>

    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_search.saved.delete_title') }}</h1>

    <dl class="govuk-summary-list govuk-!-margin-bottom-6">
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_search.saved.delete_summary') }}</dt>
            <dd class="govuk-summary-list__value">
                <strong>{{ $s['name'] }}</strong>
                @if (($s['query'] ?? '') !== '')
                    <br><span class="nexus-alpha-meta">{{ $s['query'] }}</span>
                @endif
            </dd>
        </div>
    </dl>

    <div class="govuk-warning-text">
        <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
        <strong class="govuk-warning-text__text">
            <span class="govuk-visually-hidden">{{ __('govuk_alpha_search.states.error_title') }}</span>
            {{ __('govuk_alpha_search.saved.delete_warning') }}
        </strong>
    </div>

    <div class="govuk-button-group">
        <form method="post" action="{{ route('govuk-alpha.search.saved.delete', ['tenantSlug' => $tenantSlug, 'id' => $s['id']]) }}" class="govuk-!-display-inline">
            @csrf
            <button type="submit" class="govuk-button govuk-button--warning" data-module="govuk-button">{{ __('govuk_alpha_search.saved.delete_confirm') }}</button>
        </form>
        <a class="govuk-link" href="{{ route('govuk-alpha.search.advanced', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_search.saved.delete_cancel') }}</a>
    </div>
@endsection
