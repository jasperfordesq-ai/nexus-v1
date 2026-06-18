{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $commentList = is_array($comments ?? null) ? $comments : [];
        $count = (int) ($commentsCount ?? 0);
        $statusValue = $status ?? null;
        $successStates = ['comment-added', 'reply-added'];
        $errorStates = ['comment-invalid', 'comment-failed'];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $listingId]) }}">{{ __('govuk_alpha_listings.comments.back_to_listing') }}</a>

    @if (in_array($statusValue, $errorStates, true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        <li><a href="#body">{{ __('govuk_alpha_listings.comments.states.' . $statusValue) }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
    @elseif (in_array($statusValue, $successStates, true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="comment-status-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="comment-status-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha_listings.comments.states.' . $statusValue) }}</p>
            </div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ $listingTitle }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_listings.comments.title') }} <span class="govuk-!-font-weight-regular">({{ $count }})</span></h1>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <h2 class="govuk-heading-l">{{ __('govuk_alpha_listings.comments.heading') }}</h2>

            @if (empty($commentList))
                <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_listings.comments.empty') }}</p></div>
            @else
                <ul class="govuk-list nexus-alpha-comments-list">
                    @foreach ($commentList as $comment)
                        @include('accessible-frontend::partials.listings-comment', ['comment' => $comment, 'depth' => 0])
                    @endforeach
                </ul>
            @endif

            <h2 class="govuk-heading-m govuk-!-margin-top-6" id="add-comment">{{ __('govuk_alpha_listings.comments.add_heading') }}</h2>
            <form method="post" action="{{ route('govuk-alpha.listings.comments.store', ['tenantSlug' => $tenantSlug, 'id' => $listingId]) }}" class="govuk-!-margin-top-2">
                @csrf
                <div class="govuk-form-group {{ $statusValue === 'comment-invalid' ? 'govuk-form-group--error' : '' }}">
                    <label class="govuk-label govuk-label--s" for="body">{{ __('govuk_alpha_listings.comments.body_label') }}</label>
                    <div id="body-hint" class="govuk-hint">{{ __('govuk_alpha_listings.comments.body_hint') }}</div>
                    @if ($statusValue === 'comment-invalid')
                        <p id="body-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ __('govuk_alpha_listings.comments.states.comment-invalid') }}</p>
                    @endif
                    <textarea class="govuk-textarea {{ $statusValue === 'comment-invalid' ? 'govuk-textarea--error' : '' }}" id="body" name="body" rows="4" maxlength="5000" aria-describedby="body-hint{{ $statusValue === 'comment-invalid' ? ' body-error' : '' }}"></textarea>
                </div>
                <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_listings.comments.submit') }}</button>
            </form>
        </div>
    </div>
@endsection
