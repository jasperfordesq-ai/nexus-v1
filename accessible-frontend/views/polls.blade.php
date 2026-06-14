{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
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

    @if (empty($polls))
        <div class="govuk-inset-text">{{ __('govuk_alpha.polls.empty') }}</div>
    @else
        @foreach ($polls as $poll)
            @php
                $pollId = (int) ($poll['id'] ?? 0);
                $question = trim((string) ($poll['question'] ?? '')) ?: __('govuk_alpha.polls.title');
                $isOpen = ($poll['status'] ?? 'closed') === 'open';
                $hasVoted = (bool) ($poll['has_voted'] ?? false);
                $resultsVisible = (bool) ($poll['results_visible'] ?? false);
                $votedOptionId = $poll['voted_option_id'] ?? null;
                $options = is_array($poll['options'] ?? null) ? $poll['options'] : [];
                $totalVotes = (int) ($poll['total_votes'] ?? 0);
                $creatorName = trim((string) ($poll['creator']['name'] ?? '')) ?: __('govuk_alpha.members.unknown_member');
                $showVoteForm = $isOpen && !$hasVoted;
            @endphp
            <article class="nexus-alpha-card govuk-!-margin-bottom-6" id="poll-{{ $pollId }}">
                <div class="nexus-alpha-module-row">
                    <h2 class="govuk-heading-m govuk-!-margin-bottom-1">{{ $question }}</h2>
                    <strong class="govuk-tag {{ $isOpen ? 'govuk-tag--green' : 'govuk-tag--grey' }}">{{ $isOpen ? __('govuk_alpha.polls.open_tag') : __('govuk_alpha.polls.closed_tag') }}</strong>
                </div>
                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-3">{{ __('govuk_alpha.polls.by_label', ['name' => $creatorName]) }} · {{ __('govuk_alpha.polls.votes_count', ['count' => $totalVotes]) }}</p>

                @if (!empty($poll['description']))
                    <p class="govuk-body">{{ $poll['description'] }}</p>
                @endif

                @if (empty($options))
                    <p class="govuk-inset-text">{{ __('govuk_alpha.polls.no_options') }}</p>
                @elseif ($showVoteForm)
                    <form method="post" action="{{ route('govuk-alpha.polls.vote', ['tenantSlug' => $tenantSlug, 'pollId' => $pollId]) }}">
                        @csrf
                        <fieldset class="govuk-fieldset">
                            <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha.polls.choose_label') }}</legend>
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
                @elseif ($resultsVisible)
                    <h3 class="govuk-heading-s">{{ __('govuk_alpha.polls.results_title') }}</h3>
                    @foreach ($options as $opt)
                        @php
                            $pct = (float) ($opt['percentage'] ?? 0);
                            $cnt = (int) ($opt['vote_count'] ?? 0);
                            $isMine = $votedOptionId !== null && (int) ($opt['id'] ?? 0) === (int) $votedOptionId;
                        @endphp
                        <div class="govuk-!-margin-bottom-3">
                            <p class="govuk-body govuk-!-margin-bottom-1">
                                {{ $opt['text'] ?? ($opt['label'] ?? '') }}
                                @if ($isMine)<strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha.polls.you_voted') }}</strong>@endif
                            </p>
                            <progress max="100" value="{{ $pct }}" aria-label="{{ $pct }}%">{{ $pct }}%</progress>
                            <span class="govuk-body-s">{{ $pct }}% ({{ $cnt }})</span>
                        </div>
                    @endforeach
                @else
                    {{-- Voted, but the poll is still open: ballot integrity hides the running totals. --}}
                    <p class="govuk-inset-text">{{ __('govuk_alpha.polls.voted_notice') }} {{ __('govuk_alpha.polls.results_hidden') }}</p>
                    @foreach ($options as $opt)
                        @php $isMine = $votedOptionId !== null && (int) ($opt['id'] ?? 0) === (int) $votedOptionId; @endphp
                        <p class="govuk-body govuk-!-margin-bottom-1">
                            {{ $opt['text'] ?? ($opt['label'] ?? '') }}
                            @if ($isMine)<strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha.polls.you_voted') }}</strong>@endif
                        </p>
                    @endforeach
                @endif
            </article>
        @endforeach
    @endif
@endsection
