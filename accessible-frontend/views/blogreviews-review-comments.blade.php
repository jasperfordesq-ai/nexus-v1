{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $review = is_array($review ?? null) ? $review : [];
        $reviewId = (int) ($reviewId ?? ($review['id'] ?? 0));
        $comments = is_array($comments ?? null) ? $comments : [];
        $alphaReactions = $alphaReactions ?? [];
        $reviewReactionCounts = (array) ($reviewReactionCounts ?? []);
        $reviewReactionTotal = (int) ($reviewReactionTotal ?? 0);
        $reviewUserReaction = $reviewUserReaction ?? null;
        $rating = max(0, min(5, (int) ($review['rating'] ?? 0)));
        $isAnonymous = (bool) ($review['is_anonymous'] ?? false);
        $reviewer = is_array($review['reviewer'] ?? null) ? $review['reviewer'] : [];
        $resolvedReviewer = trim((string) ($reviewer['organization_name'] ?? ''));
        if ($resolvedReviewer === '') {
            $resolvedReviewer = trim((string) ($reviewer['name'] ?? '') ?: trim(($reviewer['first_name'] ?? '') . ' ' . ($reviewer['last_name'] ?? '')));
        }
        $reviewerName = $isAnonymous
            ? __('govuk_alpha_blogreviews.review_comments.anonymous')
            : ($resolvedReviewer !== '' ? $resolvedReviewer : __('govuk_alpha_blogreviews.review_comments.unknown_member'));
        $hiddenInputs = ['review_id' => $reviewId];
        $storeAction = route('govuk-alpha.blogreviews.reviews.comments.store', ['tenantSlug' => $tenantSlug, 'id' => $reviewId]);
        $reviewsListHref = route('govuk-alpha.blogreviews.reviews.list', ['tenantSlug' => $tenantSlug]);
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <a class="govuk-back-link" href="{{ $reviewsListHref }}">{{ __('govuk_alpha_blogreviews.review_comments.back_to_reviews') }}</a>

            <span class="govuk-caption-l">{{ __('govuk_alpha_blogreviews.review_comments.caption') }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha_blogreviews.review_comments.heading') }}</h1>

            @include('accessible-frontend::partials.blogreviews-comment-status', ['status' => $status ?? null, 'errorAnchor' => '#body'])

            {{-- The review being moderated --}}
            <div class="nexus-alpha-card govuk-!-margin-bottom-6">
                <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha_blogreviews.review_comments.by_label', ['name' => $reviewerName]) }}</h2>
                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">
                    <progress value="{{ $rating }}" max="5" aria-label="{{ __('govuk_alpha_blogreviews.review_comments.rating_aria', ['value' => $rating]) }}">{{ $rating }}/5</progress>
                    <span>{{ __('govuk_alpha_blogreviews.review_comments.rating_label', ['value' => $rating]) }}</span>
                </p>
                @if (trim((string) ($review['comment'] ?? '')) !== '')
                    <p class="govuk-body govuk-!-margin-bottom-0">{{ $review['comment'] }}</p>
                @endif
            </div>

            {{-- Reaction panel on the review --}}
            <section id="review-reactions" class="govuk-!-margin-bottom-6">
                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1" aria-live="polite">
                    {{ trans_choice('govuk_alpha_blogreviews.reactions.count', $reviewReactionTotal, ['count' => $reviewReactionTotal]) }}
                </p>
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s govuk-visually-hidden">{{ __('govuk_alpha_blogreviews.reactions.post_legend') }}</legend>
                    <div class="nexus-alpha-actions govuk-button-group govuk-!-margin-bottom-0">
                        @foreach ($alphaReactions as $reactionType => $reactionEmoji)
                            @php
                                $isSelected = $reviewUserReaction === $reactionType;
                                $typeCount = (int) ($reviewReactionCounts[$reactionType] ?? 0);
                                $reactionLabel = \Illuminate\Support\Facades\Lang::has('govuk_alpha_blogreviews.reactions.' . $reactionType)
                                    ? __('govuk_alpha_blogreviews.reactions.' . $reactionType)
                                    : $reactionType;
                            @endphp
                            <form method="post" action="{{ route('govuk-alpha.blogreviews.reviews.react', ['tenantSlug' => $tenantSlug, 'id' => $reviewId]) }}" class="nexus-alpha-reaction-form">
                                @csrf
                                <input type="hidden" name="emoji" value="{{ $reactionType }}">
                                <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0{{ $isSelected ? ' nexus-alpha-reaction--active' : '' }}" data-module="govuk-button" aria-pressed="{{ $isSelected ? 'true' : 'false' }}">
                                    <span aria-hidden="true">{{ $reactionEmoji }}</span>
                                    {{ $reactionLabel }}@if ($typeCount > 0) ({{ $typeCount }})@endif
                                    <span class="govuk-visually-hidden"> {{ __('govuk_alpha_blogreviews.reactions.for_review') }}</span>
                                </button>
                            </form>
                        @endforeach
                    </div>
                </fieldset>
            </section>

            <hr class="govuk-section-break govuk-section-break--visible govuk-section-break--l">

            <section id="comments">
                <h2 class="govuk-heading-l">
                    {{ __('govuk_alpha_blogreviews.review_comments.review_summary') }}
                    <span class="govuk-!-font-weight-regular">({{ (int) ($commentsCount ?? 0) }})</span>
                </h2>

                @if (empty($comments))
                    <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_blogreviews.review_comments.empty') }}</p></div>
                @else
                    <ul class="govuk-list nexus-alpha-comments-list">
                        @foreach ($comments as $comment)
                            @include('accessible-frontend::partials.blogreviews-comment', [
                                'comment' => $comment,
                                'depth' => 0,
                                'tenantSlug' => $tenantSlug,
                                'currentUserId' => $currentUserId ?? null,
                                'alphaReactions' => $alphaReactions,
                                'storeAction' => $storeAction,
                                'updateRoute' => 'govuk-alpha.blogreviews.blog.comments.update',
                                'deleteRoute' => 'govuk-alpha.blogreviews.blog.comments.delete',
                                'reactRoute' => 'govuk-alpha.blogreviews.blog.comments.react',
                                'hiddenInputs' => $hiddenInputs,
                            ])
                        @endforeach
                    </ul>
                @endif

                <h3 class="govuk-heading-m govuk-!-margin-top-6">{{ __('govuk_alpha_blogreviews.review_comments.add_heading') }}</h3>
                <form method="post" action="{{ $storeAction }}">
                    @csrf
                    <div class="govuk-form-group {{ ($status ?? null) === 'comment-invalid' ? 'govuk-form-group--error' : '' }}">
                        <label class="govuk-label govuk-label--s" for="body">{{ __('govuk_alpha_blogreviews.review_comments.body_label') }}</label>
                        @if (($status ?? null) === 'comment-invalid')
                            <p id="body-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_blogreviews.states.error_prefix') }}</span> {{ __('govuk_alpha_blogreviews.comment_states.comment-invalid') }}</p>
                        @endif
                        <textarea class="govuk-textarea" id="body" name="body" rows="4" maxlength="5000" {{ ($status ?? null) === 'comment-invalid' ? 'aria-describedby="body-error"' : '' }}></textarea>
                    </div>
                    <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_blogreviews.review_comments.submit') }}</button>
                </form>
            </section>
        </div>
    </div>
@endsection
