{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $hasFilters = ($searchQuery ?? '') !== '' || ((int) ($selectedCategory ?? 0)) > 0;

        $fileIconLabel = function (?string $path): string {
            $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                return __('govuk_alpha_resources.file_types.pdf');
            }
            if (in_array($ext, ['doc', 'docx', 'rtf', 'odt'], true)) {
                return __('govuk_alpha_resources.file_types.doc');
            }
            if (in_array($ext, ['xls', 'xlsx', 'csv', 'ods'], true)) {
                return __('govuk_alpha_resources.file_types.spreadsheet');
            }
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)) {
                return __('govuk_alpha_resources.file_types.image');
            }
            if ($ext === 'txt') {
                return __('govuk_alpha_resources.file_types.text');
            }
            return __('govuk_alpha_resources.file_types.file');
        };

        $fileExt = fn (?string $path): string => strtoupper(pathinfo((string) $path, PATHINFO_EXTENSION)) ?: __('govuk_alpha_resources.file_types.file');

        $formatSize = function (int $bytes): string {
            if ($bytes <= 0) {
                return '';
            }
            if ($bytes < 1024) {
                return $bytes . ' B';
            }
            if ($bytes < 1024 * 1024) {
                return number_format($bytes / 1024, 1) . ' KB';
            }
            return number_format($bytes / (1024 * 1024), 1) . ' MB';
        };

        $categoryTagClass = function (?string $color): string {
            return match ($color) {
                'blue' => 'govuk-tag--blue',
                'green' => 'govuk-tag--green',
                'red' => 'govuk-tag--red',
                'purple' => 'govuk-tag--purple',
                'yellow' => 'govuk-tag--yellow',
                'fuchsia' => 'govuk-tag--pink',
                default => 'govuk-tag--grey',
            };
        };

        $resourceCount = count($resources ?? []);
    @endphp

    @if (($status ?? null) === 'resource-uploaded' || ($status ?? null) === 'resource-deleted')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="resources-status-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="resources-status-title">{{ __('govuk_alpha_resources.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">
                    {{ ($status === 'resource-uploaded') ? __('govuk_alpha_resources.states.uploaded') : __('govuk_alpha_resources.states.deleted') }}
                </p>
            </div>
        </div>
    @elseif (in_array($status ?? null, ['resource-delete-failed', 'resource-reorder-failed'], true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_resources.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p>{{ ($status === 'resource-delete-failed') ? __('govuk_alpha_resources.states.delete_failed') : __('govuk_alpha_resources.states.reorder_failed') }}</p>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ __('govuk_alpha_resources.library.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_resources.library.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_resources.library.description') }}</p>

    <div class="govuk-button-group govuk-!-margin-bottom-6">
        <a class="govuk-button" href="{{ route('govuk-alpha.resources.upload.form', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha_resources.actions.upload') }}</a>
        @if ($isAdmin)
            @if ($reorderMode)
                <a class="govuk-link" href="{{ route('govuk-alpha.resources.library', array_filter(['tenantSlug' => $tenantSlug, 'q' => $searchQuery ?: null, 'category_id' => $selectedCategory ?: null])) }}">{{ __('govuk_alpha_resources.actions.reorder_off') }}</a>
            @else
                <a class="govuk-link" href="{{ route('govuk-alpha.resources.library', array_filter(['tenantSlug' => $tenantSlug, 'q' => $searchQuery ?: null, 'category_id' => $selectedCategory ?: null, 'reorder' => '1'])) }}">{{ __('govuk_alpha_resources.actions.reorder_on') }}</a>
            @endif
        @endif
        <a class="govuk-link" href="{{ route('govuk-alpha.resources.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_resources.library.simple_view_link') }}</a>
    </div>

    <div class="govuk-grid-row">
        {{-- Category sidebar --}}
        <div class="govuk-grid-column-one-third">
            <nav class="nexus-alpha-card" aria-label="{{ __('govuk_alpha_resources.categories.heading') }}">
                <h2 class="govuk-heading-s">{{ __('govuk_alpha_resources.categories.heading') }}</h2>
                @if (empty($categoryTree) && empty($flatCategories))
                    <p class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha_resources.categories.empty') }}</p>
                @else
                    <ul class="govuk-list govuk-!-margin-bottom-0">
                        <li>
                            <a class="govuk-link {{ ((int) ($selectedCategory ?? 0)) === 0 ? 'govuk-!-font-weight-bold' : '' }}"
                               @if (((int) ($selectedCategory ?? 0)) === 0) aria-current="true" @endif
                               href="{{ route('govuk-alpha.resources.library', array_filter(['tenantSlug' => $tenantSlug, 'q' => $searchQuery ?: null, 'reorder' => $reorderMode ? '1' : null])) }}">{{ __('govuk_alpha_resources.categories.all') }}</a>
                        </li>
                        @if (!empty($categoryTree))
                            @include('accessible-frontend::partials.resources-category-tree', ['nodes' => $categoryTree, 'depth' => 0])
                        @else
                            @foreach ($flatCategories as $cat)
                                <li>
                                    <a class="govuk-link {{ ((int) ($selectedCategory ?? 0)) === (int) $cat['id'] ? 'govuk-!-font-weight-bold' : '' }}"
                                       @if (((int) ($selectedCategory ?? 0)) === (int) $cat['id']) aria-current="true" @endif
                                       href="{{ route('govuk-alpha.resources.library', array_filter(['tenantSlug' => $tenantSlug, 'q' => $searchQuery ?: null, 'category_id' => $cat['id'], 'reorder' => $reorderMode ? '1' : null])) }}">{{ $cat['name'] }}@if (($cat['resource_count'] ?? 0) > 0) <span class="nexus-alpha-meta">({{ $cat['resource_count'] }})</span>@endif</a>
                                </li>
                            @endforeach
                        @endif
                    </ul>
                @endif
            </nav>
        </div>

        {{-- Main content --}}
        <div class="govuk-grid-column-two-thirds">
            <form method="get" action="{{ route('govuk-alpha.resources.library', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
                @if (((int) ($selectedCategory ?? 0)) > 0)
                    <input type="hidden" name="category_id" value="{{ (int) $selectedCategory }}">
                @endif
                <div class="govuk-form-group">
                    <label class="govuk-label" for="q">{{ __('govuk_alpha_resources.search.label') }}</label>
                    <div id="q-hint" class="govuk-hint">{{ __('govuk_alpha_resources.search.hint') }}</div>
                    <input class="govuk-input govuk-!-width-two-thirds" id="q" name="q" type="search" value="{{ $searchQuery ?? '' }}" aria-describedby="q-hint">
                </div>
                <div class="govuk-button-group">
                    <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha_resources.search.submit') }}</button>
                    @if ($hasFilters)
                        <a class="govuk-link" href="{{ route('govuk-alpha.resources.library', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_resources.search.clear') }}</a>
                    @endif
                </div>
            </form>

            <p class="govuk-body nexus-alpha-result-count" aria-live="polite">
                {{ trans_choice('govuk_alpha_resources.library.count', $resourceCount, ['count' => $resourceCount]) }}
            </p>

            @if ($error)
                <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_resources.states.error_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <p>{{ __('govuk_alpha_resources.states.load_error') }}</p>
                            <p><a class="govuk-link" href="{{ url()->full() }}">{{ __('govuk_alpha_resources.states.try_again') }}</a></p>
                        </div>
                    </div>
                </div>
            @elseif (empty($resources))
                <div class="govuk-inset-text">
                    <h2 class="govuk-heading-m">{{ __('govuk_alpha_resources.empty.title') }}</h2>
                    <p class="govuk-body">{{ $hasFilters ? __('govuk_alpha_resources.empty.no_match') : __('govuk_alpha_resources.empty.no_resources') }}</p>
                    <h3 class="govuk-heading-s">{{ __('govuk_alpha_resources.empty.tips_title') }}</h3>
                    <ul class="govuk-list govuk-list--bullet">
                        <li>{{ __('govuk_alpha_resources.empty.tip_guides') }}</li>
                        <li>{{ __('govuk_alpha_resources.empty.tip_templates') }}</li>
                        <li>{{ __('govuk_alpha_resources.empty.tip_files') }}</li>
                    </ul>
                    @if ($hasFilters)
                        <p class="govuk-body"><a class="govuk-link" href="{{ route('govuk-alpha.resources.library', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_resources.search.clear') }}</a></p>
                    @endif
                </div>
            @else
                <div class="nexus-alpha-card-list">
                    @foreach ($resources as $i => $r)
                        @php
                            $rTitle = trim((string) ($r['title'] ?? '')) ?: __('govuk_alpha_resources.file_types.file');
                            $rPath = (string) ($r['file_path'] ?? '');
                            $sizeLabel = $formatSize((int) ($r['file_size'] ?? 0));
                            $canManage = $isAdmin || ((int) ($r['uploader_id'] ?? 0) === (int) ($currentUserId ?? 0));
                        @endphp
                        <article class="nexus-alpha-card">
                            <div class="nexus-alpha-module-row">
                                <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $rTitle }}</h3>
                                <strong class="govuk-tag govuk-tag--grey" title="{{ $fileIconLabel($rPath) }}">{{ $fileExt($rPath) }}</strong>
                            </div>

                            @if (!empty($r['category_name']))
                                <p class="govuk-!-margin-bottom-2"><strong class="govuk-tag {{ $categoryTagClass($r['category_color'] ?? 'grey') }}">{{ $r['category_name'] }}</strong></p>
                            @endif

                            @if (trim((string) ($r['description'] ?? '')) !== '')
                                <p class="govuk-body govuk-!-margin-bottom-2">{{ \Illuminate\Support\Str::limit((string) $r['description'], 220) }}</p>
                            @endif

                            <dl class="nexus-alpha-inline-list govuk-!-margin-bottom-2">
                                @if (!empty($r['uploader_name']))
                                    <div>
                                        <dt>{{ __('govuk_alpha_resources.card.uploaded_by', ['name' => '']) }}</dt>
                                        <dd>{{ $r['uploader_name'] }}</dd>
                                    </div>
                                @endif
                                @if ($sizeLabel !== '')
                                    <div>
                                        <dt>{{ __('govuk_alpha_resources.card.file_size') }}</dt>
                                        <dd>{{ $sizeLabel }}</dd>
                                    </div>
                                @endif
                                @if (!empty($r['created_at']))
                                    <div>
                                        <dt>{{ __('govuk_alpha_resources.card.uploaded_on') }}</dt>
                                        <dd>{{ \Illuminate\Support\Carbon::parse($r['created_at'])->diffForHumans() }}</dd>
                                    </div>
                                @endif
                                <div>
                                    <dt>{{ __('govuk_alpha_resources.card.downloads_label') }}</dt>
                                    <dd>{{ (int) ($r['downloads'] ?? 0) }}</dd>
                                </div>
                            </dl>

                            <div class="nexus-alpha-actions">
                                <a class="govuk-link govuk-link--no-visited-state" href="{{ route('govuk-alpha.resources.download', ['tenantSlug' => $tenantSlug, 'id' => $r['id']]) }}"
                                   aria-label="{{ __('govuk_alpha_resources.actions.download_aria', ['title' => $rTitle]) }}">{{ __('govuk_alpha_resources.actions.download') }}</a>

                                @if ($canManage)
                                    <a class="govuk-link" href="{{ route('govuk-alpha.resources.delete.confirm', ['tenantSlug' => $tenantSlug, 'id' => $r['id']]) }}"
                                       aria-label="{{ __('govuk_alpha_resources.actions.delete_aria', ['title' => $rTitle]) }}">{{ __('govuk_alpha_resources.actions.delete') }}</a>
                                @endif
                            </div>

                            {{-- Social panel: like toggle + comment count --}}
                            @php
                                $rLikeCount = (int) (($reactionCountsByResource ?? [])[$r['id']] ?? 0);
                                $rCommentCount = (int) (($commentCountsByResource ?? [])[$r['id']] ?? 0);
                            @endphp
                            <div class="nexus-alpha-actions govuk-!-margin-top-2" aria-label="{{ __('govuk_alpha_resources.social.panel_label') }}">
                                <form method="post" action="{{ route('govuk-alpha.resources.react', ['tenantSlug' => $tenantSlug, 'id' => $r['id']]) }}" class="nexus-alpha-reaction-form govuk-!-display-inline">
                                    @csrf
                                    <input type="hidden" name="emoji" value="like">
                                    <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button"
                                            aria-label="{{ __('govuk_alpha_resources.social.like_aria', ['title' => $rTitle]) }}">
                                        <span aria-hidden="true">&#128077;</span>
                                        {{ __('govuk_alpha_resources.social.like') }}
                                        @if ($rLikeCount > 0)
                                            ({{ $rLikeCount }})
                                        @endif
                                    </button>
                                </form>
                                <a class="govuk-link" href="{{ route('govuk-alpha.resources.comments', ['tenantSlug' => $tenantSlug, 'id' => $r['id']]) }}"
                                   aria-label="{{ __('govuk_alpha_resources.social.comments_link_aria', ['title' => $rTitle]) }}">
                                    &#128172;
                                    {{ trans_choice('govuk_alpha_resources.social.comment_count', $rCommentCount, ['count' => $rCommentCount]) }}
                                </a>
                            </div>

                            @if ($reorderMode && $isAdmin)
                                <div class="nexus-alpha-actions govuk-!-margin-top-2">
                                    @if ($i > 0)
                                        <form method="post" action="{{ route('govuk-alpha.resources.reorder', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-display-inline">
                                            @csrf
                                            <input type="hidden" name="resource_id" value="{{ (int) $r['id'] }}">
                                            <input type="hidden" name="direction" value="up">
                                            <input type="hidden" name="q" value="{{ $searchQuery ?? '' }}">
                                            <input type="hidden" name="category_id" value="{{ (int) ($selectedCategory ?? 0) }}">
                                            <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button" aria-label="{{ __('govuk_alpha_resources.actions.move_up_aria', ['title' => $rTitle]) }}">{{ __('govuk_alpha_resources.actions.move_up') }}</button>
                                        </form>
                                    @endif
                                    @if ($i < count($resources) - 1)
                                        <form method="post" action="{{ route('govuk-alpha.resources.reorder', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-display-inline">
                                            @csrf
                                            <input type="hidden" name="resource_id" value="{{ (int) $r['id'] }}">
                                            <input type="hidden" name="direction" value="down">
                                            <input type="hidden" name="q" value="{{ $searchQuery ?? '' }}">
                                            <input type="hidden" name="category_id" value="{{ (int) ($selectedCategory ?? 0) }}">
                                            <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button" aria-label="{{ __('govuk_alpha_resources.actions.move_down_aria', ['title' => $rTitle]) }}">{{ __('govuk_alpha_resources.actions.move_down') }}</button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>

                @if (!empty($hasMore) && !empty($nextCursor))
                    <nav class="govuk-pagination govuk-pagination--block govuk-!-margin-top-6" aria-label="{{ __('govuk_alpha_resources.actions.load_more') }}">
                        <div class="govuk-pagination__next">
                            <a class="govuk-link govuk-pagination__link" rel="next" href="{{ route('govuk-alpha.resources.library', array_filter(['tenantSlug' => $tenantSlug, 'q' => $searchQuery ?: null, 'category_id' => $selectedCategory ?: null, 'reorder' => $reorderMode ? '1' : null, 'cursor' => $nextCursor])) }}">
                                <span class="govuk-pagination__link-title">{{ __('govuk_alpha_resources.actions.load_more') }}</span>
                            </a>
                        </div>
                    </nav>
                @endif
            @endif
        </div>
    </div>
@endsection
