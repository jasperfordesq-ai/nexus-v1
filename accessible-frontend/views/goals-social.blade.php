{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.goals.show', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}">{{ __('govuk_alpha_goals.common.back_to_goal') }}</a>

    @php
        $gTitle = trim((string) ($goal['title'] ?? '')) ?: __('govuk_alpha_goals.social.title');
        $likeCount = (int) ($likeCount ?? 0);
        $liked = (bool) ($liked ?? false);
        $comments = is_array($comments ?? null) ? $comments : [];
        $commentCount = (int) ($commentCount ?? 0);
        $currentUserId = (int) ($currentUserId ?? 0);
        $successStates = ['liked', 'unliked', 'comment-added', 'reply-added', 'comment-deleted'];
        $errorStates = ['like-failed', 'comment-invalid', 'comment-failed', 'comment-delete-failed'];
        $fmtDate = function ($value): ?\Illuminate\Support\Carbon {
            if (empty($value)) {
                return null;
            }
            try {
                return \Illuminate\Support\Carbon::parse($value);
            } catch (\Throwable $e) {
                return null;
            }
        };
    @endphp

    @if (in_array($status, $successStates, true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="social-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="social-status">{{ __('govuk_alpha_goals.common.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_goals.states.' . $status) }}</p></div>
        </div>
    @elseif (in_array($status, $errorStates, true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_goals.common.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        <li><a href="#body">{{ __('govuk_alpha_goals.states.' . $status) }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ __('govuk_alpha_goals.social.caption') }}: {{ $gTitle }}</span>
    <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">{{ __('govuk_alpha_goals.social.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_goals.social.intro') }}</p>

    {{-- Likes (heart toggle), parity with SocialInteractionPanel --}}
    <section id="likes" class="govuk-!-margin-bottom-6">
        <h2 class="govuk-heading-l">{{ __('govuk_alpha_goals.social.likes_heading') }}</h2>
        <p class="govuk-body nexus-alpha-meta govuk-!-margin-bottom-2" aria-live="polite">
            {{ trans_choice('govuk_alpha_goals.social.likes_count', $likeCount, ['count' => $likeCount]) }}
        </p>
        <form method="post" action="{{ route('govuk-alpha.goals.like', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}">
            @csrf
            <button class="govuk-button {{ $liked ? '' : 'govuk-button--secondary' }} govuk-!-margin-bottom-0" data-module="govuk-button" aria-pressed="{{ $liked ? 'true' : 'false' }}">
                <span aria-hidden="true">{{ $liked ? '♥' : '♡' }}</span>
                {{ $liked ? __('govuk_alpha_goals.social.unlike_button') : __('govuk_alpha_goals.social.like_button') }}
            </button>
        </form>
    </section>

    <hr class="govuk-section-break govuk-section-break--visible govuk-section-break--l">

    {{-- Comments --}}
    <section id="comments">
        <h2 class="govuk-heading-l">
            {{ __('govuk_alpha_goals.social.comments_heading') }}
            <span class="govuk-!-font-weight-regular">({{ $commentCount }})</span>
        </h2>
        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-3" aria-live="polite">
            {{ trans_choice('govuk_alpha_goals.social.comments_count', $commentCount, ['count' => $commentCount]) }}
        </p>

        @if (empty($comments))
            <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_goals.social.comments_empty') }}</p></div>
        @else
            <ul class="govuk-list nexus-alpha-comments-list">
                @foreach ($comments as $comment)
                    @php
                        $cId = (int) ($comment['id'] ?? 0);
                        $cAuthor = trim((string) ($comment['author']['name'] ?? '')) ?: __('govuk_alpha_goals.social.author_fallback');
                        $cIsOwn = !empty($comment['is_own']) && $cId > 0;
                        $cEdited = !empty($comment['edited']);
                        $cWhen = $fmtDate($comment['created_at'] ?? null);
                        $cReplies = is_array($comment['replies'] ?? null) ? $comment['replies'] : [];
                    @endphp
                    <li class="nexus-alpha-comment" id="comment-{{ $cId }}">
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">
                            <strong>{{ $cAuthor }}</strong>
                            @if ($cIsOwn)
                                <span class="govuk-!-font-weight-regular"> ({{ __('govuk_alpha_goals.social.you') }})</span>
                            @endif
                            @if ($cEdited)
                                <span class="govuk-!-font-weight-regular"> ({{ __('govuk_alpha_goals.social.edited') }})</span>
                            @endif
                            @if ($cWhen)
                                <span aria-hidden="true"> | </span>
                                <time datetime="{{ $cWhen->toIso8601String() }}">{{ $cWhen->translatedFormat('j F Y, H:i') }}</time>
                            @endif
                        </p>
                        <p class="govuk-body govuk-!-margin-bottom-2">{{ $comment['content'] ?? '' }}</p>

                        @if ($cId > 0)
                            <div class="nexus-alpha-comment-actions govuk-!-margin-bottom-2">
                                {{-- Reply (one nesting level, matching the threaded service output) --}}
                                <details class="govuk-details govuk-!-margin-bottom-0" data-module="govuk-details">
                                    <summary class="govuk-details__summary">
                                        <span class="govuk-details__summary-text">{{ __('govuk_alpha_goals.social.reply_link') }}</span>
                                    </summary>
                                    <div class="govuk-details__text">
                                        <form method="post" action="{{ route('govuk-alpha.goals.comments.store', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}">
                                            @csrf
                                            <input type="hidden" name="parent_id" value="{{ $cId }}">
                                            <div class="govuk-form-group">
                                                <label class="govuk-label govuk-label--s" for="reply-{{ $cId }}">{{ __('govuk_alpha_goals.social.reply_label') }}</label>
                                                <textarea class="govuk-textarea" id="reply-{{ $cId }}" name="body" rows="3" maxlength="5000" required></textarea>
                                            </div>
                                            <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_goals.social.reply_submit') }}</button>
                                        </form>
                                    </div>
                                </details>
                                @if ($cIsOwn)
                                    <details class="govuk-details govuk-!-margin-bottom-0" data-module="govuk-details">
                                        <summary class="govuk-details__summary">
                                            <span class="govuk-details__summary-text">{{ __('govuk_alpha_goals.social.delete_comment') }}</span>
                                        </summary>
                                        <div class="govuk-details__text">
                                            <div class="govuk-warning-text">
                                                <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                                                <strong class="govuk-warning-text__text">
                                                    <span class="govuk-visually-hidden">{{ __('govuk_alpha_goals.common.error_title') }}</span>
                                                    {{ __('govuk_alpha_goals.social.delete_comment_warning') }}
                                                </strong>
                                            </div>
                                            <form method="post" action="{{ route('govuk-alpha.goals.comments.delete', ['tenantSlug' => $tenantSlug, 'id' => $goal['id'], 'commentId' => $cId]) }}">
                                                @csrf
                                                <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_goals.social.delete_comment') }}</button>
                                            </form>
                                        </div>
                                    </details>
                                @endif
                            </div>
                        @endif

                        @if (!empty($cReplies))
                            <ul class="govuk-list nexus-alpha-comments-list--nested">
                                @foreach ($cReplies as $reply)
                                    @php
                                        $rId = (int) ($reply['id'] ?? 0);
                                        $rAuthor = trim((string) ($reply['author']['name'] ?? '')) ?: __('govuk_alpha_goals.social.author_fallback');
                                        $rIsOwn = !empty($reply['is_own']) && $rId > 0;
                                        $rEdited = !empty($reply['edited']);
                                        $rWhen = $fmtDate($reply['created_at'] ?? null);
                                    @endphp
                                    <li class="nexus-alpha-comment" id="comment-{{ $rId }}">
                                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">
                                            <strong>{{ $rAuthor }}</strong>
                                            @if ($rIsOwn)
                                                <span class="govuk-!-font-weight-regular"> ({{ __('govuk_alpha_goals.social.you') }})</span>
                                            @endif
                                            @if ($rEdited)
                                                <span class="govuk-!-font-weight-regular"> ({{ __('govuk_alpha_goals.social.edited') }})</span>
                                            @endif
                                            @if ($rWhen)
                                                <span aria-hidden="true"> | </span>
                                                <time datetime="{{ $rWhen->toIso8601String() }}">{{ $rWhen->translatedFormat('j F Y, H:i') }}</time>
                                            @endif
                                        </p>
                                        <p class="govuk-body govuk-!-margin-bottom-2">{{ $reply['content'] ?? '' }}</p>
                                        @if ($rIsOwn && $rId > 0)
                                            <details class="govuk-details govuk-!-margin-bottom-0" data-module="govuk-details">
                                                <summary class="govuk-details__summary">
                                                    <span class="govuk-details__summary-text">{{ __('govuk_alpha_goals.social.delete_comment') }}</span>
                                                </summary>
                                                <div class="govuk-details__text">
                                                    <div class="govuk-warning-text">
                                                        <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                                                        <strong class="govuk-warning-text__text">
                                                            <span class="govuk-visually-hidden">{{ __('govuk_alpha_goals.common.error_title') }}</span>
                                                            {{ __('govuk_alpha_goals.social.delete_comment_warning') }}
                                                        </strong>
                                                    </div>
                                                    <form method="post" action="{{ route('govuk-alpha.goals.comments.delete', ['tenantSlug' => $tenantSlug, 'id' => $goal['id'], 'commentId' => $rId]) }}">
                                                        @csrf
                                                        <button class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_goals.social.delete_comment') }}</button>
                                                    </form>
                                                </div>
                                            </details>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        <h3 class="govuk-heading-m govuk-!-margin-top-6">{{ __('govuk_alpha_goals.social.add_comment_heading') }}</h3>
        <form method="post" action="{{ route('govuk-alpha.goals.comments.store', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}">
            @csrf
            <div class="govuk-form-group {{ $status === 'comment-invalid' ? 'govuk-form-group--error' : '' }}">
                <label class="govuk-label govuk-label--s" for="body">{{ __('govuk_alpha_goals.social.comment_label') }}</label>
                <div id="body-hint" class="govuk-hint">{{ __('govuk_alpha_goals.social.comment_help') }}</div>
                @if ($status === 'comment-invalid')
                    <p id="body-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_goals.common.error_title') }}</span> {{ __('govuk_alpha_goals.states.comment-invalid') }}</p>
                @endif
                <textarea class="govuk-textarea" id="body" name="body" rows="4" maxlength="5000" aria-describedby="body-hint{{ $status === 'comment-invalid' ? ' body-error' : '' }}"></textarea>
            </div>
            <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_goals.social.comment_submit') }}</button>
        </form>
    </section>
@endsection
