{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $cTitle = trim((string) ($challenge['title'] ?? '')) ?: __('govuk_alpha.ideation.title');
        $cStatus = (string) ($challenge['status'] ?? 'draft');
        [$tagClass, $tagLabel] = match ($cStatus) {
            'open' => ['govuk-tag--green', __('govuk_alpha.ideation.status_open')],
            'voting' => ['govuk-tag--blue', __('govuk_alpha.ideation.status_voting')],
            'evaluating' => ['govuk-tag--purple', __('govuk_alpha.ideation.status_evaluating')],
            'closed', 'archived' => ['govuk-tag--grey', __('govuk_alpha.ideation.status_closed')],
            default => ['govuk-tag--grey', __('govuk_alpha.ideation.status_draft')],
        };
        // Ideas may be submitted while a challenge is open or in voting; votes are
        // only meaningful in those phases too. Closed/archived challenges are read-only.
        $isOpenForIdeas = in_array($cStatus, ['open', 'voting'], true);
        $isOpenForVotes = in_array($cStatus, ['open', 'voting'], true);
    @endphp

    <a href="{{ route('govuk-alpha.ideation.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.ideation.back') }}</a>

    @if (in_array($status, ['idea-submitted', 'idea-voted'], true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="idea-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="idea-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.ideation.states.' . $status) }}</p></div>
        </div>
    @elseif (in_array($status, ['idea-invalid', 'idea-failed'], true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert"><h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        {{-- The submit form (and #idea_title) only renders while the challenge is open;
                             linking to it when closed would be a dead anchor, so drop the link then. --}}
                        <li>@if ($isOpenForIdeas)<a href="#idea_title">{{ __('govuk_alpha.ideation.states.' . $status) }}</a>@else{{ __('govuk_alpha.ideation.states.' . $status) }}@endif</li>
                    </ul>
                </div></div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ __('govuk_alpha.ideation.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <div class="nexus-alpha-module-row">
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">{{ $cTitle }}</h1>
        <strong class="govuk-tag {{ $tagClass }}">{{ $tagLabel }}</strong>
    </div>

    @if (trim((string) ($challenge['description'] ?? '')) !== '')
        <div class="govuk-body-l">{!! nl2br(e($challenge['description'])) !!}</div>
    @endif

    @if (trim((string) ($challenge['prize_description'] ?? '')) !== '')
        <div class="govuk-inset-text">
            <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha.ideation.prize_label') }}</h2>
            <p class="govuk-body govuk-!-margin-bottom-0">{{ $challenge['prize_description'] }}</p>
        </div>
    @endif

    <h2 class="govuk-heading-l govuk-!-margin-top-6" id="ideas">{{ __('govuk_alpha.ideation.ideas_title') }}</h2>
    @if (empty($ideas))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.ideation.ideas_empty') }}</p></div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($ideas as $idea)
                @php
                    $iTitle = trim((string) ($idea['title'] ?? '')) ?: __('govuk_alpha.ideation.idea_title_label');
                    $iVotes = (int) ($idea['vote_count'] ?? 0);
                    $iAuthor = trim((string) ($idea['creator']['name'] ?? ''));
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1" id="idea-{{ $idea['id'] }}-title">{{ $iTitle }}</h3>
                        <strong class="govuk-tag govuk-tag--blue">{{ trans_choice('govuk_alpha.ideation.votes', $iVotes, ['count' => $iVotes]) }}</strong>
                    </div>
                    @if (trim((string) ($idea['description'] ?? '')) !== '')
                        <p class="govuk-body govuk-!-margin-bottom-1">{{ \Illuminate\Support\Str::limit($idea['description'], 240) }}</p>
                    @endif
                    @if ($iAuthor !== '')
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">{{ __('govuk_alpha.ideation.idea_by', ['name' => $iAuthor]) }}</p>
                    @endif
                    @if ($isOpenForVotes)
                        <form method="post" action="{{ route('govuk-alpha.ideation.ideas.vote', ['tenantSlug' => $tenantSlug, 'id' => $challenge['id'], 'ideaId' => $idea['id']]) }}">
                            @csrf
                            <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button"
                                aria-describedby="idea-{{ $idea['id'] }}-title"
                                aria-label="{{ __('govuk_alpha.polish_discovery.idea_vote_aria', ['title' => $iTitle]) }}">{{ __('govuk_alpha.ideation.vote_button') }}</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    @endif

    @if ($isOpenForIdeas)
        <h2 class="govuk-heading-l govuk-!-margin-top-6" id="submit">{{ __('govuk_alpha.ideation.submit_title') }}</h2>
        <form method="post" action="{{ route('govuk-alpha.ideation.ideas.store', ['tenantSlug' => $tenantSlug, 'id' => $challenge['id']]) }}">
            @csrf
            <div class="govuk-form-group {{ $status === 'idea-invalid' ? 'govuk-form-group--error' : '' }}">
                <label class="govuk-label" for="idea_title">{{ __('govuk_alpha.ideation.idea_title_label') }}</label>
                @if ($status === 'idea-invalid')
                    <p id="idea_title-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ __('govuk_alpha.ideation.states.idea-invalid') }}</p>
                @endif
                <input class="govuk-input" id="idea_title" name="idea_title" type="text" maxlength="255" {{ $status === 'idea-invalid' ? 'aria-describedby=idea_title-error' : '' }}>
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="idea_content">{{ __('govuk_alpha.ideation.idea_content_label') }}</label>
                <textarea class="govuk-textarea" id="idea_content" name="idea_content" rows="5" maxlength="5000"></textarea>
            </div>
            <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.ideation.submit_button') }}</button>
        </form>
    @endif
@endsection
