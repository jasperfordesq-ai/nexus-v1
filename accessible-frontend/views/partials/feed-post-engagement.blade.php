{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
{{--
    Share + save controls for a feed post (posts only — typed cards are not
    shareable/bookmarkable here). Plain submit-button toggles, no JS.

    Required vars:
      $engagementPostId   — feed post id
      $engagementTitle    — post title/label for the visually-hidden suffix
      $engagementTenant   — tenant slug
    Optional vars:
      $engagementShared   — bool: viewer already shared this post
      $engagementSaved    — bool: viewer already saved this post
      $engagementShareCount — int
      $engagementOwn      — bool: viewer is the author (cannot self-share)
      $engagementPreserved — hidden inputs to preserve feed filter/cursor state
--}}
@php
    $engagementShared = $engagementShared ?? false;
    $engagementSaved = $engagementSaved ?? false;
    $engagementShareCount = (int) ($engagementShareCount ?? 0);
    $engagementOwn = $engagementOwn ?? false;
    $engagementPreserved = $engagementPreserved ?? [];
@endphp
<div class="nexus-alpha-actions govuk-button-group govuk-!-margin-bottom-3">
    @unless ($engagementOwn)
        <form method="post" action="{{ route('govuk-alpha.feed.posts.share', ['tenantSlug' => $engagementTenant, 'id' => $engagementPostId]) }}">
            @csrf
            @foreach ($engagementPreserved as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button" aria-pressed="{{ $engagementShared ? 'true' : 'false' }}">
                {{ $engagementShared ? __('govuk_alpha.feed_t1.unshare_button') : __('govuk_alpha.feed_t1.share_button') }}
                <span class="govuk-visually-hidden"> {{ $engagementTitle }}</span>
            </button>
        </form>
    @endunless
    <form method="post" action="{{ route('govuk-alpha.feed.posts.save', ['tenantSlug' => $engagementTenant, 'id' => $engagementPostId]) }}">
        @csrf
        @foreach ($engagementPreserved as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button" aria-pressed="{{ $engagementSaved ? 'true' : 'false' }}">
            {{ $engagementSaved ? __('govuk_alpha.feed_t1.unsave_button') : __('govuk_alpha.feed_t1.save_button') }}
            <span class="govuk-visually-hidden"> {{ $engagementTitle }}</span>
        </button>
    </form>
</div>
@if ($engagementShareCount > 0)
    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-3">
        {{ trans_choice('govuk_alpha.feed_t1.shares_count', $engagementShareCount, ['count' => $engagementShareCount]) }}
    </p>
@endif
