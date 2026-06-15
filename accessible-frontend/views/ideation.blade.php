{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $statusTag = function (string $s): array {
            return match ($s) {
                'open' => ['govuk-tag--green', __('govuk_alpha.ideation.status_open')],
                'voting' => ['govuk-tag--blue', __('govuk_alpha.ideation.status_voting')],
                'evaluating' => ['govuk-tag--purple', __('govuk_alpha.ideation.status_evaluating')],
                'closed', 'archived' => ['govuk-tag--grey', __('govuk_alpha.ideation.status_closed')],
                default => ['govuk-tag--grey', __('govuk_alpha.ideation.status_draft')],
            };
        };
        $activeStatus = $ideationStatus ?? '';
        $activeQuery  = $ideationQuery ?? '';
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha.ideation.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.ideation.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.ideation.description') }}</p>

    {{-- Search + filter form --}}
    <form method="get" action="{{ route('govuk-alpha.ideation.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
        <div class="govuk-form-group govuk-!-margin-bottom-4">
            <label class="govuk-label" for="ideation-q">{{ __('govuk_alpha.polish_discovery.ideation_search_label') }}</label>
            <input class="govuk-input govuk-input--width-30" id="ideation-q" name="q" type="search" value="{{ $activeQuery }}">
        </div>
        <div class="govuk-form-group govuk-!-margin-bottom-4">
            <fieldset class="govuk-fieldset">
                <legend class="govuk-fieldset__legend">{{ __('govuk_alpha.polish_discovery.ideation_filter_label') }}</legend>
                <div class="govuk-radios govuk-radios--inline govuk-radios--small" data-module="govuk-radios">
                    @foreach ([
                        '' => __('govuk_alpha.polish_discovery.ideation_filter_all'),
                        'open' => __('govuk_alpha.polish_discovery.ideation_filter_open'),
                        'voting' => __('govuk_alpha.polish_discovery.ideation_filter_voting'),
                        'closed' => __('govuk_alpha.polish_discovery.ideation_filter_closed'),
                    ] as $val => $label)
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="ideation-status-{{ $val === '' ? 'all' : $val }}" name="status" type="radio" value="{{ $val }}"{{ $activeStatus === $val ? ' checked' : '' }}>
                            <label class="govuk-label govuk-radios__label" for="ideation-status-{{ $val === '' ? 'all' : $val }}">{{ $label }}</label>
                        </div>
                    @endforeach
                </div>
            </fieldset>
        </div>
        <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.polish_discovery.ideation_search_button') }}</button>
        @if ($activeStatus !== '' || $activeQuery !== '')
            <a class="govuk-link govuk-!-margin-left-4" href="{{ route('govuk-alpha.ideation.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.clear_filters') }}</a>
        @endif
    </form>

    @if (empty($challenges))
        <div class="govuk-inset-text">{{ __('govuk_alpha.ideation.empty') }}</div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($challenges as $c)
                @php
                    $cTitle = trim((string) ($c['title'] ?? '')) ?: __('govuk_alpha.ideation.title');
                    [$tagClass, $tagLabel] = $statusTag((string) ($c['status'] ?? 'draft'));
                    $ideaCount = (int) ($c['ideas_count'] ?? 0);
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1"><a class="govuk-link" href="{{ route('govuk-alpha.ideation.show', ['tenantSlug' => $tenantSlug, 'id' => $c['id']]) }}">{{ $cTitle }}</a></h2>
                        <strong class="govuk-tag {{ $tagClass }}">{{ $tagLabel }}</strong>
                    </div>
                    @if (trim((string) ($c['description'] ?? '')) !== '')
                        <p class="govuk-body govuk-!-margin-bottom-1">{{ \Illuminate\Support\Str::limit($c['description'], 160) }}</p>
                    @endif
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">{{ trans_choice('govuk_alpha.ideation.ideas_count', $ideaCount, ['count' => $ideaCount]) }}</p>
                </article>
            @endforeach
        </div>
    @endif
@endsection
