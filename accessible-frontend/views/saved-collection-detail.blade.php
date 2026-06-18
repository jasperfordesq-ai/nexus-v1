{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $collection = $collection ?? null;
        $items = $items ?? [];
        $meta = $meta ?? ['current_page' => 1, 'last_page' => 1, 'total' => 0];
        $currentPage = (int) ($currentPage ?? 1);
        $lastPage = (int) ($meta['last_page'] ?? 1);
        $isOwner = (bool) ($isOwner ?? false);
        $status = $status ?? null;
        $cName = $collection !== null ? trim((string) ($collection['name'] ?? '')) : '';
        $cDesc = $collection !== null ? trim((string) ($collection['description'] ?? '')) : '';
        $cColor = $collection !== null ? (string) ($collection['color'] ?? '#6366f1') : '#6366f1';
        $cColorSafe = preg_match('/^#[0-9a-fA-F]{6}$/', $cColor) ? $cColor : '#6366f1';
        $cCount = $collection !== null ? (int) ($collection['items_count'] ?? 0) : 0;
        $cPublic = $collection !== null ? (bool) ($collection['is_public'] ?? false) : false;
        $cId = $collection !== null ? (int) ($collection['id'] ?? 0) : 0;

        // Resolve a browseable URL for each saved item type (Route::has guarded,
        // mirroring the React ITEM_LINKS map). Returns '' when no route exists.
        $resolveItemUrl = function (string $type, int $id) use ($tenantSlug): string {
            return match ($type) {
                'listing' => \Illuminate\Support\Facades\Route::has('govuk-alpha.listings.show')
                    ? route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $id]) : '',
                'event' => \Illuminate\Support\Facades\Route::has('govuk-alpha.events.show')
                    ? route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $id]) : '',
                'job' => \Illuminate\Support\Facades\Route::has('govuk-alpha.jobs.show')
                    ? route('govuk-alpha.jobs.show', ['tenantSlug' => $tenantSlug, 'id' => $id]) : '',
                'group' => \Illuminate\Support\Facades\Route::has('govuk-alpha.groups.show')
                    ? route('govuk-alpha.groups.show', ['tenantSlug' => $tenantSlug, 'id' => $id]) : '',
                'post' => \Illuminate\Support\Facades\Route::has('govuk-alpha.feed')
                    ? route('govuk-alpha.feed', ['tenantSlug' => $tenantSlug]) : '',
                default => '',
            };
        };
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.saved.collections', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_saved.collections.title') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_saved.detail.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">
        <span class="nexus-alpha-avatar" style="background-color: {{ $cColorSafe }}; width: 1.25rem; height: 1.25rem; display: inline-block; border-radius: 50%; vertical-align: middle;" aria-hidden="true"></span>
        {{ $cName !== '' ? $cName : __('govuk_alpha_saved.detail.title') }}
    </h1>

    {{-- Status banners --}}
    @if (in_array($status, ['item-removed', 'collection-updated'], true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="saved-detail-status-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="saved-detail-status-title">{{ __('govuk_alpha_saved.errors.summary_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">
                    {{ $status === 'item-removed' ? __('govuk_alpha_saved.status.item_removed') : __('govuk_alpha_saved.status.collection_updated') }}
                </p>
            </div>
        </div>
    @elseif (in_array($status, ['item-remove-failed', 'collection-failed', 'collection-name-required'], true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_saved.errors.summary_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p class="govuk-body">
                        @switch($status)
                            @case('item-remove-failed'){{ __('govuk_alpha_saved.status.item_remove_failed') }}@break
                            @case('collection-name-required'){{ __('govuk_alpha_saved.status.collection_name_required') }}@break
                            @default{{ __('govuk_alpha_saved.status.collection_failed') }}
                        @endswitch
                    </p>
                </div>
            </div>
        </div>
    @endif

    <p class="govuk-body">
        {{ trans_choice('govuk_alpha_saved.detail.count', $cCount, ['count' => $cCount]) }}
        @if ($cPublic)
            <strong class="govuk-tag govuk-tag--blue govuk-!-margin-left-1">{{ __('govuk_alpha_saved.detail.public_tag') }}</strong>
        @else
            <strong class="govuk-tag govuk-tag--grey govuk-!-margin-left-1">{{ __('govuk_alpha_saved.detail.private_tag') }}</strong>
        @endif
    </p>
    @if ($cDesc !== '')
        <p class="govuk-body-l">{{ $cDesc }}</p>
    @endif

    {{-- Saved items list --}}
    @if (empty($items))
        <div class="govuk-inset-text">
            <h2 class="govuk-heading-m">{{ __('govuk_alpha_saved.detail.empty_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha_saved.detail.empty_body') }}</p>
        </div>
    @else
        <ul class="govuk-list nexus-alpha-card-list">
            @foreach ($items as $item)
                @php
                    $iId = (int) ($item['id'] ?? 0);
                    $iType = (string) ($item['item_type'] ?? '');
                    $iItemId = (int) ($item['item_id'] ?? 0);
                    $iNote = trim((string) ($item['note'] ?? ''));
                    $iSavedAt = trim((string) ($item['saved_at'] ?? ''));
                    $iTitle = trim((string) ($item['preview_title'] ?? ''));
                    $typeLabel = \Illuminate\Support\Facades\Lang::has('govuk_alpha_saved.types.' . $iType)
                        ? __('govuk_alpha_saved.types.' . $iType)
                        : \Illuminate\Support\Str::headline($iType);
                    $displayTitle = $iTitle !== '' ? $iTitle : ($typeLabel . ' #' . $iItemId);
                    $itemUrl = $iItemId > 0 ? $resolveItemUrl($iType, $iItemId) : '';
                    $savedOn = '';
                    if ($iSavedAt !== '') {
                        try {
                            $savedOn = \Illuminate\Support\Carbon::parse($iSavedAt)->translatedFormat('j F Y');
                        } catch (\Throwable $e) {
                            $savedOn = '';
                        }
                    }
                @endphp
                <li class="nexus-alpha-card">
                    <h2 class="govuk-heading-s govuk-!-margin-bottom-1">
                        @if ($itemUrl !== '')
                            <a class="govuk-link" href="{{ $itemUrl }}">{{ $displayTitle }}</a>
                        @else
                            {{ $displayTitle }}
                        @endif
                    </h2>
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">
                        <strong class="govuk-tag govuk-tag--grey">{{ $typeLabel }}</strong>
                        @if ($savedOn !== '')
                            <span class="govuk-!-margin-left-1">{{ __('govuk_alpha_saved.detail.saved_on', ['date' => $savedOn]) }}</span>
                        @endif
                    </p>
                    @if ($iNote !== '')
                        <p class="govuk-body">{{ $iNote }}</p>
                    @endif
                    @if ($isOwner && $iId > 0)
                        <form method="post" action="{{ route('govuk-alpha.saved.collections.item-remove', ['tenantSlug' => $tenantSlug, 'id' => $cId, 'itemId' => $iId]) }}" class="govuk-!-margin-bottom-0">
                            @csrf
                            <button type="submit" class="govuk-button govuk-button--warning govuk-!-margin-bottom-0 govuk-!-font-size-16" data-module="govuk-button"
                                aria-label="{{ __('govuk_alpha_saved.detail.remove_item_label', ['title' => $displayTitle]) }}">{{ __('govuk_alpha_saved.detail.remove_item') }}</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    {{-- Pagination --}}
    @if ($lastPage > 1)
        <nav class="govuk-pagination" role="navigation" aria-label="{{ __('govuk_alpha_saved.pagination.page_of', ['current' => $currentPage, 'last' => $lastPage]) }}">
            @if ($currentPage > 1)
                <div class="govuk-pagination__prev">
                    <a class="govuk-link govuk-pagination__link" href="{{ route('govuk-alpha.saved.collection-detail', ['tenantSlug' => $tenantSlug, 'id' => $cId, 'page' => $currentPage - 1]) }}" rel="prev">
                        <svg class="govuk-pagination__icon govuk-pagination__icon--prev" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13"><path d="m6.5938-0.0078125-6.7266 6.7266 6.7441 6.7441 1.4062-1.4062-4.3008-4.3398h11.379v-2h-11.397l4.2998-4.2998-1.4063-1.4063z"></path></svg>
                        <span class="govuk-pagination__link-title">{{ __('govuk_alpha_saved.pagination.previous') }}</span>
                    </a>
                </div>
            @endif
            @if ($currentPage < $lastPage)
                <div class="govuk-pagination__next">
                    <a class="govuk-link govuk-pagination__link" href="{{ route('govuk-alpha.saved.collection-detail', ['tenantSlug' => $tenantSlug, 'id' => $cId, 'page' => $currentPage + 1]) }}" rel="next">
                        <span class="govuk-pagination__link-title">{{ __('govuk_alpha_saved.pagination.next') }}</span>
                        <svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13"><path d="m8.107-0.0078125-1.4062 1.4062 4.2998 4.2998h-11.397v2h11.379l-4.3008 4.3398 1.4062 1.4062 6.7441-6.7441-6.7266-6.7266z"></path></svg>
                    </a>
                </div>
            @endif
        </nav>
    @endif

    {{-- Owner-only: edit / delete --}}
    @if ($isOwner && $cId > 0)
        <hr class="govuk-section-break govuk-section-break--visible govuk-section-break--l">
        <details class="govuk-details" data-module="govuk-details">
            <summary class="govuk-details__summary">
                <span class="govuk-details__summary-text">{{ __('govuk_alpha_saved.edit.heading') }}</span>
            </summary>
            <div class="govuk-details__text">
                <form method="post" action="{{ route('govuk-alpha.saved.collections.update', ['tenantSlug' => $tenantSlug, 'id' => $cId]) }}">
                    @csrf
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="edit-collection-name">{{ __('govuk_alpha_saved.edit.name_label') }}</label>
                        <input class="govuk-input govuk-!-width-two-thirds" id="edit-collection-name" name="name" type="text" maxlength="255" value="{{ $cName }}">
                    </div>
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="edit-collection-description">{{ __('govuk_alpha_saved.edit.description_label') }}</label>
                        <textarea class="govuk-textarea govuk-!-width-two-thirds" id="edit-collection-description" name="description" rows="3">{{ $cDesc }}</textarea>
                    </div>
                    <div class="govuk-form-group">
                        <fieldset class="govuk-fieldset">
                            <legend class="govuk-fieldset__legend govuk-visually-hidden">{{ __('govuk_alpha_saved.edit.public_label') }}</legend>
                            <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                                <div class="govuk-checkboxes__item">
                                    <input class="govuk-checkboxes__input" id="edit-collection-public" name="is_public" type="checkbox" value="1"{{ $cPublic ? ' checked' : '' }}>
                                    <label class="govuk-label govuk-checkboxes__label" for="edit-collection-public">{{ __('govuk_alpha_saved.edit.public_label') }}</label>
                                </div>
                            </div>
                        </fieldset>
                    </div>
                    <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha_saved.edit.submit') }}</button>
                </form>

                <div class="govuk-warning-text">
                    <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                    <strong class="govuk-warning-text__text">
                        <span class="govuk-visually-hidden">{{ __('govuk_alpha_saved.errors.summary_title') }}</span>
                        {{ __('govuk_alpha_saved.edit.delete_warning') }}
                    </strong>
                </div>
                <form method="post" action="{{ route('govuk-alpha.saved.collections.delete', ['tenantSlug' => $tenantSlug, 'id' => $cId]) }}">
                    @csrf
                    <button type="submit" class="govuk-button govuk-button--warning" data-module="govuk-button"
                        aria-label="{{ __('govuk_alpha_saved.edit.delete_confirm_label', ['name' => $cName]) }}">{{ __('govuk_alpha_saved.edit.delete_submit') }}</button>
                </form>
            </div>
        </details>
    @endif
@endsection
