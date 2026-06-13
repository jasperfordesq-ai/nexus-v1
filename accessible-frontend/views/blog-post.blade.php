{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $post = $post ?? [];
        $author = $post['author'] ?? null;
        $published = $post['published_at'] ?? null;
        $blogHref = route('govuk-alpha.blog.index', ['tenantSlug' => $tenantSlug]);
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <a class="govuk-back-link" href="{{ $blogHref }}">{{ __('govuk_alpha.blog.back_to_blog') }}</a>

            <span class="govuk-caption-l">{{ __('govuk_alpha.blog.caption', ['community' => $communityName]) }}</span>
            <h1 class="govuk-heading-xl">{{ $post['title'] ?? '' }}</h1>

            <p class="govuk-body-s nexus-alpha-meta">
                @if (! empty($post['category']['name'])){{ $post['category']['name'] }} · @endif
                @if (! empty($author['name'])){{ __('govuk_alpha.blog.author_label') }} {{ $author['name'] }} · @endif
                @if ($published){{ __('govuk_alpha.blog.published_label') }}: {{ \Illuminate\Support\Str::of((string) $published)->before('T') }}@endif
                @if (! empty($post['reading_time'])) · {{ __('govuk_alpha.blog.read_time', ['count' => (int) $post['reading_time']]) }}@endif
            </p>

            @if (! empty($post['featured_image']))
                <div class="nexus-alpha-detail-hero">
                    <img src="{{ $post['featured_image'] }}" alt="{{ __('govuk_alpha.blog.image_alt', ['title' => $post['title'] ?? '']) }}">
                </div>
            @endif

            {{-- Content is sanitized on save by the blog service. --}}
            <div class="legal-content govuk-body">
                {!! $post['content'] ?? '' !!}
            </div>
        </div>
    </div>
@endsection
