{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@php
    $depth = $depth ?? 0;
    $cAuthor = trim((string) ($comment['author']['name'] ?? ''));
    $cDate = !empty($comment['created_at']) ? \Illuminate\Support\Carbon::parse($comment['created_at'])->translatedFormat('j F Y') : '';
    $cReplies = is_array($comment['replies'] ?? null) ? $comment['replies'] : [];
    $cEdited = (bool) ($comment['edited'] ?? false);
@endphp
<li class="nexus-alpha-comment">
    <p class="govuk-body govuk-!-margin-bottom-1">{{ $comment['content'] ?? '' }}</p>
    @php
        $cMetaParts = [];
        if ($cAuthor !== '') { $cMetaParts[] = $cAuthor; }
        if ($cDate !== '') { $cMetaParts[] = $cDate; }
        if ($cEdited) { $cMetaParts[] = __('govuk_alpha_listings.comments.edited'); }
        $cMeta = implode(' · ', $cMetaParts);
    @endphp
    @if ($cMeta !== '')
        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">{{ $cMeta }}</p>
    @endif
    @if (!empty($cReplies) && $depth < 4)
        <ul class="govuk-list nexus-alpha-comments-list--nested">
            @foreach ($cReplies as $reply)
                @include('accessible-frontend::partials.listings-comment', ['comment' => $reply, 'depth' => $depth + 1])
            @endforeach
        </ul>
    @endif
</li>
