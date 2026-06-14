{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        // Split into actionable (open) and finished (closed) so the page reads
        // top-to-bottom: vote on what's open, then read results of what's closed.
        $openPolls = [];
        $closedPolls = [];
        foreach ($polls as $p) {
            if (($p['status'] ?? 'closed') === 'open') {
                $openPolls[] = $p;
            } else {
                $closedPolls[] = $p;
            }
        }
        $pollDate = fn ($v): ?string => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('j F Y') : null;
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha.polls.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.polls.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.polls.description') }}</p>

    @if ($status === 'voted')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-live="polite" aria-labelledby="polls-status">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="polls-status">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.polls.states.voted') }}</p>
            </div>
        </div>
    @elseif ($status === 'vote-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p class="govuk-body">{{ __('govuk_alpha.polls.states.vote-failed') }}</p>
                </div>
            </div>
        </div>
    @endif

    <div class="govuk-inset-text">{{ __('govuk_alpha.polls.how_it_works') }}</div>

    @if (empty($polls))
        <p class="govuk-inset-text">{{ __('govuk_alpha.polls.empty') }}</p>
    @else
        {{-- ===== Open polls (vote) ===== --}}
        <h2 class="govuk-heading-l">{{ __('govuk_alpha.polls.open_section_title') }}</h2>
        @if (empty($openPolls))
            <p class="govuk-inset-text">{{ __('govuk_alpha.polls.no_open') }}</p>
        @else
            @foreach ($openPolls as $poll)
                @php
                    $pollId = (int) ($poll['id'] ?? 0);
                    $question = trim((string) ($poll['question'] ?? '')) ?: __('govuk_alpha.polls.title');
                    $hasVoted = (bool) ($poll['has_voted'] ?? false);
                    $votedOptionId = $poll['voted_option_id'] ?? null;
                    $options = is_array($poll['options'] ?? null) ? $poll['options'] : [];
                    $creatorName = trim((string) ($poll['creator']['name'] ?? '')) ?: __('govuk_alpha.members.unknown_member');
                    $closesOn = $pollDate($poll['expires_at'] ?? null);
                @endphp
                <article class="nexus-alpha-card govuk-!-margin-bottom-6" id="poll-{{ $pollId }}">
                    <div class="nexus-alpha-module-row">
                        <h3 class="govuk-heading-m govuk-!-margin-bottom-1">{{ $question }}</h3>
                        <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.polls.open_tag') }}</strong>
                    </div>
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-3">
                        {{ __('govuk_alpha.polls.by_label', ['name' => $creatorName]) }}@if ($closesOn) · {{ __('govuk_alpha.polls.closes_on', ['date' => $closesOn]) }}@endif
                    </p>

                    @if (!empty($poll['description']))
                        <p class="govuk-body">{{ $poll['description'] }}</p>
                    @endif

                    @if (empty($options))
                        <p class="govuk-inset-text">{{ __('govuk_alpha.polls.no_options') }}</p>
                    @elseif (!$hasVoted)
                        <form method="post" action="{{ route('govuk-alpha.polls.vote', ['tenantSlug' => $tenantSlug, 'pollId' => $pollId]) }}">
                            @csrf
                            <fieldset class="govuk-fieldset" aria-describedby="poll-{{ $pollId }}-hint">
                                <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha.polls.choose_label') }}</legend>
                                <div id="poll-{{ $pollId }}-hint" class="govuk-hint">{{ __('govuk_alpha.polls.vote_once_hint') }}</div>
                                <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                                    @foreach ($options as $opt)
                                        <div class="govuk-radios__item">
                                            <input class="govuk-radios__input" id="poll-{{ $pollId }}-opt-{{ $opt['id'] }}" name="option_id" type="radio" value="{{ $opt['id'] }}">
                                            <label class="govuk-label govuk-radios__label" for="poll-{{ $pollId }}-opt-{{ $opt['id'] }}">{{ $opt['text'] ?? ($opt['label'] ?? '') }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </fieldset>
                            <button class="govuk-button govuk-!-margin-top-2 govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.polls.vote_button') }}</button>
                        </form>
                    @else
                        <div class="govuk-inset-text">{{ __('govuk_alpha.polls.results_pending_note') }}</div>
                        <ul class="govuk-list">
                            @foreach ($options as $opt)
                                @php $isMine = $votedOptionId !== null && (int) ($opt['id'] ?? 0) === (int) $votedOptionId; @endphp
                                <li>
                                    {{ $opt['text'] ?? ($opt['label'] ?? '') }}
                                    @if ($isMine)<strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha.polls.your_choice') }}</strong>@endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </article>
            @endforeach
        @endif

        {{-- ===== Closed polls (results) ===== --}}
        @if (!empty($closedPolls))
            <h2 class="govuk-heading-l govuk-!-margin-top-8">{{ __('govuk_alpha.polls.closed_section_title') }}</h2>
            @foreach ($closedPolls as $poll)
                @php
                    $pollId = (int) ($poll['id'] ?? 0);
                    $question = trim((string) ($poll['question'] ?? '')) ?: __('govuk_alpha.polls.title');
                    $votedOptionId = $poll['voted_option_id'] ?? null;
                    $options = is_array($poll['options'] ?? null) ? $poll['options'] : [];
                    $totalVotes = (int) ($poll['total_votes'] ?? 0);
                    $resultsVisible = (bool) ($poll['results_visible'] ?? false);
                    $creatorName = trim((string) ($poll['creator']['name'] ?? '')) ?: __('govuk_alpha.members.unknown_member');
                    $closedOn = $pollDate($poll['expires_at'] ?? null);
                    // Highest vote count, to flag the leading option(s).
                    $maxVotes = 0;
                    foreach ($options as $o) { $maxVotes = max($maxVotes, (int) ($o['vote_count'] ?? 0)); }
                @endphp
                <article class="nexus-alpha-card govuk-!-margin-bottom-6" id="poll-{{ $pollId }}">
                    <div class="nexus-alpha-module-row">
                        <h3 class="govuk-heading-m govuk-!-margin-bottom-1">{{ $question }}</h3>
                        <strong class="govuk-tag govuk-tag--grey">{{ __('govuk_alpha.polls.closed_tag') }}</strong>
                    </div>
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-3">
                        {{ __('govuk_alpha.polls.by_label', ['name' => $creatorName]) }}@if ($closedOn) · {{ __('govuk_alpha.polls.closed_on', ['date' => $closedOn]) }}@endif · {{ __('govuk_alpha.polls.votes_count', ['count' => $totalVotes]) }}
                    </p>

                    @if (!empty($poll['description']))
                        <p class="govuk-body">{{ $poll['description'] }}</p>
                    @endif

                    @if (empty($options))
                        <p class="govuk-inset-text">{{ __('govuk_alpha.polls.no_options') }}</p>
                    @elseif ($resultsVisible)
                        @foreach ($options as $opt)
                            @php
                                $pct = (float) ($opt['percentage'] ?? 0);
                                $cnt = (int) ($opt['vote_count'] ?? 0);
                                $isMine = $votedOptionId !== null && (int) ($opt['id'] ?? 0) === (int) $votedOptionId;
                                $isLeading = $totalVotes > 0 && $cnt === $maxVotes;
                            @endphp
                            <div class="govuk-!-margin-bottom-3">
                                <p class="govuk-body govuk-!-margin-bottom-1">
                                    {{ $opt['text'] ?? ($opt['label'] ?? '') }}
                                    @if ($isLeading)<strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.polls.leading') }}</strong>@endif
                                    @if ($isMine)<strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha.polls.your_choice') }}</strong>@endif
                                </p>
                                <progress max="100" value="{{ $pct }}" aria-label="{{ $pct }}%">{{ $pct }}%</progress>
                                <span class="govuk-body-s">{{ $pct }}% — {{ __('govuk_alpha.polls.per_option_votes', ['count' => $cnt]) }}</span>
                            </div>
                        @endforeach
                    @endif
                </article>
            @endforeach
        @endif
    @endif
@endsection
