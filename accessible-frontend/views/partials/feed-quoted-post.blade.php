{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
{{--
    Nested quoted-post preview card. FeedService attaches a `quoted_post` shape
    to post items that quote another post; this renders it as an inset card so
    the accessible frontend reaches parity with the React FeedCard quote embed.

    Required vars:
      $quoted        — the item['quoted_post'] array
      $tenantSlug    — current tenant slug (for the permalink)
--}}
@php
    $quotedId = (int) ($quoted['id'] ?? 0);
    $quotedAuthor = $quoted['author']['name'] ?? __('govuk_alpha_feed.item.unknown_author');
    $quotedAvatar = $quoted['author']['avatar_url'] ?? null;
    $quotedCreated = !empty($quoted['created_at']) ? \Illuminate\Support\Carbon::parse($quoted['created_at']) : null;
    $quotedContent = trim(html_entity_decode(strip_tags((string) ($quoted['content'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $quotedMedia = is_array($quoted['media'] ?? null) ? $quoted['media'] : [];
@endphp
@if ($quotedId > 0)
    <figure class="nexus-alpha-card nexus-alpha-card--quoted govuk-!-margin-bottom-3" aria-label="{{ __('govuk_alpha_feed.quoted.posted_by', ['name' => $quotedAuthor]) }}">
        <figcaption class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">
            @if (!empty($quotedAvatar))
                <img class="nexus-alpha-avatar nexus-alpha-avatar--small" src="{{ $quotedAvatar }}" alt="" loading="lazy" decoding="async" width="24" height="24">
            @endif
            <strong>{{ __('govuk_alpha_feed.quoted.heading') }}:</strong>
            {{ __('govuk_alpha_feed.item.posted_by', ['name' => $quotedAuthor]) }}
            @if ($quotedCreated)
                <span aria-hidden="true"> | </span>
                <time datetime="{{ $quotedCreated->toIso8601String() }}">{{ $quotedCreated->translatedFormat('j F Y, H:i') }}</time>
            @endif
        </figcaption>

        @if ($quotedContent !== '')
            <p class="govuk-body-s">{!! nl2br(e($quotedContent)) !!}</p>
            @if (!empty($quoted['content_truncated']))
                <p class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha_feed.quoted.truncated') }}</p>
            @endif
        @endif

        @if (!empty($quotedMedia))
            <ul class="nexus-alpha-feed-media" data-count="{{ min(count($quotedMedia), 4) }}">
                @foreach (array_slice($quotedMedia, 0, 4) as $qMedia)
                    @php
                        $qFull = $qMedia['file_url'] ?? null;
                        $qThumb = $qMedia['thumbnail_url'] ?? $qFull;
                        $qAlt = !empty($qMedia['alt_text']) ? $qMedia['alt_text'] : __('govuk_alpha_feed.quoted.image_alt');
                    @endphp
                    @if ($qFull)
                        <li>
                            <a href="{{ $qFull }}" target="_blank" rel="noopener noreferrer">
                                <img src="{{ $qThumb }}" alt="{{ $qAlt }}" class="nexus-alpha-feed-image" loading="lazy">
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        @endif

        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">
            <a class="govuk-link govuk-link--no-visited-state" href="{{ route('govuk-alpha.feed.posts.show', ['tenantSlug' => $tenantSlug, 'id' => $quotedId]) }}">
                {{ __('govuk_alpha_feed.item.open_full') }}
            </a>
        </p>
    </figure>
@endif
