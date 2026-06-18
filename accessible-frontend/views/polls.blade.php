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
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="polls-status">
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

    {{-- ===== Page actions: advanced poll creation + management (parity links) ===== --}}
    <div class="govuk-button-group">
        @if (\Illuminate\Support\Facades\Route::has('govuk-alpha.gamification.poll.create'))
            <a class="govuk-button govuk-button--secondary" data-module="govuk-button" role="button" href="{{ route('govuk-alpha.gamification.poll.create', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_gamification.nav.create_poll') }}</a>
        @endif
        @if (\Illuminate\Support\Facades\Route::has('govuk-alpha.gamification.poll.manage'))
            <a class="govuk-button govuk-button--secondary" data-module="govuk-button" role="button" href="{{ route('govuk-alpha.gamification.poll.manage', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_gamification.nav.manage_polls') }}</a>
        @endif
    </div>

    {{-- ===== Create poll form ===== --}}
    <details class="govuk-details govuk-!-margin-bottom-6" data-module="govuk-details">
        <summary class="govuk-details__summary">
            <span class="govuk-details__summary-text">{{ __('govuk_alpha.polish_discovery.polls_create_summary') }}</span>
        </summary>
        <div class="govuk-details__text">
            @if ($status === 'poll-created')
                <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="poll-create-status">
                    <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="poll-create-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
                    <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.polish_discovery.polls_create_success') }}</p></div>
                </div>
            @elseif ($status === 'poll-create-failed')
                <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                        <div class="govuk-error-summary__body"><p class="govuk-body">{{ __('govuk_alpha.polish_discovery.polls_create_failed') }}</p></div>
                    </div>
                </div>
            @endif
            <form method="post" action="{{ route('govuk-alpha.polls.store', ['tenantSlug' => $tenantSlug]) }}" novalidate>
                @csrf
                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--s" for="poll-question">{{ __('govuk_alpha.polish_discovery.polls_create_title_label') }}</label>
                    <div id="poll-question-hint" class="govuk-hint">{{ __('govuk_alpha.polish_discovery.polls_create_title_hint') }}</div>
                    <input class="govuk-input" id="poll-question" name="question" type="text" aria-describedby="poll-question-hint" required maxlength="500">
                </div>
                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--s" for="poll-description">{{ __('govuk_alpha.polish_discovery.polls_create_desc_label') }}</label>
                    <div id="poll-desc-hint" class="govuk-hint">{{ __('govuk_alpha.polish_discovery.polls_create_desc_hint') }}</div>
                    <textarea class="govuk-textarea" id="poll-description" name="description" rows="3" aria-describedby="poll-desc-hint" maxlength="2000"></textarea>
                </div>
                <fieldset class="govuk-fieldset govuk-!-margin-bottom-4">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">
                        {{ __('govuk_alpha.polish_discovery.polls_create_options_legend') }}
                        <span class="govuk-hint">{{ __('govuk_alpha.polish_discovery.polls_create_options_hint') }}</span>
                    </legend>
                    @foreach ([1, 2, 3, 4] as $n)
                        <div class="govuk-form-group govuk-!-margin-bottom-2">
                            <label class="govuk-label" for="poll-option-{{ $n }}">{{ __('govuk_alpha.polish_discovery.polls_create_option_label', ['num' => $n]) }}</label>
                            <input class="govuk-input govuk-input--width-30" id="poll-option-{{ $n }}" name="options[]" type="text" maxlength="500"{{ $n <= 2 ? ' required' : '' }}>
                        </div>
                    @endforeach
                </fieldset>
                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--s" for="poll-expires">{{ __('govuk_alpha.polish_discovery.polls_create_expires_label') }}</label>
                    <input class="govuk-input govuk-input--width-10" id="poll-expires" name="expires_at" type="date" min="{{ now()->addDay()->format('Y-m-d') }}">
                </div>
                <div class="govuk-form-group">
                    <fieldset class="govuk-fieldset">
                        <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha.polish_discovery.polls_create_type_legend') }}</legend>
                        <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                            <div class="govuk-radios__item">
                                <input class="govuk-radios__input" id="poll-type-single" name="poll_type" type="radio" value="standard" checked>
                                <label class="govuk-label govuk-radios__label" for="poll-type-single">{{ __('govuk_alpha.polish_discovery.polls_create_type_single') }}</label>
                            </div>
                            <div class="govuk-radios__item">
                                <input class="govuk-radios__input" id="poll-type-multiple" name="poll_type" type="radio" value="multiple">
                                <label class="govuk-label govuk-radios__label" for="poll-type-multiple">{{ __('govuk_alpha.polish_discovery.polls_create_type_multiple') }}</label>
                            </div>
                        </div>
                    </fieldset>
                </div>
                <div class="govuk-form-group">
                    <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="poll-anonymous" name="is_anonymous" type="checkbox" value="1">
                            <label class="govuk-label govuk-checkboxes__label" for="poll-anonymous">{{ __('govuk_alpha.polish_discovery.polls_create_anon_label') }}</label>
                        </div>
                    </div>
                </div>
                <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.polish_discovery.polls_create_submit') }}</button>
            </form>
        </div>
    </details>

    @if (empty($polls))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.polls.empty') }}</p></div>
    @else
        {{-- ===== Open polls (vote) ===== --}}
        <h2 class="govuk-heading-l">{{ __('govuk_alpha.polls.open_section_title') }}</h2>
        @if (empty($openPolls))
            <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.polls.no_open') }}</p></div>
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
                    $pollType = trim((string) ($poll['poll_type'] ?? 'standard'));
                    $isRanked = $pollType === 'ranked';
                    $canRank = $isRanked && $pollId > 0 && \Illuminate\Support\Facades\Route::has('govuk-alpha.gamification.poll.rank');
                @endphp
                <article class="nexus-alpha-card govuk-!-margin-bottom-6" id="poll-{{ $pollId }}">
                    <div class="nexus-alpha-module-row">
                        <h3 class="govuk-heading-m govuk-!-margin-bottom-1">{{ $question }}</h3>
                        <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.polls.open_tag') }}</strong>
                        @if ($isRanked)<strong class="govuk-tag govuk-tag--purple">{{ __('govuk_alpha_gamification.nav.ranked_tag') }}</strong>@endif
                    </div>
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-3">
                        {{ __('govuk_alpha.polls.by_label', ['name' => $creatorName]) }}@if ($closesOn) · {{ __('govuk_alpha.polls.closes_on', ['date' => $closesOn]) }}@endif
                    </p>

                    @if (!empty($poll['description']))
                        <p class="govuk-body">{{ $poll['description'] }}</p>
                    @endif

                    @if ($canRank)
                        {{-- Ranked-choice polls are voted on a dedicated reorder page, not single-choice radios. --}}
                        <a class="govuk-button" data-module="govuk-button" role="button" href="{{ route('govuk-alpha.gamification.poll.rank', ['tenantSlug' => $tenantSlug, 'pollId' => $pollId]) }}">{{ $hasVoted ? __('govuk_alpha_gamification.nav.view_ranked_poll') : __('govuk_alpha_gamification.nav.rank_this_poll') }}</a>
                    @elseif (empty($options))
                        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.polls.no_options') }}</p></div>
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
                        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.polls.no_options') }}</p></div>
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
                                <progress max="100" value="{{ $pct }}" aria-label="{{ ($opt['text'] ?? ($opt['label'] ?? '')) }}: {{ $pct }}%">{{ $pct }}%</progress>
                                <span class="govuk-body-s">{{ $pct }}% — {{ __('govuk_alpha.polls.per_option_votes', ['count' => $cnt]) }}</span>
                            </div>
                        @endforeach
                    @endif
                </article>
            @endforeach
        @endif
    @endif
@endsection
