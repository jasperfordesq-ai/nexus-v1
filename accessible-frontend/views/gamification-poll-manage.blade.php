{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $pollDate = fn ($v): ?string => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('j F Y') : null;
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.polls.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_gamification.common.back_to_polls') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_gamification.poll_manage.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_gamification.poll_manage.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_gamification.poll_manage.description') }}</p>

    @if ($status === 'poll-deleted')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="manage-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="manage-status">{{ __('govuk_alpha_gamification.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_gamification.poll_manage.states.poll-deleted') }}</p></div>
        </div>
    @elseif ($status === 'poll-delete-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_gamification.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p class="govuk-body">{{ __('govuk_alpha_gamification.poll_manage.states.poll-delete-failed') }}</p></div>
            </div>
        </div>
    @endif

    <p class="govuk-body">
        <a class="govuk-link" href="{{ route('govuk-alpha.gamification.poll.create', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_gamification.poll_manage.create_link') }}</a>
    </p>

    @if (empty($myPolls))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_gamification.poll_manage.empty') }}</p></div>
    @else
        @foreach ($myPolls as $poll)
            @php
                $pollId = (int) ($poll['id'] ?? 0);
                $question = trim((string) ($poll['question'] ?? '')) ?: __('govuk_alpha_gamification.poll_manage.title');
                $isOpen = ($poll['status'] ?? 'closed') === 'open';
                $isRanked = ($poll['poll_type'] ?? 'standard') === 'ranked';
                $isAnon = (bool) ($poll['is_anonymous'] ?? false);
                $totalVotes = (int) ($poll['total_votes'] ?? 0);
                $closesOn = $pollDate($poll['expires_at'] ?? null);
            @endphp
            <article class="nexus-alpha-card govuk-!-margin-bottom-6" id="poll-{{ $pollId }}">
                <div class="nexus-alpha-module-row">
                    <h2 class="govuk-heading-m govuk-!-margin-bottom-1">{{ $question }}</h2>
                    <strong class="govuk-tag {{ $isOpen ? 'govuk-tag--green' : 'govuk-tag--grey' }}">{{ $isOpen ? __('govuk_alpha_gamification.poll_manage.open_tag') : __('govuk_alpha_gamification.poll_manage.closed_tag') }}</strong>
                </div>
                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-3">
                    @if ($isRanked)<strong class="govuk-tag govuk-tag--purple">{{ __('govuk_alpha_gamification.poll_manage.ranked_tag') }}</strong> @endif
                    @if ($isAnon)<strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha_gamification.poll_manage.anonymous_tag') }}</strong> @endif
                    {{ trans_choice('govuk_alpha_gamification.poll_manage.votes_count', $totalVotes, ['count' => $totalVotes]) }}
                    @if ($closesOn) · {{ $closesOn }}@endif
                </p>

                <div class="nexus-alpha-actions govuk-button-group">
                    @if ($isRanked)
                        <a class="govuk-link" href="{{ route('govuk-alpha.gamification.poll.rank', ['tenantSlug' => $tenantSlug, 'pollId' => $pollId]) }}">{{ __('govuk_alpha_gamification.poll_manage.view_ranked') }}</a>
                    @endif
                    <a class="govuk-link" href="{{ route('govuk-alpha.gamification.poll.export', ['tenantSlug' => $tenantSlug, 'pollId' => $pollId]) }}">{{ __('govuk_alpha_gamification.poll_manage.export_button') }}</a>
                </div>

                {{-- Delete with explicit warning + confirmation --}}
                <details class="govuk-details govuk-!-margin-top-3" data-module="govuk-details">
                    <summary class="govuk-details__summary">
                        <span class="govuk-details__summary-text">{{ __('govuk_alpha_gamification.poll_manage.delete_button') }}</span>
                    </summary>
                    <div class="govuk-details__text">
                        <div class="govuk-warning-text">
                            <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                            <strong class="govuk-warning-text__text">
                                <span class="govuk-visually-hidden">{{ __('govuk_alpha_gamification.states.error_title') }}</span>
                                {{ __('govuk_alpha_gamification.poll_manage.delete_warning') }}
                            </strong>
                        </div>
                        <form method="post" action="{{ route('govuk-alpha.gamification.poll.delete', ['tenantSlug' => $tenantSlug, 'pollId' => $pollId]) }}">
                            @csrf
                            <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_gamification.poll_manage.delete_button') }}</button>
                        </form>
                    </div>
                </details>
            </article>
        @endforeach
    @endif
@endsection
