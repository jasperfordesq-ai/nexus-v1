{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $shifts = $shifts ?? [];
        $formatDateTime = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y, g:ia') : null;
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_volunteering') }}</a>

    <span class="govuk-caption-l">{{ __('govuk_alpha.volunteering.title') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_volunteering.recommended.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_volunteering.recommended.description') }}</p>

    @if (empty($shifts))
        <div class="govuk-inset-text">{{ __('govuk_alpha_volunteering.recommended.empty') }}</div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($shifts as $shift)
                @php
                    $opportunityId = (int) ($shift['opportunity_id'] ?? 0);
                    $matchScore = (int) ($shift['match_score'] ?? 0);
                    $alreadyApplied = (bool) ($shift['already_applied'] ?? false);
                @endphp
                <article class="nexus-alpha-card">
                    <h2 class="govuk-heading-m govuk-!-margin-bottom-2">
                        @if ($opportunityId > 0)
                            <a class="govuk-link" href="{{ route('govuk-alpha.volunteering.show', ['tenantSlug' => $tenantSlug, 'id' => $opportunityId]) }}">{{ $shift['title'] ?? __('govuk_alpha.volunteering.detail_title') }}</a>
                        @else
                            {{ $shift['title'] ?? __('govuk_alpha.volunteering.detail_title') }}
                        @endif
                    </h2>
                    <p class="govuk-!-margin-bottom-2">
                        <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha_volunteering.recommended.match_score', ['score' => $matchScore]) }}</strong>
                        @if ($alreadyApplied)
                            <strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha_volunteering.recommended.already_applied') }}</strong>
                        @endif
                    </p>
                    {{-- Match score also exposed as a native progress for non-colour conveyance --}}
                    <progress max="100" value="{{ $matchScore }}" aria-label="{{ __('govuk_alpha_volunteering.recommended.match_score', ['score' => $matchScore]) }}">{{ $matchScore }}%</progress>
                    <dl class="govuk-summary-list govuk-!-margin-top-3 govuk-!-margin-bottom-0">
                        @if (!empty($shift['organization_name']))
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_volunteering.recommended.organisation_label') }}</dt>
                                <dd class="govuk-summary-list__value">{{ $shift['organization_name'] }}</dd>
                            </div>
                        @endif
                        @if (!empty($shift['location']))
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_volunteering.recommended.location_label') }}</dt>
                                <dd class="govuk-summary-list__value">{{ $shift['location'] }}</dd>
                            </div>
                        @endif
                        @if (!empty($shift['start_time']))
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_volunteering.recommended.shift_label') }}</dt>
                                <dd class="govuk-summary-list__value">{{ $formatDateTime($shift['start_time']) }}</dd>
                            </div>
                        @endif
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_volunteering.recommended.spots_remaining') }}</dt>
                            <dd class="govuk-summary-list__value">{{ (int) ($shift['spots_remaining'] ?? 0) }}</dd>
                        </div>
                    </dl>
                    @if ($opportunityId > 0)
                        <a class="govuk-link govuk-link--no-visited-state govuk-!-margin-top-2 govuk-!-display-block" href="{{ route('govuk-alpha.volunteering.show', ['tenantSlug' => $tenantSlug, 'id' => $opportunityId]) }}">{{ __('govuk_alpha_volunteering.recommended.view_opportunity') }}</a>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
