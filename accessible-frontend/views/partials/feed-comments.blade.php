{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@php
    $depth = (int) ($depth ?? 0);
@endphp

<ol class="govuk-list nexus-alpha-comments-list{{ $depth > 0 ? ' nexus-alpha-comments-list--nested' : '' }}">
    @foreach ($comments as $comment)
        @php
            $authorName = $comment['author']['name'] ?? __('govuk_alpha.feed.unknown_author');
            $createdAt = !empty($comment['created_at']) ? \Illuminate\Support\Carbon::parse($comment['created_at']) : null;
        @endphp
        <li class="nexus-alpha-comment">
            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">
                <strong>{{ $authorName }}</strong>
                @if ($createdAt)
                    <span aria-hidden="true"> | </span>
                    <span class="govuk-visually-hidden">{{ __('govuk_alpha.feed.comment_posted_on_prefix') }}</span>
                    <time datetime="{{ $createdAt->toIso8601String() }}">{{ $createdAt->translatedFormat('j F Y, H:i') }}</time>
                @endif
            </p>
            <p class="govuk-body govuk-!-margin-bottom-2">{{ $comment['content'] ?? '' }}</p>
            @if (!empty($comment['replies']))
                @include('accessible-frontend::partials.feed-comments', ['comments' => $comment['replies'], 'depth' => $depth + 1])
            @endif
        </li>
    @endforeach
</ol>
