{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.achievements', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_gamification.common.back_to_achievements') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_gamification.engagement.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_gamification.engagement.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_gamification.engagement.description') }}</p>

    @include('accessible-frontend::partials.gamification-nav', ['gamificationNavGroup' => 'achievements', 'gamificationActiveTab' => 'engagement'])

    @if (empty($engagementHistory))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_gamification.engagement.empty') }}</p></div>
    @else
        <table class="govuk-table">
            <caption class="govuk-table__caption govuk-table__caption--s govuk-visually-hidden">{{ __('govuk_alpha_gamification.engagement.title') }}</caption>
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_gamification.engagement.month_column') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_gamification.engagement.active_column') }}</th>
                    <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha_gamification.engagement.activity_column') }}</th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                @foreach ($engagementHistory as $row)
                    @php
                        $month = trim((string) ($row['year_month'] ?? ''));
                        $wasActive = (bool) ($row['was_active'] ?? false);
                        $count = (int) ($row['activity_count'] ?? 0);
                    @endphp
                    <tr class="govuk-table__row">
                        <td class="govuk-table__cell">{{ $month }}</td>
                        <td class="govuk-table__cell">
                            <strong class="govuk-tag {{ $wasActive ? 'govuk-tag--green' : 'govuk-tag--grey' }}">{{ $wasActive ? __('govuk_alpha_gamification.engagement.active_yes') : __('govuk_alpha_gamification.engagement.active_no') }}</strong>
                        </td>
                        <td class="govuk-table__cell govuk-table__cell--numeric">{{ trans_choice('govuk_alpha_gamification.engagement.activities_count', $count, ['count' => $count]) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
