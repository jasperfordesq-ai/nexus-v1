{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.goals.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.goals.back_to_goals') }}</a>

    @if ($status === 'goal-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list"><li><a href="#templates-list">{{ __('govuk_alpha.goals.states.goal-failed') }}</a></li></ul>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ __('govuk_alpha.goals.templates_caption') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.goals.templates_title') }}</h1>

    @if (!empty($categories))
        <form method="get" action="{{ route('govuk-alpha.goals.templates', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
            <div class="govuk-form-group">
                <label class="govuk-label" for="category">{{ __('govuk_alpha.goals.templates_category_label') }}</label>
                <select class="govuk-select" id="category" name="category">
                    <option value="">{{ __('govuk_alpha.goals.templates_category_all') }}</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat }}" @selected((string) ($category ?? '') === (string) $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
            <button class="govuk-button govuk-button--secondary" data-module="govuk-button" type="submit">{{ __('govuk_alpha.goals.templates_filter_button') }}</button>
        </form>
    @endif

    <div id="templates-list">
        @if (empty($templates))
            <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.goals.templates_empty') }}</p></div>
        @else
            <div class="nexus-alpha-card-list">
                @foreach ($templates as $t)
                    @php
                        $tTitle = trim((string) ($t['title'] ?? '')) ?: __('govuk_alpha.goals.title');
                        $tDesc = trim((string) ($t['description'] ?? ''));
                        $tTarget = rtrim(rtrim(number_format((float) ($t['default_target_value'] ?? 0), 2), '0'), '.');
                        $tCategory = trim((string) ($t['category'] ?? ''));
                    @endphp
                    <article class="nexus-alpha-card">
                        <div class="nexus-alpha-module-row">
                            <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $tTitle }}</h2>
                            @if ($tCategory !== '')
                                <strong class="govuk-tag govuk-tag--grey">{{ $tCategory }}</strong>
                            @endif
                        </div>
                        @if ($tDesc !== '')
                            <p class="govuk-body-s">{{ $tDesc }}</p>
                        @endif
                        @if ((float) ($t['default_target_value'] ?? 0) > 0)
                            <p class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha.goals.template_target', ['target' => $tTarget]) }}</p>
                        @endif
                        <form method="post" action="{{ route('govuk-alpha.goals.templates.use', ['tenantSlug' => $tenantSlug, 'id' => $t['id']]) }}">
                            @csrf
                            <div class="govuk-form-group">
                                <label class="govuk-label" for="title-{{ $t['id'] }}">{{ __('govuk_alpha.goals.template_title_override_label') }}</label>
                                <div id="title-hint-{{ $t['id'] }}" class="govuk-hint">{{ __('govuk_alpha.goals.template_title_override_hint') }}</div>
                                <input class="govuk-input" id="title-{{ $t['id'] }}" name="title" type="text" maxlength="255" aria-describedby="title-hint-{{ $t['id'] }}">
                            </div>
                            <div class="govuk-checkboxes govuk-checkboxes--small govuk-form-group" data-module="govuk-checkboxes">
                                <div class="govuk-checkboxes__item">
                                    <input class="govuk-checkboxes__input" id="is_public-{{ $t['id'] }}" name="is_public" type="checkbox" value="1" checked>
                                    <label class="govuk-label govuk-checkboxes__label" for="is_public-{{ $t['id'] }}">{{ __('govuk_alpha.goals.public_label') }}</label>
                                </div>
                            </div>
                            <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button" type="submit">{{ __('govuk_alpha.goals.template_use_button') }}</button>
                        </form>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endsection
