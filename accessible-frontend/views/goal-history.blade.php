{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.goals.show', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}">{{ __('govuk_alpha_goals.common.back_to_goal') }}</a>

    @php
        $gTitle = trim((string) ($goal['title'] ?? '')) ?: __('govuk_alpha_goals.history.title');

        /**
         * GOV.UK tag colour per event type.
         * Mirrors the colour logic in GoalProgressHistory.tsx.
         *
         * @var array<string,string>
         */
        $tagColour = [
            'created'         => 'govuk-tag--grey',
            'progress_update' => 'govuk-tag--blue',
            'checkin'         => 'govuk-tag--blue',
            'milestone'       => 'govuk-tag--purple',
            'buddy_joined'    => 'govuk-tag--turquoise',
            'buddy_action'    => 'govuk-tag--turquoise',
            'completed'       => 'govuk-tag--green',
        ];

        $fmtDate = function ($value): ?string {
            if (empty($value)) {
                return null;
            }
            try {
                return \Illuminate\Support\Carbon::parse($value)->isoFormat('D MMM YYYY, HH:mm');
            } catch (\Throwable $e) {
                return null;
            }
        };

        $fmtRelative = function ($value): ?string {
            if (empty($value)) {
                return null;
            }
            try {
                $dt = \Illuminate\Support\Carbon::parse($value);
                return $dt->diffForHumans();
            } catch (\Throwable $e) {
                return null;
            }
        };
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha_goals.history.caption') }}: {{ $gTitle }}</span>
    <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">{{ __('govuk_alpha_goals.history.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_goals.history.intro') }}</p>

    @if (empty($items))
        <div class="govuk-inset-text">
            <p class="govuk-body">{{ __('govuk_alpha_goals.history.empty') }}</p>
        </div>
    @else
        <ol class="govuk-list govuk-list--number nexus-alpha-card-list" aria-label="{{ __('govuk_alpha_goals.history.list_aria') }}">
            @foreach ($items as $event)
                @php
                    $type      = (string) ($event['type'] ?? $event['event_type'] ?? 'progress_update');
                    $typeKey   = array_key_exists($type, $tagColour) ? $type : 'progress_update';
                    $colour    = $tagColour[$typeKey];
                    $desc      = trim((string) ($event['description'] ?? ''));
                    $when      = $event['created_at'] ?? null;
                    $absolute  = $fmtDate($when);
                    $relative  = $fmtRelative($when);
                    $typeLabel = __('govuk_alpha_goals.history.type_' . $typeKey);
                @endphp
                <li class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row govuk-!-margin-bottom-2">
                        <strong class="govuk-tag {{ $colour }}">{{ $typeLabel }}</strong>
                        @if ($absolute)
                            <span class="govuk-body-s nexus-alpha-meta" aria-label="{{ $absolute }}">
                                <time datetime="{{ $when }}" title="{{ $absolute }}">{{ $relative ?? $absolute }}</time>
                            </span>
                        @endif
                    </div>
                    @if ($desc !== '')
                        <p class="govuk-body govuk-!-margin-bottom-0">{{ $desc }}</p>
                    @endif
                </li>
            @endforeach
        </ol>

        @if ($hasMore && $nextCursor)
            <p class="govuk-body govuk-!-margin-top-4">
                <a class="govuk-link" href="{{ route('govuk-alpha.goals.history', ['tenantSlug' => $tenantSlug, 'id' => $goal['id'], 'cursor' => $nextCursor]) }}">
                    {{ __('govuk_alpha_goals.history.load_more') }}
                </a>
            </p>
        @endif
    @endif

    <p class="govuk-body govuk-!-margin-top-6">
        <a class="govuk-link" href="{{ route('govuk-alpha.goals.show', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}">{{ __('govuk_alpha_goals.common.back_to_goal') }}</a>
    </p>
@endsection
