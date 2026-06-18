{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $gId = (int) ($group['id'] ?? 0);
        $gName = trim((string) ($group['name'] ?? '')) ?: __('govuk_alpha_groups.image.title');
        $avatarUrl = trim((string) ($group['image_url'] ?? ''));
        $coverUrl = trim((string) ($group['cover_image_url'] ?? ''));
        $successStates = ['avatar-updated', 'cover-updated'];
        $errorStates = ['image-missing', 'image-failed'];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.groups.show', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}">{{ __('govuk_alpha_groups.common.back_to_group') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_groups.image.caption', ['group' => $gName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_groups.image.title') }}</h1>

    @if (in_array($status, $successStates, true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="image-status-banner">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="image-status-banner">{{ __('govuk_alpha_groups.common.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_groups.states.' . $status) }}</p></div>
        </div>
    @elseif (in_array($status, $errorStates, true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_groups.common.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p class="govuk-body">{{ __('govuk_alpha_groups.states.' . $status) }}</p></div>
            </div>
        </div>
    @endif

    <p class="govuk-body-l">{{ __('govuk_alpha_groups.image.intro') }}</p>

    {{-- Avatar --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha_groups.image.avatar_heading') }}</h2>
    <p class="govuk-body">{{ __('govuk_alpha_groups.image.avatar_description') }}</p>
    @if ($avatarUrl !== '')
        <p class="govuk-body">
            <img class="nexus-alpha-avatar" src="{{ $avatarUrl }}" alt="{{ __('govuk_alpha_groups.image.avatar_current_alt') }}" width="96" height="96" loading="lazy" decoding="async">
        </p>
    @else
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_groups.image.avatar_none') }}</p></div>
    @endif

    <form method="post" action="{{ route('govuk-alpha.groups.image.update', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}" enctype="multipart/form-data" class="govuk-!-margin-bottom-8" novalidate>
        @csrf
        <input type="hidden" name="type" value="avatar">
        <div class="govuk-form-group">
            <label class="govuk-label" for="avatar-image">{{ __('govuk_alpha_groups.image.avatar_label') }}</label>
            <div id="avatar-image-hint" class="govuk-hint">{{ __('govuk_alpha_groups.image.avatar_hint') }}</div>
            <input class="govuk-file-upload" id="avatar-image" name="image" type="file" accept="image/jpeg,image/png,image/gif,image/webp" aria-describedby="avatar-image-hint" required>
        </div>
        <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_groups.image.avatar_submit') }}</button>
    </form>

    {{-- Cover --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha_groups.image.cover_heading') }}</h2>
    <p class="govuk-body">{{ __('govuk_alpha_groups.image.cover_description') }}</p>
    @if ($coverUrl !== '')
        <p class="govuk-body">
            <img class="nexus-alpha-card-thumb" src="{{ $coverUrl }}" alt="{{ __('govuk_alpha_groups.image.cover_current_alt') }}" width="320" height="180" loading="lazy" decoding="async">
        </p>
    @else
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_groups.image.cover_none') }}</p></div>
    @endif

    <form method="post" action="{{ route('govuk-alpha.groups.image.update', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}" enctype="multipart/form-data" novalidate>
        @csrf
        <input type="hidden" name="type" value="cover">
        <div class="govuk-form-group">
            <label class="govuk-label" for="cover-image">{{ __('govuk_alpha_groups.image.cover_label') }}</label>
            <div id="cover-image-hint" class="govuk-hint">{{ __('govuk_alpha_groups.image.cover_hint') }}</div>
            <input class="govuk-file-upload" id="cover-image" name="image" type="file" accept="image/jpeg,image/png,image/gif,image/webp" aria-describedby="cover-image-hint" required>
        </div>
        <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_groups.image.cover_submit') }}</button>
    </form>
@endsection
