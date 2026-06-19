{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $conversations = $conversations ?? [];
        $messages = $messages ?? [];
        $selectedId = (int) ($selectedId ?? 0);
        $fmt = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j M Y, g:ia') : null;
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.explore', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_aichat.caption') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_aichat.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_aichat.description') }}</p>

    <div class="govuk-warning-text">
        <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
        <strong class="govuk-warning-text__text">
            <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.warning') ?? 'Warning' }}</span>
            {{ __('govuk_alpha_aichat.ai_notice') }}
        </strong>
    </div>

    @if (($status ?? null) === 'empty')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li><a href="#message">{{ __('govuk_alpha_aichat.status_empty') }}</a></li></ul></div>
            </div>
        </div>
    @endif

    @unless ($aiEnabled ?? true)
        <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="ai-disabled-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="ai-disabled-title">{{ __('govuk_alpha.states.error_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-body">{{ __('govuk_alpha_aichat.disabled_notice') }}</p>
            </div>
        </div>
    @endunless

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-one-third">
            <h2 class="govuk-heading-m">{{ __('govuk_alpha_aichat.conversations_title') }}</h2>
            <p class="govuk-body"><a class="govuk-link" href="{{ route('govuk-alpha.chat.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_aichat.new_conversation') }}</a></p>
            @if (empty($conversations))
                <p class="govuk-hint">{{ __('govuk_alpha_aichat.no_conversations') }}</p>
            @else
                <ul class="govuk-list govuk-list--spaced">
                    @foreach ($conversations as $conv)
                        @php($convId = (int) ($conv['id'] ?? 0))
                        <li>
                            @if ($convId === $selectedId)
                                <strong>{{ \Illuminate\Support\Str::limit((string) ($conv['title'] ?? __('govuk_alpha_aichat.thread_title')), 60) }}</strong>
                            @else
                                <a class="govuk-link" href="{{ route('govuk-alpha.chat.index', ['tenantSlug' => $tenantSlug, 'c' => $convId]) }}">{{ \Illuminate\Support\Str::limit((string) ($conv['title'] ?? __('govuk_alpha_aichat.thread_title')), 60) }}</a>
                            @endif
                            @if (!empty($conv['updated_at']))
                                <br><span class="govuk-hint govuk-!-font-size-16">{{ $fmt($conv['updated_at']) }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="govuk-grid-column-two-thirds">
            <h2 class="govuk-heading-m">{{ __('govuk_alpha_aichat.thread_title') }}</h2>
            @if (empty($messages))
                <p class="govuk-inset-text">{{ __('govuk_alpha_aichat.empty_thread') }}</p>
            @else
                <ol class="govuk-list govuk-list--spaced">
                    @foreach ($messages as $m)
                        @php
                            $role = (string) ($m['role'] ?? 'assistant');
                            $who = $role === 'user' ? __('govuk_alpha_aichat.you') : __('govuk_alpha_aichat.assistant');
                            $tagClass = $role === 'user' ? 'govuk-tag--blue' : 'govuk-tag--green';
                        @endphp
                        <li class="nexus-alpha-card">
                            <p class="govuk-!-margin-bottom-1"><strong class="govuk-tag {{ $tagClass }}">{{ $who }}</strong></p>
                            <div class="govuk-body govuk-!-margin-bottom-0">{!! nl2br(e((string) ($m['content'] ?? ''))) !!}</div>
                        </li>
                    @endforeach
                </ol>
            @endif

            <form method="post" action="{{ route('govuk-alpha.chat.send', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-top-4">
                @csrf
                @if ($selectedId > 0)
                    <input type="hidden" name="conversation_id" value="{{ $selectedId }}">
                @endif
                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--m" for="message">{{ __('govuk_alpha_aichat.message_label') }}</label>
                    <div id="message-hint" class="govuk-hint">{{ __('govuk_alpha_aichat.message_hint') }}</div>
                    <textarea class="govuk-textarea" id="message" name="message" rows="4" maxlength="4000" aria-describedby="message-hint" required></textarea>
                </div>
                <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_aichat.send') }}</button>
            </form>
        </div>
    </div>
@endsection
