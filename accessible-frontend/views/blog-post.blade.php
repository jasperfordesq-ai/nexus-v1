{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@php
    $communityName = $tenant['name'] ?? $tenantSlug;
    $post = $post ?? [];
    $author = $post['author'] ?? null;
    $published = $post['published_at'] ?? null;
    $updated = $post['updated_at'] ?? null;
    $blogHref = route('govuk-alpha.blog.index', ['tenantSlug' => $tenantSlug]);
    $metaDesc = trim((string) ($post['meta_description'] ?? $post['excerpt'] ?? ''));
    $ogImage = trim((string) ($post['og_image_url'] ?? $post['featured_image'] ?? ''));
    $canonical = trim((string) ($post['canonical_url'] ?? ''));
    $fmtDate = fn ($v): ?string => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('j F Y') : null;
@endphp

@push('alpha_head')
    @if ($metaDesc !== '')<meta name="description" content="{{ \Illuminate\Support\Str::limit($metaDesc, 300) }}">@endif
    @if (!empty($post['noindex']))<meta name="robots" content="noindex">@endif
    @if ($canonical !== '')<link rel="canonical" href="{{ $canonical }}">@endif
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $post['meta_title'] ?? $post['title'] ?? '' }}">
    @if ($metaDesc !== '')<meta property="og:description" content="{{ \Illuminate\Support\Str::limit($metaDesc, 300) }}">@endif
    @if ($ogImage !== '')<meta property="og:image" content="{{ $ogImage }}">@endif
    @if ($published)<meta property="article:published_time" content="{{ \Illuminate\Support\Carbon::parse($published)->toIso8601String() }}">@endif
    @if ($updated)<meta property="article:modified_time" content="{{ \Illuminate\Support\Carbon::parse($updated)->toIso8601String() }}">@endif
    <script type="application/ld+json">{!! json_encode(array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => (string) ($post['title'] ?? ''),
        'description' => $metaDesc !== '' ? $metaDesc : null,
        'image' => $ogImage !== '' ? $ogImage : null,
        'datePublished' => $published ? \Illuminate\Support\Carbon::parse($published)->toIso8601String() : null,
        'dateModified' => $updated ? \Illuminate\Support\Carbon::parse($updated)->toIso8601String() : null,
        'author' => !empty($author['name']) ? ['@type' => 'Person', 'name' => $author['name']] : null,
        'publisher' => ['@type' => 'Organization', 'name' => $communityName],
    ]), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')
    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <a class="govuk-back-link" href="{{ $blogHref }}">{{ __('govuk_alpha.blog.back_to_blog') }}</a>

            <span class="govuk-caption-l">{{ __('govuk_alpha.blog.caption', ['community' => $communityName]) }}</span>
            <h1 class="govuk-heading-xl">{{ $post['title'] ?? '' }}</h1>

            <p class="govuk-body-s nexus-alpha-meta">
                @if (! empty($post['category']['name'])){{ $post['category']['name'] }} · @endif
                @if (! empty($author['name']))
                    {{ __('govuk_alpha.blog.author_label') }}
                    @if (!empty($author['id']) && (int) $author['id'] > 0)<a class="govuk-link" href="{{ route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $author['id']]) }}">{{ $author['name'] }}</a>@else{{ $author['name'] }}@endif ·
                @endif
                @if ($fmtDate($published)){{ __('govuk_alpha.blog.published_label') }}: {{ $fmtDate($published) }}@endif
                @if (! empty($post['reading_time'])) · {{ __('govuk_alpha.blog.read_time', ['count' => (int) $post['reading_time']]) }}@endif
            </p>

            @if (! empty($post['featured_image']))
                <div class="nexus-alpha-detail-hero">
                    <img src="{{ $post['featured_image'] }}" alt="{{ !empty($post['title']) ? __('govuk_alpha.blog.image_alt', ['title' => $post['title']]) : __('govuk_alpha.blog.featured_image_generic') }}">
                </div>
            @endif

            {{-- Content is run through HtmlSanitizer::sanitizeCms in BlogService::getBySlug before it reaches here. --}}
            <div class="legal-content govuk-body">
                {!! $post['content'] ?? '' !!}
            </div>

            {{-- Post-level like (parity with the React social panel). --}}
            <div id="reactions" class="govuk-!-margin-top-4 govuk-!-margin-bottom-2">
                @if (!empty($isAuthenticated) && !empty($post['slug']))
                    <form method="post" action="{{ route('govuk-alpha.blog.like', ['tenantSlug' => $tenantSlug, 'slug' => $post['slug']]) }}" class="govuk-!-display-inline-block">
                        @csrf
                        <button class="govuk-button {{ ($hasLiked ?? false) ? '' : 'govuk-button--secondary' }} govuk-!-margin-bottom-0" data-module="govuk-button">
                            {{ ($hasLiked ?? false) ? __('govuk_alpha.blog.unlike') : __('govuk_alpha.blog.like') }}
                        </button>
                    </form>
                @endif
                <span class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha.blog.likes_count', ['count' => (int) ($likeCount ?? 0)]) }}</span>
            </div>

            <hr class="govuk-section-break govuk-section-break--visible govuk-section-break--l">

            <section id="comments">
                <h2 class="govuk-heading-l">{{ __('govuk_alpha.blog.comments_heading') }} <span class="govuk-!-font-weight-regular">({{ (int) ($commentsCount ?? 0) }})</span></h2>

                @if (!empty($post['slug']))
                    <p class="govuk-body">
                        <a class="govuk-link" href="{{ route('govuk-alpha.blogreviews.blog.comments', ['tenantSlug' => $tenantSlug, 'slug' => $post['slug']]) }}">{{ __('govuk_alpha_blogreviews.nav.view_discussion') }}</a>
                    </p>
                @endif

                @if (($status ?? null) === 'comment-added')
                    <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="comment-status">
                        <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="comment-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
                        <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.blog.comment_states.comment-added') }}</p></div>
                    </div>
                @elseif (in_array($status ?? null, ['comment-invalid', 'comment-failed'], true))
                    <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                        <div role="alert"><h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                            <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li><a href="#body">{{ __('govuk_alpha.blog.comment_states.' . $status) }}</a></li></ul></div></div>
                    </div>
                @endif

                @if (empty($isAuthenticated))
                    <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.blog.comments_signin') }}</p></div>
                @else
                    @if (empty($comments))
                        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.blog.comments_empty') }}</p></div>
                    @else
                        <ul class="govuk-list">
                            @foreach ($comments as $comment)
                                @include('accessible-frontend::partials.blog-comment', ['comment' => $comment, 'depth' => 0])
                            @endforeach
                        </ul>
                    @endif

                    <form method="post" action="{{ route('govuk-alpha.blog.comments.store', ['tenantSlug' => $tenantSlug, 'slug' => $post['slug'] ?? '']) }}" class="govuk-!-margin-top-4">
                        @csrf
                        <div class="govuk-form-group {{ ($status ?? null) === 'comment-invalid' ? 'govuk-form-group--error' : '' }}">
                            <label class="govuk-label govuk-label--s" for="body">{{ __('govuk_alpha.blog.comment_body_label') }}</label>
                            @if (($status ?? null) === 'comment-invalid')
                                <p id="body-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ __('govuk_alpha.blog.comment_states.comment-invalid') }}</p>
                            @endif
                            <textarea class="govuk-textarea" id="body" name="body" rows="4" maxlength="5000" {{ ($status ?? null) === 'comment-invalid' ? 'aria-describedby="body-error"' : '' }}></textarea>
                        </div>
                        <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.blog.comment_submit') }}</button>
                    </form>
                @endif
            </section>
        </div>
    </div>
@endsection
