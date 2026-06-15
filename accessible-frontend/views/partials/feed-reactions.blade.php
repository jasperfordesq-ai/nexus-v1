{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
{{--
    Accessible reaction row: one plain submit-button per reaction type (no JS
    picker). Re-used for posts and comments. Each button toggles a reaction
    through ReactionService. The currently selected reaction is announced and
    styled as pressed so it can be removed by submitting it again.

    Required vars:
      $reactionAction       — POST target URL (already route()-built)
      $alphaReactions       — array<string type, string emoji>
      $reactionLegend       — accessible legend text
      $reactionTargetLabel  — short label for the visually-hidden button suffix
    Optional vars:
      $reactionCounts       — array<string type, int count>
      $userReactionTypes    — array<int, string> reaction types the viewer chose
      $reactionPreserved    — hidden inputs to preserve feed filter/cursor state
--}}
@php
    $reactionCounts = $reactionCounts ?? [];
    $userReactionTypes = $userReactionTypes ?? [];
    $reactionPreserved = $reactionPreserved ?? [];
    $reactionLabelKey = [
        'like' => 'govuk_alpha.feed_t1.reaction_like',
        'love' => 'govuk_alpha.feed_t1.reaction_love',
        'celebrate' => 'govuk_alpha.feed_t1.reaction_celebrate',
    ];
    $reactionTotal = 0;
    foreach ($reactionCounts as $reactionTypeCount) {
        $reactionTotal += (int) $reactionTypeCount;
    }
@endphp
<div class="nexus-alpha-reactions govuk-!-margin-bottom-3">
    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1" aria-live="polite">
        {{ trans_choice('govuk_alpha.feed_t1.reaction_count', $reactionTotal, ['count' => $reactionTotal]) }}
    </p>
    <fieldset class="govuk-fieldset">
        <legend class="govuk-fieldset__legend govuk-fieldset__legend--s govuk-visually-hidden">{{ $reactionLegend }}</legend>
        <div class="nexus-alpha-actions govuk-button-group govuk-!-margin-bottom-0">
            @foreach ($alphaReactions as $reactionType => $reactionEmoji)
                @php
                    $isSelected = in_array($reactionType, $userReactionTypes, true);
                    $typeCount = (int) ($reactionCounts[$reactionType] ?? 0);
                    $reactionLabel = \Illuminate\Support\Facades\Lang::has($reactionLabelKey[$reactionType] ?? '')
                        ? __($reactionLabelKey[$reactionType])
                        : $reactionType;
                @endphp
                <form method="post" action="{{ $reactionAction }}" class="nexus-alpha-reaction-form">
                    @csrf
                    @foreach ($reactionPreserved as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                    <input type="hidden" name="emoji" value="{{ $reactionType }}">
                    <button
                        class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0{{ $isSelected ? ' nexus-alpha-reaction--active' : '' }}"
                        data-module="govuk-button"
                        aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                    >
                        <span aria-hidden="true">{{ $reactionEmoji }}</span>
                        {{ $reactionLabel }}@if ($typeCount > 0) ({{ $typeCount }})@endif
                        <span class="govuk-visually-hidden"> {{ $reactionTargetLabel }}</span>
                    </button>
                </form>
            @endforeach
        </div>
    </fieldset>
</div>
