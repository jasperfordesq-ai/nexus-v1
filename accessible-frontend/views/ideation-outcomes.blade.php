{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $ideationActiveTab = 'outcomes';
        $ideationIsAdmin = $isAdmin ?? false;
        $s = $stats ?? [];
        $outcomeStatusLabel = function (string $st): array {
            return match ($st) {
                'implemented' => ['govuk-tag--green', __('govuk_alpha_ideation.outcomes.status_implemented')],
                'in_progress' => ['govuk-tag--blue', __('govuk_alpha_ideation.outcomes.status_in_progress')],
                'abandoned' => ['govuk-tag--red', __('govuk_alpha_ideation.outcomes.status_abandoned')],
                default => ['govuk-tag--grey', __('govuk_alpha_ideation.outcomes.status_not_started')],
            };
        };
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha_ideation.outcomes.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_ideation.outcomes.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_ideation.outcomes.intro') }}</p>

    @include('accessible-frontend::partials.ideation-nav')

    {{-- Stats --}}
    <dl class="govuk-summary-list govuk-!-margin-bottom-6">
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_ideation.outcomes.stat_total') }}</dt>
            <dd class="govuk-summary-list__value">{{ (int) ($s['total'] ?? 0) }}</dd>
        </div>
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_ideation.outcomes.stat_implemented') }}</dt>
            <dd class="govuk-summary-list__value">{{ (int) ($s['implemented'] ?? 0) }}</dd>
        </div>
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_ideation.outcomes.stat_in_progress') }}</dt>
            <dd class="govuk-summary-list__value">{{ (int) ($s['in_progress'] ?? 0) }}</dd>
        </div>
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_ideation.outcomes.stat_not_started') }}</dt>
            <dd class="govuk-summary-list__value">{{ (int) ($s['not_started'] ?? 0) }}</dd>
        </div>
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_ideation.outcomes.stat_abandoned') }}</dt>
            <dd class="govuk-summary-list__value">{{ (int) ($s['abandoned'] ?? 0) }}</dd>
        </div>
    </dl>

    @if (empty($outcomes))
        <div class="govuk-inset-text">{{ __('govuk_alpha_ideation.outcomes.empty') }}</div>
    @else
        <table class="govuk-table">
            <caption class="govuk-visually-hidden">{{ __('govuk_alpha_ideation.outcomes.title') }}</caption>
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_ideation.outcomes.challenge_column') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_ideation.outcomes.winning_column') }}</th>
                    <th scope="col" class="govuk-table__header">{{ __('govuk_alpha_ideation.outcomes.status_column') }}</th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                @foreach ($outcomes as $o)
                    @php
                        $oChallengeId = (int) ($o['challenge_id'] ?? 0);
                        $oChallengeTitle = trim((string) ($o['challenge_title'] ?? '')) ?: __('govuk_alpha_ideation.nav.challenges');
                        $oIdeaTitle = trim((string) ($o['idea_title'] ?? ''));
                        $oStatus = (string) ($o['status'] ?? 'not_started');
                        [$oTagClass, $oTagLabel] = $outcomeStatusLabel($oStatus);
                    @endphp
                    <tr class="govuk-table__row">
                        <td class="govuk-table__cell">
                            @if ($ideationIsAdmin)
                                <a class="govuk-link" href="{{ route('govuk-alpha.ideation.outcome', ['tenantSlug' => $tenantSlug, 'id' => $oChallengeId]) }}">{{ $oChallengeTitle }}</a>
                            @else
                                <a class="govuk-link" href="{{ route('govuk-alpha.ideation.show', ['tenantSlug' => $tenantSlug, 'id' => $oChallengeId]) }}">{{ $oChallengeTitle }}</a>
                            @endif
                        </td>
                        <td class="govuk-table__cell">{{ $oIdeaTitle !== '' ? $oIdeaTitle : __('govuk_alpha_ideation.outcomes.no_winning_idea') }}</td>
                        <td class="govuk-table__cell"><strong class="govuk-tag {{ $oTagClass }}">{{ $oTagLabel }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
