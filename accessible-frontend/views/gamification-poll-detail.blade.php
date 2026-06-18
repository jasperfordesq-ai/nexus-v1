{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $pollId = (int) ($poll['id'] ?? 0);
        $question = trim((string) ($poll['question'] ?? '')) ?: __('govuk_alpha_gamification.poll_detail.title');
        $options = is_array($poll['options'] ?? null) ? $poll['options'] : [];
        $pollStatus = (string) ($poll['status'] ?? 'closed');
        $isOpen = $pollStatus === 'open';
        $pollType = trim((string) ($poll['poll_type'] ?? 'standard'));
        $isRanked = $pollType === 'ranked';
        $hasVoted = (bool) ($poll['has_voted'] ?? false);
        $votedOptionId = $poll['voted_option_id'] ?? null;
        $totalVotes = (int) ($poll['total_votes'] ?? 0);
        $resultsVisible = (bool) ($poll['results_visible'] ?? false);
        $creatorName = trim((string) ($poll['creator']['name'] ?? '')) ?: __('govuk_alpha_gamification.common.unknown_member');
        $pollDate = fn ($v): ?string => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('j F Y') : null;
        $closesOn = $pollDate($poll['expires_at'] ?? null);
        $likeCount = (int) ($pollLikeCount ?? 0);
        $isLiked = (bool) ($pollIsLiked ?? false);
        $commentCount = (int) ($pollCommentCount ?? 0);
        $comments = is_array($pollComments ?? null) ? $pollComments : [];
        $maxVotes = 0;
        foreach ($options as $o) { $maxVotes = max($maxVotes, (int) ($o['vote_count'] ?? 0)); }
        $canRank = $isRanked && $pollId > 0 && \Illuminate\Support\Facades\Route::has('govuk-alpha.gamification.poll.rank');
        $canVote = $isOpen && !$isRanked && !$hasVoted && !empty($options)
            && \Illuminate\Support\Facades\Route::has('govuk-alpha.polls.vote');

        $statusKey = $status ?? null;
        $successStates = ['poll-liked', 'poll-unliked', 'poll-comment-created'];
        $errorStates = ['poll-like-failed', 'poll-comment-empty', 'poll-comment-too-long', 'poll-comment-failed'];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.polls.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_gamification.common.back_to_polls') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_gamification.poll_detail.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <div class="nexus-alpha-module-row">
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-1">{{ $question }}</h1>
        @if ($isOpen)
            <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha_gamification.poll_detail.open_tag') }}</strong>
        @else
            <strong class="govuk-tag govuk-tag--grey">{{ __('govuk_alpha_gamification.poll_detail.closed_tag') }}</strong>
        @endif
        @if ($isRanked)<strong class="govuk-tag govuk-tag--purple">{{ __('govuk_alpha_gamification.poll_detail.ranked_tag') }}</strong>@endif
    </div>

    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-3">
        {{ __('govuk_alpha_gamification.poll_detail.by_label', ['name' => $creatorName]) }}
        @if ($closesOn) · {{ $isOpen ? __('govuk_alpha_gamification.poll_detail.closes_on', ['date' => $closesOn]) : __('govuk_alpha_gamification.poll_detail.closed_on', ['date' => $closesOn]) }}@endif
        · {{ trans_choice('govuk_alpha_gamification.poll_detail.votes_count', $totalVotes, ['count' => $totalVotes]) }}
    </p>

    {{-- ===== Status banner ===== --}}
    @if (in_array($statusKey, $successStates, true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="poll-detail-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="poll-detail-status">{{ __('govuk_alpha_gamification.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_gamification.poll_detail.states.' . $statusKey) }}</p></div>
        </div>
    @elseif (in_array($statusKey, $errorStates, true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_gamification.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p class="govuk-body">{{ __('govuk_alpha_gamification.poll_detail.states.' . $statusKey) }}</p></div>
            </div>
        </div>
    @endif

    @if (!empty($poll['description']))
        <p class="govuk-body">{{ $poll['description'] }}</p>
    @endif

    {{-- ===== Poll body: vote action / results ===== --}}
    @if ($canRank)
        <a class="govuk-button" data-module="govuk-button" role="button" href="{{ route('govuk-alpha.gamification.poll.rank', ['tenantSlug' => $tenantSlug, 'pollId' => $pollId]) }}">{{ $hasVoted ? __('govuk_alpha_gamification.nav.view_ranked_poll') : __('govuk_alpha_gamification.poll_detail.rank_link') }}</a>
    @elseif (empty($options))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_gamification.poll_detail.no_options') }}</p></div>
    @elseif ($canVote)
        <form method="post" action="{{ route('govuk-alpha.polls.vote', ['tenantSlug' => $tenantSlug, 'pollId' => $pollId]) }}">
            @csrf
            <fieldset class="govuk-fieldset" aria-describedby="poll-detail-{{ $pollId }}-hint">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_gamification.poll_detail.vote_link') }}</legend>
                <div id="poll-detail-{{ $pollId }}-hint" class="govuk-hint">{{ __('govuk_alpha_gamification.poll_detail.results_pending') }}</div>
                <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                    @foreach ($options as $opt)
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="poll-detail-{{ $pollId }}-opt-{{ $opt['id'] }}" name="option_id" type="radio" value="{{ $opt['id'] }}">
                            <label class="govuk-label govuk-radios__label" for="poll-detail-{{ $pollId }}-opt-{{ $opt['id'] }}">{{ $opt['text'] ?? ($opt['label'] ?? '') }}</label>
                        </div>
                    @endforeach
                </div>
            </fieldset>
            <button class="govuk-button govuk-!-margin-top-2" data-module="govuk-button">{{ __('govuk_alpha_gamification.poll_detail.vote_link') }}</button>
        </form>
    @elseif ($resultsVisible)
        <h2 class="govuk-heading-l">{{ __('govuk_alpha_gamification.poll_detail.results_heading') }}</h2>
        @foreach ($options as $opt)
            @php
                $pct = (float) ($opt['percentage'] ?? 0);
                $cnt = (int) ($opt['vote_count'] ?? 0);
                $optText = $opt['text'] ?? ($opt['label'] ?? '');
                $isMine = $votedOptionId !== null && (int) ($opt['id'] ?? 0) === (int) $votedOptionId;
                $isLeading = $totalVotes > 0 && $cnt === $maxVotes && $cnt > 0;
            @endphp
            <div class="govuk-!-margin-bottom-3">
                <p class="govuk-body govuk-!-margin-bottom-1">
                    {{ $optText }}
                    @if ($isLeading)<strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha_gamification.poll_detail.leading') }}</strong>@endif
                    @if ($isMine)<strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha_gamification.poll_detail.your_choice') }}</strong>@endif
                </p>
                <progress max="100" value="{{ $pct }}" aria-label="{{ $optText }}: {{ $pct }}%">{{ $pct }}%</progress>
                <span class="govuk-body-s">{{ $pct }}% — {{ trans_choice('govuk_alpha_gamification.poll_detail.votes_count', $cnt, ['count' => $cnt]) }}</span>
            </div>
        @endforeach
    @else
        <div class="govuk-inset-text">{{ __('govuk_alpha_gamification.poll_detail.results_pending') }}</div>
        <ul class="govuk-list">
            @foreach ($options as $opt)
                @php $isMine = $votedOptionId !== null && (int) ($opt['id'] ?? 0) === (int) $votedOptionId; @endphp
                <li>
                    {{ $opt['text'] ?? ($opt['label'] ?? '') }}
                    @if ($isMine)<strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha_gamification.poll_detail.your_choice') }}</strong>@endif
                </li>
            @endforeach
        </ul>
    @endif

    {{-- ===== Social: likes + comments ===== --}}
    <hr class="govuk-section-break govuk-section-break--visible govuk-section-break--l">
    <h2 class="govuk-heading-l" id="poll-social">{{ __('govuk_alpha_gamification.poll_detail.social_heading') }}</h2>

    <p class="govuk-body-s nexus-alpha-meta">
        {{ trans_choice('govuk_alpha_gamification.poll_detail.like_summary', $likeCount, ['count' => $likeCount]) }}
        ·
        {{ trans_choice('govuk_alpha_gamification.poll_detail.comment_summary', $commentCount, ['count' => $commentCount]) }}
    </p>

    <div class="nexus-alpha-actions govuk-!-margin-bottom-4">
        <form method="post" action="{{ route('govuk-alpha.gamification.poll.like', ['tenantSlug' => $tenantSlug, 'pollId' => $pollId]) }}">
            @csrf
            <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button" aria-pressed="{{ $isLiked ? 'true' : 'false' }}">
                {{ $isLiked ? __('govuk_alpha_gamification.poll_detail.unlike_button') : __('govuk_alpha_gamification.poll_detail.like_button') }}
            </button>
        </form>
    </div>

    <h3 class="govuk-heading-m" id="poll-comments">{{ __('govuk_alpha_gamification.poll_detail.comments_heading') }}</h3>
    @if (!empty($comments))
        @include('accessible-frontend::partials.feed-comments', [
            'comments' => $comments,
            'depth' => 0,
            'targetType' => 'poll',
            'targetId' => $pollId,
            'tenantSlug' => $tenantSlug,
            'requiresAuth' => false,
            'currentUserId' => $currentUserId ?? null,
            'preservedFeedInputs' => [],
            'alphaReactions' => [],
        ])
    @else
        <p class="govuk-body">{{ __('govuk_alpha_gamification.poll_detail.no_comments') }}</p>
    @endif

    <form method="post" action="{{ route('govuk-alpha.gamification.poll.comment', ['tenantSlug' => $tenantSlug, 'pollId' => $pollId]) }}">
        @csrf
        <div class="govuk-form-group">
            <label class="govuk-label" for="poll-comment-{{ $pollId }}">{{ __('govuk_alpha_gamification.poll_detail.comment_label') }}</label>
            <div id="poll-comment-{{ $pollId }}-hint" class="govuk-hint">{{ __('govuk_alpha_gamification.poll_detail.comment_hint') }}</div>
            <textarea class="govuk-textarea" id="poll-comment-{{ $pollId }}" name="content" rows="3" aria-describedby="poll-comment-{{ $pollId }}-hint" maxlength="10000"></textarea>
        </div>
        <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_gamification.poll_detail.comment_button') }}</button>
    </form>
@endsection
