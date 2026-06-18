{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $appreciations = $appreciations ?? [];
        $reactionTypes = $reactionTypes ?? ['heart', 'clap', 'star'];
        $ownerId = (int) ($ownerId ?? 0);
        $ownerName = trim((string) ($ownerName ?? ''));
        $ownerLabel = $ownerName !== '' ? $ownerName : __('govuk_alpha_saved.wall.from_someone');
        $isSelf = (bool) ($isSelf ?? false);
        $status = $status ?? null;
        $meta = $meta ?? ['current_page' => 1, 'last_page' => 1, 'total' => 0];
        $currentPage = (int) ($currentPage ?? 1);
        $lastPage = (int) ($meta['last_page'] ?? 1);
        $messageError = $status === 'appreciation-message-required';
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $ownerId]) }}">{{ __('govuk_alpha_saved.wall.back_to_profile') }}</a>

    @php
        $errorStatuses = ['appreciation-message-required', 'appreciation-self', 'appreciation-too-long', 'appreciation-rate-limited', 'appreciation-failed', 'reaction-failed'];
    @endphp

    {{-- Error summary BEFORE the h1 (validation / failure) --}}
    @if (in_array($status, $errorStatuses, true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_saved.errors.summary_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        <li>
                            @if ($status === 'appreciation-message-required')
                                <a href="#appreciation-message">{{ __('govuk_alpha_saved.status.appreciation_message_required') }}</a>
                            @else
                                @switch($status)
                                    @case('appreciation-self'){{ __('govuk_alpha_saved.status.appreciation_self') }}@break
                                    @case('appreciation-too-long'){{ __('govuk_alpha_saved.status.appreciation_too_long') }}@break
                                    @case('appreciation-rate-limited'){{ __('govuk_alpha_saved.status.appreciation_rate_limited') }}@break
                                    @case('reaction-failed'){{ __('govuk_alpha_saved.status.reaction_failed') }}@break
                                    @default{{ __('govuk_alpha_saved.status.appreciation_failed') }}
                                @endswitch
                            @endif
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ __('govuk_alpha_saved.wall.caption', ['name' => $ownerLabel]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_saved.wall.heading', ['name' => $ownerLabel]) }}</h1>

    {{-- Success banners --}}
    @if (in_array($status, ['appreciation-sent', 'reaction-updated'], true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="saved-wall-status-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="saved-wall-status-title">{{ __('govuk_alpha_saved.errors.summary_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">
                    {{ $status === 'appreciation-sent' ? __('govuk_alpha_saved.status.appreciation_sent') : __('govuk_alpha_saved.status.reaction_updated') }}
                </p>
            </div>
        </div>
    @endif

    <p class="govuk-body-l">{{ __('govuk_alpha_saved.wall.description') }}</p>

    {{-- Send a thank-you (hidden for own wall — service rejects self-thanks) --}}
    @if (!$isSelf)
        <h2 class="govuk-heading-l">{{ __('govuk_alpha_saved.send.heading_to', ['name' => $ownerLabel]) }}</h2>
        <p class="govuk-body">{{ __('govuk_alpha_saved.send.intro') }}</p>
        <form method="post" action="{{ route('govuk-alpha.saved.appreciations.send', ['tenantSlug' => $tenantSlug, 'userId' => $ownerId]) }}">
            @csrf
            <div class="govuk-form-group{{ $messageError ? ' govuk-form-group--error' : '' }}">
                <label class="govuk-label" for="appreciation-message">{{ __('govuk_alpha_saved.send.message_label') }}</label>
                <div id="appreciation-message-hint" class="govuk-hint">{{ __('govuk_alpha_saved.send.message_hint') }}</div>
                @if ($messageError)
                    <p id="appreciation-message-error" class="govuk-error-message">
                        <span class="govuk-visually-hidden">{{ __('govuk_alpha_saved.errors.summary_title') }}:</span>
                        {{ __('govuk_alpha_saved.status.appreciation_message_required') }}
                    </p>
                @endif
                <textarea class="govuk-textarea govuk-!-width-two-thirds" id="appreciation-message" name="message" rows="4" maxlength="500" aria-describedby="appreciation-message-hint{{ $messageError ? ' appreciation-message-error' : '' }}"></textarea>
            </div>
            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-visually-hidden">{{ __('govuk_alpha_saved.send.public_label') }}</legend>
                    <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="appreciation-public" name="is_public" type="checkbox" value="1" checked aria-describedby="appreciation-public-hint">
                            <label class="govuk-label govuk-checkboxes__label" for="appreciation-public">{{ __('govuk_alpha_saved.send.public_label') }}</label>
                            <div id="appreciation-public-hint" class="govuk-hint govuk-checkboxes__hint">{{ __('govuk_alpha_saved.send.public_hint') }}</div>
                        </div>
                    </div>
                </fieldset>
            </div>
            <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_saved.send.submit') }}</button>
        </form>
        <hr class="govuk-section-break govuk-section-break--visible govuk-section-break--l">
    @endif

    {{-- Appreciation notes --}}
    @if (empty($appreciations))
        <div class="govuk-inset-text">
            <h2 class="govuk-heading-m">{{ __('govuk_alpha_saved.wall.empty_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha_saved.wall.empty_body') }}</p>
        </div>
    @else
        <ul class="govuk-list nexus-alpha-card-list">
            @foreach ($appreciations as $a)
                @php
                    $aId = (int) ($a['id'] ?? 0);
                    $senderId = (int) ($a['sender_id'] ?? 0);
                    $senderName = trim((string) ($a['sender_name'] ?? ''));
                    $senderLabel = $senderName !== '' ? $senderName : __('govuk_alpha_saved.wall.from_someone');
                    $aMessage = trim((string) ($a['message'] ?? ''));
                    $aCount = (int) ($a['reactions_count'] ?? 0);
                    $myReaction = $a['my_reaction'] ?? null;
                    $aCreated = trim((string) ($a['created_at'] ?? ''));
                    $createdOn = '';
                    if ($aCreated !== '') {
                        try {
                            $createdOn = \Illuminate\Support\Carbon::parse($aCreated)->translatedFormat('j F Y');
                        } catch (\Throwable $e) {
                            $createdOn = '';
                        }
                    }
                @endphp
                <li class="nexus-alpha-card" id="appreciation-{{ $aId }}">
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">
                        @if ($senderId > 0)
                            <a class="govuk-link" href="{{ route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $senderId]) }}">{{ $senderLabel }}</a>
                        @else
                            {{ $senderLabel }}
                        @endif
                        @if ($createdOn !== '')
                            <span class="govuk-!-margin-left-1">{{ __('govuk_alpha_saved.wall.received_on', ['date' => $createdOn]) }}</span>
                        @endif
                    </p>
                    <p class="govuk-body">{{ $aMessage }}</p>

                    {{-- Reaction buttons (heart / clap / star) --}}
                    <fieldset class="govuk-fieldset govuk-!-margin-bottom-0">
                        <legend class="govuk-fieldset__legend govuk-visually-hidden">{{ __('govuk_alpha_saved.react.legend') }}</legend>
                        <div class="govuk-button-group govuk-!-margin-bottom-0">
                            @foreach ($reactionTypes as $rt)
                                @php
                                    $rtLabel = \Illuminate\Support\Facades\Lang::has('govuk_alpha_saved.react.' . $rt)
                                        ? __('govuk_alpha_saved.react.' . $rt)
                                        : \Illuminate\Support\Str::headline($rt);
                                    $isMine = $myReaction === $rt;
                                    $btnClass = $isMine ? 'govuk-button' : 'govuk-button govuk-button--secondary';
                                    $ariaLabel = $isMine
                                        ? __('govuk_alpha_saved.react.remove_label', ['reaction' => $rtLabel])
                                        : __('govuk_alpha_saved.react.react_label', ['reaction' => $rtLabel]);
                                @endphp
                                <form method="post" action="{{ route('govuk-alpha.saved.appreciations.react', ['tenantSlug' => $tenantSlug, 'id' => $aId]) }}" class="nexus-alpha-linkform govuk-!-display-inline-block">
                                    @csrf
                                    <input type="hidden" name="reaction_type" value="{{ $rt }}">
                                    <input type="hidden" name="owner_id" value="{{ $ownerId }}">
                                    <button type="submit" class="{{ $btnClass }} govuk-!-margin-bottom-0 govuk-!-font-size-16" data-module="govuk-button" aria-label="{{ $ariaLabel }}"{{ $isMine ? ' aria-pressed=true' : '' }}>
                                        {{ $rtLabel }}@if ($isMine) <span aria-hidden="true">&check;</span>@endif
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </fieldset>
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-top-1 govuk-!-margin-bottom-0">{{ trans_choice('govuk_alpha_saved.wall.reactions_count', $aCount, ['count' => $aCount]) }}</p>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- Pagination --}}
    @if ($lastPage > 1)
        <nav class="govuk-pagination" role="navigation" aria-label="{{ __('govuk_alpha_saved.pagination.page_of', ['current' => $currentPage, 'last' => $lastPage]) }}">
            @if ($currentPage > 1)
                <div class="govuk-pagination__prev">
                    <a class="govuk-link govuk-pagination__link" href="{{ route('govuk-alpha.saved.appreciations', ['tenantSlug' => $tenantSlug, 'userId' => $ownerId, 'page' => $currentPage - 1]) }}" rel="prev">
                        <svg class="govuk-pagination__icon govuk-pagination__icon--prev" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13"><path d="m6.5938-0.0078125-6.7266 6.7266 6.7441 6.7441 1.4062-1.4062-4.3008-4.3398h11.379v-2h-11.397l4.2998-4.2998-1.4063-1.4063z"></path></svg>
                        <span class="govuk-pagination__link-title">{{ __('govuk_alpha_saved.pagination.previous') }}</span>
                    </a>
                </div>
            @endif
            @if ($currentPage < $lastPage)
                <div class="govuk-pagination__next">
                    <a class="govuk-link govuk-pagination__link" href="{{ route('govuk-alpha.saved.appreciations', ['tenantSlug' => $tenantSlug, 'userId' => $ownerId, 'page' => $currentPage + 1]) }}" rel="next">
                        <span class="govuk-pagination__link-title">{{ __('govuk_alpha_saved.pagination.next') }}</span>
                        <svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13"><path d="m8.107-0.0078125-1.4062 1.4062 4.2998 4.2998h-11.397v2h11.379l-4.3008 4.3398 1.4062 1.4062 6.7441-6.7441-6.7266-6.7266z"></path></svg>
                    </a>
                </div>
            @endif
        </nav>
    @endif
@endsection
