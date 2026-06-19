{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $statusTag = function (string $s): array {
            return match ($s) {
                'open' => ['govuk-tag--green', __('govuk_alpha_ideation.status.open')],
                'voting' => ['govuk-tag--blue', __('govuk_alpha_ideation.status.voting')],
                'evaluating' => ['govuk-tag--purple', __('govuk_alpha_ideation.status.evaluating')],
                'closed', 'archived' => ['govuk-tag--grey', __('govuk_alpha_ideation.status.closed')],
                default => ['govuk-tag--grey', __('govuk_alpha_ideation.status.draft')],
            };
        };
        $hasSelected = is_string($selectedTag ?? null) && $selectedTag !== '';
    @endphp

    <a href="{{ route('govuk-alpha.ideation.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha_ideation.tags.back_to_challenges') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_ideation.tags.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">{{ __('govuk_alpha_ideation.tags.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_ideation.tags.intro') }}</p>

    @if (empty($tags))
        <div class="govuk-inset-text">{{ __('govuk_alpha_ideation.tags.empty') }}</div>
    @else
        <h2 class="govuk-heading-m">{{ __('govuk_alpha_ideation.tags.popular_heading') }}</h2>
        <ul class="govuk-list nexus-alpha-inline-list">
            @foreach ($tags as $tagRow)
                @php
                    $tagName = trim((string) ($tagRow['tag'] ?? ''));
                    $tagCount = (int) ($tagRow['count'] ?? 0);
                    $isActive = $hasSelected && mb_strtolower($tagName) === mb_strtolower((string) $selectedTag);
                @endphp
                @if ($tagName !== '')
                    <li>
                        <a class="govuk-link{{ $isActive ? ' govuk-!-font-weight-bold' : '' }}" href="{{ route('govuk-alpha.ideation.tags', ['tenantSlug' => $tenantSlug, 'tag' => $tagName]) }}">
                            {{ $tagName }} ({{ trans_choice('govuk_alpha_ideation.tags.tag_count', $tagCount, ['count' => $tagCount]) }})
                        </a>
                    </li>
                @endif
            @endforeach
        </ul>
    @endif

    @if ($hasSelected)
        <hr class="govuk-section-break govuk-section-break--visible govuk-section-break--l">
        <div class="nexus-alpha-module-row">
            <h2 class="govuk-heading-m govuk-!-margin-bottom-2">{{ __('govuk_alpha_ideation.tags.selected_heading', ['tag' => $selectedTag]) }}</h2>
            <a class="govuk-link" href="{{ route('govuk-alpha.ideation.tags', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_ideation.tags.clear') }}</a>
        </div>

        @if (empty($matches))
            <div class="govuk-inset-text">{{ __('govuk_alpha_ideation.tags.no_matches', ['tag' => $selectedTag]) }}</div>
        @else
            <div class="nexus-alpha-card-list">
                @foreach ($matches as $c)
                    @php
                        $cId = (int) ($c['id'] ?? 0);
                        $cTitle = trim((string) ($c['title'] ?? '')) ?: __('govuk_alpha_ideation.idea.title');
                        [$tagClass, $tagLabel] = $statusTag((string) ($c['status'] ?? 'draft'));
                    @endphp
                    <article class="nexus-alpha-card">
                        <div class="nexus-alpha-module-row">
                            <h3 class="govuk-heading-s govuk-!-margin-bottom-1"><a class="govuk-link" href="{{ route('govuk-alpha.ideation.show', ['tenantSlug' => $tenantSlug, 'id' => $cId]) }}">{{ $cTitle }}</a></h3>
                            <strong class="govuk-tag {{ $tagClass }}">{{ $tagLabel }}</strong>
                        </div>
                        @if (trim((string) ($c['description'] ?? '')) !== '')
                            <p class="govuk-body govuk-!-margin-bottom-1">{{ \Illuminate\Support\Str::limit($c['description'], 160) }}</p>
                        @endif
                        <p class="govuk-body-s nexus-alpha-actions govuk-!-margin-bottom-0">
                            <a class="govuk-link" href="{{ route('govuk-alpha.ideation.show', ['tenantSlug' => $tenantSlug, 'id' => $cId]) }}">{{ __('govuk_alpha_ideation.tags.view_challenge') }}</a>
                        </p>
                    </article>
                @endforeach
            </div>
        @endif
    @endif
@endsection
