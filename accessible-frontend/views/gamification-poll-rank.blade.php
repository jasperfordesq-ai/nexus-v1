{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $question = trim((string) ($poll['question'] ?? '')) ?: __('govuk_alpha_gamification.ranked.title');
        $options = is_array($poll['options'] ?? null) ? $poll['options'] : [];
        $optionCount = count($options);
        $alreadyRanked = is_array($myRankings ?? null) && !empty($myRankings);
        $results = is_array($rankedResults ?? null) ? $rankedResults : ['total_voters' => 0, 'results' => []];
        $resultRows = is_array($results['results'] ?? null) ? $results['results'] : [];
        $totalVoters = (int) ($results['total_voters'] ?? 0);
        $isClosed = ($poll['status'] ?? 'open') !== 'open';
        $maxVotes = 0;
        foreach ($resultRows as $r) { $maxVotes = max($maxVotes, (int) ($r['votes'] ?? 0)); }
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.polls.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_gamification.common.back_to_polls') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_gamification.ranked.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <div class="nexus-alpha-module-row">
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-1">{{ $question }}</h1>
        <strong class="govuk-tag govuk-tag--purple">{{ __('govuk_alpha_gamification.ranked.badge') }}</strong>
    </div>

    @if (!empty($poll['description']))
        <p class="govuk-body">{{ $poll['description'] }}</p>
    @endif

    @if ($status === 'ranked')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="rank-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="rank-status">{{ __('govuk_alpha_gamification.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_gamification.ranked.states.ranked') }}</p></div>
        </div>
    @elseif ($status === 'rank-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_gamification.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p class="govuk-body">{{ __('govuk_alpha_gamification.ranked.states.rank-failed') }}</p></div>
            </div>
        </div>
    @endif

    @if ($optionCount === 0)
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_gamification.ranked.no_options') }}</p></div>
    @elseif ($alreadyRanked || $isClosed)
        {{-- Already ranked (or closed): show results, not the voting form. --}}
        @if ($alreadyRanked && !$isClosed)
            <div class="govuk-inset-text">{{ __('govuk_alpha_gamification.ranked.already_ranked') }}</div>
        @endif
        <h2 class="govuk-heading-l">{{ __('govuk_alpha_gamification.ranked.results_heading') }}</h2>
        <p class="govuk-body-s nexus-alpha-meta">{{ trans_choice('govuk_alpha_gamification.ranked.total_voters', $totalVoters, ['count' => $totalVoters]) }}</p>
        @if (empty($resultRows))
            <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_gamification.ranked.no_results') }}</p></div>
        @else
            @foreach ($resultRows as $r)
                @php
                    $rText = trim((string) ($r['text'] ?? '')) ?: __('govuk_alpha_gamification.common.unknown_member');
                    $rVotes = (int) ($r['votes'] ?? 0);
                    $pct = $totalVoters > 0 ? (int) round(($rVotes / $totalVoters) * 100) : 0;
                    $isLeading = $totalVoters > 0 && $rVotes === $maxVotes && $rVotes > 0;
                @endphp
                <div class="govuk-!-margin-bottom-3">
                    <p class="govuk-body govuk-!-margin-bottom-1">
                        {{ $rText }}
                        @if ($isLeading)<strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha_gamification.ranked.winner') }}</strong>@endif
                    </p>
                    <progress max="100" value="{{ $pct }}" aria-label="{{ $rText }}: {{ $pct }}%">{{ $pct }}%</progress>
                    <span class="govuk-body-s">{{ $pct }}% — {{ trans_choice('govuk_alpha_gamification.ranked.first_choice_votes', $rVotes, ['count' => $rVotes]) }}</span>
                </div>
            @endforeach
        @endif
    @else
        {{-- Ranking form: each option gets a 1..N position select (no-JS reorder). --}}
        <div class="govuk-inset-text">{{ __('govuk_alpha_gamification.ranked.how_it_works') }}</div>
        <form method="post" action="{{ route('govuk-alpha.gamification.poll.rank.store', ['tenantSlug' => $tenantSlug, 'pollId' => (int) ($poll['id'] ?? 0)]) }}">
            @csrf
            <fieldset class="govuk-fieldset">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">{{ __('govuk_alpha_gamification.ranked.legend') }}</legend>
                @foreach ($options as $idx => $opt)
                    @php
                        $optId = (int) ($opt['id'] ?? 0);
                        $optText = trim((string) ($opt['text'] ?? ($opt['label'] ?? ''))) ?: __('govuk_alpha_gamification.common.unknown_member');
                        $defaultPos = $idx + 1;
                    @endphp
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="rank-{{ $optId }}">{{ $optText }}</label>
                        <select class="govuk-select" id="rank-{{ $optId }}" name="rank[{{ $optId }}]">
                            @for ($p = 1; $p <= $optionCount; $p++)
                                <option value="{{ $p }}" @selected($p === $defaultPos)>{{ __('govuk_alpha_gamification.ranked.position_label', ['num' => $p]) }}</option>
                            @endfor
                        </select>
                    </div>
                @endforeach
            </fieldset>
            <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_gamification.ranked.submit_button') }}</button>
        </form>
    @endif
@endsection
