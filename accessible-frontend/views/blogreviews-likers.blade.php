{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $post = $post ?? [];
        $slug = (string) ($post['slug'] ?? '');
        $likers = is_array($likers ?? null) ? $likers : [];
        $reactionEmoji = (string) ($reactionEmoji ?? '');
        $likersTotal = (int) ($likersTotal ?? 0);
        $likersHasMore = (bool) ($likersHasMore ?? false);
        $likersPage = (int) ($likersPage ?? 1);
        $commentsHref = route('govuk-alpha.blogreviews.blog.comments', ['tenantSlug' => $tenantSlug, 'slug' => $slug]);
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <a class="govuk-back-link" href="{{ $commentsHref }}">{{ __('govuk_alpha_blogreviews.likers.back_to_comments') }}</a>

            <span class="govuk-caption-l">{{ __('govuk_alpha_blogreviews.likers.caption') }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha_blogreviews.likers.heading', ['emoji' => $reactionEmoji]) }}</h1>

            <p class="govuk-body-l">{{ trans_choice('govuk_alpha_blogreviews.likers.count', $likersTotal, ['count' => $likersTotal]) }}</p>

            @if (empty($likers))
                <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_blogreviews.likers.empty') }}</p></div>
            @else
                <ul class="govuk-list nexus-alpha-card-list">
                    @foreach ($likers as $liker)
                        @php
                            $likerId = (int) ($liker['id'] ?? 0);
                            $likerName = trim((string) ($liker['name'] ?? '')) !== '' ? $liker['name'] : __('govuk_alpha_blogreviews.likers.unknown_member');
                        @endphp
                        <li class="nexus-alpha-module-row">
                            @if ($likerId > 0 && \Illuminate\Support\Facades\Route::has('govuk-alpha.members.show'))
                                <a class="govuk-link" href="{{ route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $likerId]) }}">{{ $likerName }}</a>
                            @else
                                {{ $likerName }}
                            @endif
                        </li>
                    @endforeach
                </ul>

                @if ($likersHasMore)
                    <a class="govuk-button govuk-button--secondary" data-module="govuk-button" role="button"
                       href="{{ route('govuk-alpha.blogreviews.blog.likers', ['tenantSlug' => $tenantSlug, 'slug' => $slug, 'reaction' => $reaction, 'page' => $likersPage + 1]) }}">
                        {{ __('govuk_alpha_blogreviews.likers.load_more') }}
                    </a>
                @endif
            @endif
        </div>
    </div>
@endsection
