{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $post = $post ?? [];
        $slug = (string) ($post['slug'] ?? '');
        $postTitle = (string) ($post['title'] ?? '');
        $comments = is_array($comments ?? null) ? $comments : [];
        $alphaReactions = $alphaReactions ?? [];
        $postReactionCounts = (array) ($postReactionCounts ?? []);
        $postReactionTotal = (int) ($postReactionTotal ?? 0);
        $postUserReaction = $postUserReaction ?? null;
        $hiddenInputs = ['slug' => $slug];
        $storeAction = route('govuk-alpha.blogreviews.blog.comments.store', ['tenantSlug' => $tenantSlug, 'slug' => $slug]);
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <a class="govuk-back-link" href="{{ route('govuk-alpha.blog.show', ['tenantSlug' => $tenantSlug, 'slug' => $slug]) }}">{{ __('govuk_alpha_blogreviews.comments.back_to_post') }}</a>

            <span class="govuk-caption-l">{{ __('govuk_alpha_blogreviews.comments.caption') }}</span>
            <h1 class="govuk-heading-xl">{{ $postTitle }}</h1>

            @include('accessible-frontend::partials.blogreviews-comment-status', ['status' => $status ?? null, 'errorAnchor' => '#body'])

            {{-- Post-level reaction panel (likes), parity with SocialInteractionPanel --}}
            <section id="post-reactions" class="govuk-!-margin-bottom-6">
                <h2 class="govuk-heading-m">{{ __('govuk_alpha_blogreviews.reactions.post_legend') }}</h2>
                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1" aria-live="polite">
                    {{ trans_choice('govuk_alpha_blogreviews.reactions.count', $postReactionTotal, ['count' => $postReactionTotal]) }}
                </p>
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s govuk-visually-hidden">{{ __('govuk_alpha_blogreviews.reactions.post_legend') }}</legend>
                    <div class="nexus-alpha-actions govuk-button-group govuk-!-margin-bottom-2">
                        @foreach ($alphaReactions as $reactionType => $reactionEmoji)
                            @php
                                $isSelected = $postUserReaction === $reactionType;
                                $typeCount = (int) ($postReactionCounts[$reactionType] ?? 0);
                                $reactionLabel = \Illuminate\Support\Facades\Lang::has('govuk_alpha_blogreviews.reactions.' . $reactionType)
                                    ? __('govuk_alpha_blogreviews.reactions.' . $reactionType)
                                    : $reactionType;
                            @endphp
                            <form method="post" action="{{ route('govuk-alpha.blogreviews.blog.react', ['tenantSlug' => $tenantSlug, 'slug' => $slug]) }}" class="nexus-alpha-reaction-form">
                                @csrf
                                <input type="hidden" name="emoji" value="{{ $reactionType }}">
                                <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0{{ $isSelected ? ' nexus-alpha-reaction--active' : '' }}" data-module="govuk-button" aria-pressed="{{ $isSelected ? 'true' : 'false' }}">
                                    <span aria-hidden="true">{{ $reactionEmoji }}</span>
                                    {{ $reactionLabel }}@if ($typeCount > 0) ({{ $typeCount }})@endif
                                    <span class="govuk-visually-hidden"> {{ __('govuk_alpha_blogreviews.reactions.for_post') }}</span>
                                </button>
                            </form>
                        @endforeach
                    </div>
                </fieldset>
                {{-- Likers: one link per reaction type that has at least one reaction --}}
                @php $likerLinks = array_filter($postReactionCounts, fn ($c) => (int) $c > 0); @endphp
                @if (!empty($likerLinks))
                    <ul class="govuk-list nexus-alpha-inline-list govuk-!-margin-bottom-0">
                        @foreach ($likerLinks as $reactionType => $count)
                            @if (array_key_exists($reactionType, $alphaReactions))
                                <li>
                                    <a class="govuk-link" href="{{ route('govuk-alpha.blogreviews.blog.likers', ['tenantSlug' => $tenantSlug, 'slug' => $slug, 'reaction' => $reactionType]) }}">
                                        <span aria-hidden="true">{{ $alphaReactions[$reactionType] }}</span>
                                        {{ __('govuk_alpha_blogreviews.reactions.view_likers') }} ({{ (int) $count }})
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                @endif
            </section>

            <hr class="govuk-section-break govuk-section-break--visible govuk-section-break--l">

            <section id="comments">
                <h2 class="govuk-heading-l">
                    {{ __('govuk_alpha_blogreviews.comments.heading') }}
                    <span class="govuk-!-font-weight-regular">({{ (int) ($commentsCount ?? 0) }})</span>
                </h2>

                @if (empty($comments))
                    <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_blogreviews.comments.empty') }}</p></div>
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

                <h3 class="govuk-heading-m govuk-!-margin-top-6">{{ __('govuk_alpha_blogreviews.comments.add_heading') }}</h3>
                <form method="post" action="{{ $storeAction }}">
                    @csrf
                    <div class="govuk-form-group {{ ($status ?? null) === 'comment-invalid' ? 'govuk-form-group--error' : '' }}">
                        <label class="govuk-label govuk-label--s" for="body">{{ __('govuk_alpha_blogreviews.comments.body_label') }}</label>
                        <div id="body-hint" class="govuk-hint">{{ __('govuk_alpha_blogreviews.comments.body_hint') }}</div>
                        @if (($status ?? null) === 'comment-invalid')
                            <p id="body-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_blogreviews.states.error_prefix') }}</span> {{ __('govuk_alpha_blogreviews.comment_states.comment-invalid') }}</p>
                        @endif
                        <textarea class="govuk-textarea" id="body" name="body" rows="4" maxlength="5000" aria-describedby="body-hint{{ ($status ?? null) === 'comment-invalid' ? ' body-error' : '' }}"></textarea>
                    </div>
                    <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_blogreviews.comments.submit') }}</button>
                </form>
            </section>
        </div>
    </div>
@endsection
