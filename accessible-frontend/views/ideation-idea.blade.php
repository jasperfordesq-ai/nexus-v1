{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $challengeId = (int) ($challenge['id'] ?? 0);
        $ideaId = (int) ($idea['id'] ?? 0);
        $iTitle = trim((string) ($idea['title'] ?? '')) ?: __('govuk_alpha_ideation.idea.title');
        $iStatus = (string) ($idea['status'] ?? 'submitted');
        $iVotes = (int) ($idea['votes_count'] ?? 0);
        $iAuthor = trim((string) ($idea['creator']['name'] ?? ''));
        $hasVoted = (bool) ($idea['has_voted'] ?? false);
        [$statusTagClass, $statusTagLabel] = match ($iStatus) {
            'shortlisted' => ['govuk-tag--yellow', __('govuk_alpha_ideation.idea.status_shortlisted')],
            'winner' => ['govuk-tag--green', __('govuk_alpha_ideation.idea.status_winner')],
            'withdrawn' => ['govuk-tag--grey', __('govuk_alpha_ideation.idea.status_withdrawn')],
            default => ['govuk-tag--blue', __('govuk_alpha_ideation.idea.status_submitted')],
        };
        $successStates = ['idea-voted', 'idea-status-updated', 'idea-deleted', 'comment-added', 'comment-deleted', 'media-added', 'converted'];
        $errorStates = ['idea-failed', 'comment-invalid', 'comment-failed', 'media-invalid', 'media-failed', 'convert-failed'];
    @endphp

    <a href="{{ route('govuk-alpha.ideation.show', ['tenantSlug' => $tenantSlug, 'id' => $challengeId]) }}" class="govuk-back-link">{{ __('govuk_alpha_ideation.idea.back_to_challenge') }}</a>

    @if (in_array($status, $successStates, true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="idea-status-banner">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="idea-status-banner">{{ __('govuk_alpha_ideation.common.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_ideation.states.' . $status) }}</p></div>
        </div>
    @elseif (in_array($status, $errorStates, true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_ideation.common.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list"><li>{{ __('govuk_alpha_ideation.states.' . $status) }}</li></ul>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ trim((string) ($challenge['title'] ?? '')) }}</span>
    <div class="nexus-alpha-module-row">
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">{{ $iTitle }}</h1>
        <strong class="govuk-tag {{ $statusTagClass }}">{{ $statusTagLabel }}</strong>
    </div>

    @if ($iAuthor !== '')
        <p class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha_ideation.idea.by', ['name' => $iAuthor]) }}</p>
    @endif

    <p class="govuk-body">
        <strong class="govuk-tag govuk-tag--blue">{{ trans_choice('govuk_alpha_ideation.idea.votes', $iVotes, ['count' => $iVotes]) }}</strong>
    </p>

    <h2 class="govuk-heading-m">{{ __('govuk_alpha_ideation.idea.description_heading') }}</h2>
    @if (trim((string) ($idea['description'] ?? '')) !== '')
        <div class="govuk-body">{!! nl2br(e($idea['description'])) !!}</div>
    @else
        <p class="govuk-body">{{ __('govuk_alpha_ideation.idea.no_description') }}</p>
    @endif

    {{-- Vote toggle --}}
    @if ($canVote || $hasVoted)
        <form method="post" action="{{ route('govuk-alpha.ideation.idea.vote', ['tenantSlug' => $tenantSlug, 'id' => $challengeId, 'ideaId' => $ideaId]) }}" class="govuk-!-margin-bottom-4">
            @csrf
            <button type="submit" class="govuk-button {{ $hasVoted ? 'govuk-button--secondary' : '' }} govuk-!-margin-bottom-0" data-module="govuk-button"
                aria-label="{{ __('govuk_alpha_ideation.idea.vote_aria', ['title' => $iTitle]) }}">
                {{ $hasVoted ? __('govuk_alpha_ideation.idea.unvote_button') : __('govuk_alpha_ideation.idea.vote_button') }}
            </button>
        </form>
    @endif

    {{-- Attachments / media --}}
    <h2 class="govuk-heading-l govuk-!-margin-top-6" id="media">{{ __('govuk_alpha_ideation.media.heading') }}</h2>
    @if (empty($media))
        <div class="govuk-inset-text">{{ __('govuk_alpha_ideation.media.empty') }}</div>
    @else
        <ul class="govuk-list">
            @foreach ($media as $m)
                @php
                    $mUrl = (string) ($m['url'] ?? '');
                    $mCaption = trim((string) ($m['caption'] ?? ''));
                    $mType = (string) ($m['media_type'] ?? 'link');
                    $mTypeLabel = __('govuk_alpha_ideation.media.type_' . (in_array($mType, ['image', 'video', 'document', 'link'], true) ? $mType : 'link'));
                @endphp
                <li class="govuk-!-margin-bottom-2">
                    <a class="govuk-link" href="{{ e($mUrl) }}" rel="noopener noreferrer" target="_blank">{{ $mCaption !== '' ? $mCaption : __('govuk_alpha_ideation.media.open') }}</a>
                    <span class="govuk-body-s nexus-alpha-meta">({{ $mTypeLabel }})</span>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($isOwner || $isAdmin)
        <details class="govuk-details" data-module="govuk-details">
            <summary class="govuk-details__summary"><span class="govuk-details__summary-text">{{ __('govuk_alpha_ideation.media.add_heading') }}</span></summary>
            <div class="govuk-details__text">
                <form method="post" action="{{ route('govuk-alpha.ideation.idea.media.store', ['tenantSlug' => $tenantSlug, 'id' => $challengeId, 'ideaId' => $ideaId]) }}">
                    @csrf
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="media_type">{{ __('govuk_alpha_ideation.media.type_label') }}</label>
                        <select class="govuk-select" id="media_type" name="media_type">
                            <option value="image">{{ __('govuk_alpha_ideation.media.type_image') }}</option>
                            <option value="video">{{ __('govuk_alpha_ideation.media.type_video') }}</option>
                            <option value="document">{{ __('govuk_alpha_ideation.media.type_document') }}</option>
                            <option value="link">{{ __('govuk_alpha_ideation.media.type_link') }}</option>
                        </select>
                    </div>
                    <div class="govuk-form-group {{ $status === 'media-invalid' ? 'govuk-form-group--error' : '' }}">
                        <label class="govuk-label" for="media_url">{{ __('govuk_alpha_ideation.media.url_label') }}</label>
                        <div id="media_url-hint" class="govuk-hint">{{ __('govuk_alpha_ideation.media.url_hint') }}</div>
                        @if ($status === 'media-invalid')
                            <p id="media_url-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_ideation.common.error_prefix') }}</span> {{ __('govuk_alpha_ideation.states.media-invalid') }}</p>
                        @endif
                        <input class="govuk-input" id="media_url" name="media_url" type="url" maxlength="1000" aria-describedby="media_url-hint {{ $status === 'media-invalid' ? 'media_url-error' : '' }}">
                    </div>
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="media_caption">{{ __('govuk_alpha_ideation.media.caption_label') }}</label>
                        <input class="govuk-input" id="media_caption" name="media_caption" type="text" maxlength="500">
                    </div>
                    <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha_ideation.media.submit') }}</button>
                </form>
            </div>
        </details>
    @endif

    {{-- Admin status controls --}}
    @if ($isAdmin)
        <h2 class="govuk-heading-l govuk-!-margin-top-6">{{ __('govuk_alpha_ideation.admin.controls_heading') }}</h2>
        <p class="govuk-body">{{ __('govuk_alpha_ideation.admin.status_help') }}</p>
        <form method="post" action="{{ route('govuk-alpha.ideation.idea.status', ['tenantSlug' => $tenantSlug, 'id' => $challengeId, 'ideaId' => $ideaId]) }}">
            @csrf
            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_ideation.idea.status_label') }}</legend>
                    <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                        @foreach ([
                            'submitted' => __('govuk_alpha_ideation.idea.status_submitted'),
                            'shortlisted' => __('govuk_alpha_ideation.idea.status_shortlisted'),
                            'winner' => __('govuk_alpha_ideation.idea.status_winner'),
                        ] as $val => $label)
                            <div class="govuk-radios__item">
                                <input class="govuk-radios__input" id="idea-status-{{ $val }}" name="idea_status" type="radio" value="{{ $val }}"{{ $iStatus === $val ? ' checked' : '' }}>
                                <label class="govuk-label govuk-radios__label" for="idea-status-{{ $val }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            </div>
            <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha_ideation.admin.update_status') }}</button>
        </form>
    @endif

    {{-- Convert to group --}}
    @if ($canConvert)
        <h2 class="govuk-heading-l govuk-!-margin-top-6">{{ __('govuk_alpha_ideation.convert.heading') }}</h2>
        <p class="govuk-body">{{ __('govuk_alpha_ideation.convert.intro') }}</p>
        <div class="govuk-warning-text">
            <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
            <strong class="govuk-warning-text__text"><span class="govuk-visually-hidden">{{ __('govuk_alpha_ideation.common.error_prefix') }}</span>{{ __('govuk_alpha_ideation.convert.warning') }}</strong>
        </div>
        <form method="post" action="{{ route('govuk-alpha.ideation.idea.convert', ['tenantSlug' => $tenantSlug, 'id' => $challengeId, 'ideaId' => $ideaId]) }}">
            @csrf
            <div class="govuk-form-group">
                <label class="govuk-label" for="group_name">{{ __('govuk_alpha_ideation.convert.name_label') }}</label>
                <input class="govuk-input" id="group_name" name="group_name" type="text" maxlength="255" value="{{ $iTitle }}">
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="group_description">{{ __('govuk_alpha_ideation.convert.description_label') }}</label>
                <textarea class="govuk-textarea" id="group_description" name="group_description" rows="4" maxlength="5000">{{ trim((string) ($idea['description'] ?? '')) }}</textarea>
            </div>
            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_ideation.convert.visibility_label') }}</legend>
                    <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                        @foreach ([
                            'public' => __('govuk_alpha_ideation.convert.visibility_public'),
                            'private' => __('govuk_alpha_ideation.convert.visibility_private'),
                            'secret' => __('govuk_alpha_ideation.convert.visibility_secret'),
                        ] as $val => $label)
                            <div class="govuk-radios__item">
                                <input class="govuk-radios__input" id="group-visibility-{{ $val }}" name="group_visibility" type="radio" value="{{ $val }}"{{ $val === 'public' ? ' checked' : '' }}>
                                <label class="govuk-label govuk-radios__label" for="group-visibility-{{ $val }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            </div>
            <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_ideation.convert.submit') }}</button>
        </form>
    @endif

    {{-- Delete idea --}}
    @if ($isOwner || $isAdmin)
        <details class="govuk-details govuk-!-margin-top-4" data-module="govuk-details">
            <summary class="govuk-details__summary"><span class="govuk-details__summary-text">{{ __('govuk_alpha_ideation.idea.delete_button') }}</span></summary>
            <div class="govuk-details__text">
                <div class="govuk-warning-text">
                    <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                    <strong class="govuk-warning-text__text"><span class="govuk-visually-hidden">{{ __('govuk_alpha_ideation.common.error_prefix') }}</span>{{ __('govuk_alpha_ideation.idea.delete_warning') }}</strong>
                </div>
                <form method="post" action="{{ route('govuk-alpha.ideation.idea.delete', ['tenantSlug' => $tenantSlug, 'id' => $challengeId, 'ideaId' => $ideaId]) }}">
                    @csrf
                    <button type="submit" class="govuk-button govuk-button--warning" data-module="govuk-button">{{ __('govuk_alpha_ideation.idea.delete_confirm') }}</button>
                </form>
            </div>
        </details>
    @endif

    {{-- Comments --}}
    <h2 class="govuk-heading-l govuk-!-margin-top-6" id="comments">{{ __('govuk_alpha_ideation.comments.heading') }}</h2>
    @if (empty($comments))
        <div class="govuk-inset-text">{{ __('govuk_alpha_ideation.comments.empty') }}</div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($comments as $c)
                @php
                    $cAuthor = trim((string) ($c['author']['name'] ?? ''));
                    $cAuthorId = (int) ($c['author']['id'] ?? 0);
                    $cId = (int) ($c['id'] ?? 0);
                    $canDeleteComment = $isAdmin || $cAuthorId === ($currentUserId ?? -1);
                @endphp
                <article class="nexus-alpha-card">
                    @if ($cAuthor !== '')
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha_ideation.comments.by', ['name' => $cAuthor]) }}</p>
                    @endif
                    <div class="govuk-body govuk-!-margin-bottom-1">{!! nl2br(e($c['body'] ?? '')) !!}</div>
                    @if ($canDeleteComment)
                        <form method="post" action="{{ route('govuk-alpha.ideation.idea.comments.delete', ['tenantSlug' => $tenantSlug, 'id' => $challengeId, 'ideaId' => $ideaId, 'commentId' => $cId]) }}">
                            @csrf
                            <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_ideation.comments.delete') }}</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    @endif

    @if ($canComment)
        <h3 class="govuk-heading-m govuk-!-margin-top-4">{{ __('govuk_alpha_ideation.comments.add_heading') }}</h3>
        <form method="post" action="{{ route('govuk-alpha.ideation.idea.comments.store', ['tenantSlug' => $tenantSlug, 'id' => $challengeId, 'ideaId' => $ideaId]) }}">
            @csrf
            <div class="govuk-form-group {{ $status === 'comment-invalid' ? 'govuk-form-group--error' : '' }}">
                <label class="govuk-label" for="comment_body">{{ __('govuk_alpha_ideation.comments.body_label') }}</label>
                @if ($status === 'comment-invalid')
                    <p id="comment_body-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_ideation.common.error_prefix') }}</span> {{ __('govuk_alpha_ideation.states.comment-invalid') }}</p>
                @endif
                <textarea class="govuk-textarea" id="comment_body" name="comment_body" rows="4" maxlength="5000" {{ $status === 'comment-invalid' ? 'aria-describedby=comment_body-error' : '' }}></textarea>
            </div>
            <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_ideation.comments.submit') }}</button>
        </form>
    @endif
@endsection
