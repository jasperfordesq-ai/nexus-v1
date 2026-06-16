{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $gId = (int) ($group['id'] ?? 0);
        $gName = trim((string) ($group['name'] ?? ''));
        $dId = (int) ($discussion['id'] ?? 0);
        $dTitle = trim((string) ($discussion['title'] ?? ''));
        $dAuthor = trim((string) ($discussion['author']['name'] ?? ''));
        $dContent = (string) ($discussion['content'] ?? '');
        $dCreatedAt = !empty($discussion['created_at']) ? \Illuminate\Support\Carbon::parse($discussion['created_at']) : null;
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.groups.discussions.index', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}">{{ __('govuk_alpha.groups.discussions.back') }}</a>

    <span class="govuk-caption-l">{{ $gName }}</span>
    <h1 class="govuk-heading-xl">{{ $dTitle !== '' ? $dTitle : __('govuk_alpha.groups.discussions.title') }}</h1>

    @if (($status ?? null) === 'reply-posted')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-live="polite" aria-labelledby="reply-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="reply-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.groups.states.reply-posted') }}</p></div>
        </div>
    @endif

    @if ($errors->any() || ($status ?? null) === 'reply-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    @if ($errors->any())
                        <ul class="govuk-list govuk-error-summary__list">
                            @foreach ($errors->keys() as $field)
                                <li><a href="#{{ $field }}">{{ $errors->first($field) }}</a></li>
                            @endforeach
                        </ul>
                    @else
                        <p class="govuk-body">{{ __('govuk_alpha.groups.states.reply-failed') }}</p>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <article class="nexus-alpha-card govuk-!-margin-bottom-4">
        @if ($dAuthor !== '')
            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">
                {{ __('govuk_alpha.groups.discussions.started_by', ['name' => $dAuthor]) }}
                @if ($dCreatedAt)
                    <span aria-hidden="true"> · </span>
                    <time datetime="{{ $dCreatedAt->toIso8601String() }}">{{ $dCreatedAt->translatedFormat('j F Y') }}</time>
                @endif
            </p>
        @endif
        <div class="govuk-body">{!! nl2br(e(strip_tags($dContent))) !!}</div>
    </article>

    <h2 class="govuk-heading-l" id="discussion-replies">{{ __('govuk_alpha.groups.discussions.replies_count', ['count' => count($messages)]) }}</h2>

    @if (empty($messages))
        <p class="govuk-inset-text">{{ __('govuk_alpha.groups.discussions.no_replies') }}</p>
    @else
        @foreach ($messages as $msg)
            @php
                $msgAuthor = trim((string) ($msg['author']['name'] ?? ''));
                $msgContent = (string) ($msg['content'] ?? '');
                $msgCreatedAt = !empty($msg['created_at']) ? \Illuminate\Support\Carbon::parse($msg['created_at']) : null;
            @endphp
            <article class="nexus-alpha-card govuk-!-margin-bottom-3">
                @if ($msgAuthor !== '')
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">
                        {{ __('govuk_alpha.polish_groups.reply_posted_by', ['name' => $msgAuthor]) }}
                        @if ($msgCreatedAt)
                            <span aria-hidden="true"> · </span>
                            <time datetime="{{ $msgCreatedAt->toIso8601String() }}">{{ $msgCreatedAt->translatedFormat('j F Y, H:i') }}</time>
                        @endif
                    </p>
                @endif
                <div class="govuk-body govuk-!-margin-bottom-0">{!! nl2br(e(strip_tags($msgContent))) !!}</div>
            </article>
        @endforeach
    @endif

    <form method="post" action="{{ route('govuk-alpha.groups.discussions.reply', ['tenantSlug' => $tenantSlug, 'id' => $gId, 'discussionId' => $dId]) }}" novalidate>
        @csrf
        <div class="govuk-form-group{{ $errors->has('content') ? ' govuk-form-group--error' : '' }}">
            <label class="govuk-label govuk-label--m" for="content">{{ __('govuk_alpha.groups.discussions.reply_label') }}</label>
            <div id="content-hint" class="govuk-hint">{{ __('govuk_alpha.groups.discussions.reply_hint') }}</div>
            @error('content')
                <p id="content-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $message }}</p>
            @enderror
            <textarea class="govuk-textarea{{ $errors->has('content') ? ' govuk-textarea--error' : '' }}" id="content" name="content" rows="4" aria-describedby="content-hint{{ $errors->has('content') ? ' content-error' : '' }}">{{ old('content') }}</textarea>
        </div>
        <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.groups.discussions.reply_submit') }}</button>
    </form>
@endsection
