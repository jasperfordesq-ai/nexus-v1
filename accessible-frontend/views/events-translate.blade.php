{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">{{ __('govuk_alpha_events.common.back_to_event') }}</a>

    @php
        $sourceText = (string) ($event['description'] ?? '');
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-l">{{ $event['title'] ?? __('govuk_alpha_events.translate.caption') }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha_events.translate.title') }}</h1>

            @if ($status === 'translate-failed')
                <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_events.common.error_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <p>{{ __('govuk_alpha_events.translate.failed') }}</p>
                        </div>
                    </div>
                </div>
            @elseif ($status === 'translate-unavailable')
                <div class="govuk-inset-text">
                    <p class="govuk-body">{{ __('govuk_alpha_events.translate.unavailable') }}</p>
                </div>
            @elseif ($status === 'translate-empty')
                <div class="govuk-inset-text">
                    <p class="govuk-body">{{ __('govuk_alpha_events.translate.empty') }}</p>
                </div>
            @elseif ($status === 'translate-same')
                <div class="govuk-inset-text">
                    <p class="govuk-body">{{ __('govuk_alpha_events.translate.same') }}</p>
                </div>
            @endif

            <p class="govuk-body-l">{{ __('govuk_alpha_events.translate.intro') }}</p>

            @if (trim($sourceText) === '')
                <div class="govuk-inset-text">
                    <p class="govuk-body">{{ __('govuk_alpha_events.translate.empty') }}</p>
                </div>
            @else
                <form method="post" action="{{ route('govuk-alpha.events.translate.run', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">
                    @csrf
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="target_locale">{{ __('govuk_alpha_events.translate.language_label') }}</label>
                        <div id="target-locale-hint" class="govuk-hint">{{ __('govuk_alpha_events.translate.language_hint') }}</div>
                        <select class="govuk-select" id="target_locale" name="target_locale" aria-describedby="target-locale-hint">
                            @foreach ($languages as $code => $name)
                                <option value="{{ $code }}" @selected(($targetLocale ?? '') === $code)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="govuk-button" data-module="govuk-button" type="submit">{{ __('govuk_alpha_events.translate.translate_button') }}</button>
                </form>

                @if ($translated !== null && $status === 'translate-done')
                    <h2 class="govuk-heading-l govuk-!-margin-top-6">{{ __('govuk_alpha_events.translate.translated_heading') }}</h2>
                    <div class="govuk-inset-text">
                        <div class="govuk-body">{!! nl2br(e($translated)) !!}</div>
                    </div>
                    <p class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha_events.translate.machine_note') }}</p>
                @endif

                <h2 class="govuk-heading-l govuk-!-margin-top-6">{{ __('govuk_alpha_events.translate.original_heading') }}</h2>
                <div class="govuk-body">{!! nl2br(e($sourceText)) !!}</div>
            @endif
        </div>
    </div>
@endsection
