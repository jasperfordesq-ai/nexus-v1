{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $statusTag = function (string $s): array {
            return match ($s) {
                'open' => ['govuk-tag--green', __('govuk_alpha.ideation.status_open')],
                'voting' => ['govuk-tag--blue', __('govuk_alpha.ideation.status_voting')],
                'evaluating' => ['govuk-tag--purple', __('govuk_alpha.ideation.status_evaluating')],
                'closed', 'archived' => ['govuk-tag--grey', __('govuk_alpha.ideation.status_closed')],
                default => ['govuk-tag--grey', __('govuk_alpha.ideation.status_draft')],
            };
        };
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha.ideation.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.ideation.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.ideation.description') }}</p>

    @if (empty($challenges))
        <p class="govuk-inset-text">{{ __('govuk_alpha.ideation.empty') }}</p>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($challenges as $c)
                @php
                    $cTitle = trim((string) ($c['title'] ?? '')) ?: __('govuk_alpha.ideation.title');
                    [$tagClass, $tagLabel] = $statusTag((string) ($c['status'] ?? 'draft'));
                    $ideaCount = (int) ($c['ideas_count'] ?? 0);
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1"><a class="govuk-link" href="{{ route('govuk-alpha.ideation.show', ['tenantSlug' => $tenantSlug, 'id' => $c['id']]) }}">{{ $cTitle }}</a></h2>
                        <strong class="govuk-tag {{ $tagClass }}">{{ $tagLabel }}</strong>
                    </div>
                    @if (trim((string) ($c['description'] ?? '')) !== '')
                        <p class="govuk-body govuk-!-margin-bottom-1">{{ \Illuminate\Support\Str::limit($c['description'], 160) }}</p>
                    @endif
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">{{ trans_choice('govuk_alpha.ideation.ideas_count', $ideaCount, ['count' => $ideaCount]) }}</p>
                </article>
            @endforeach
        </div>
    @endif
@endsection
