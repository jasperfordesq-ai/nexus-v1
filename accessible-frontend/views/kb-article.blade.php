{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $article = $article ?? [];
        $author = $article['author'] ?? null;
        $updated = $article['updated_at'] ?? ($article['created_at'] ?? null);
        $children = $article['children'] ?? [];
        $kbHref = route('govuk-alpha.kb.index', ['tenantSlug' => $tenantSlug]);
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <a class="govuk-back-link" href="{{ $kbHref }}">{{ __('govuk_alpha.kb.back_to_kb') }}</a>

            <span class="govuk-caption-l">{{ __('govuk_alpha.kb.caption', ['community' => $communityName]) }}</span>
            <h1 class="govuk-heading-xl">{{ $article['title'] ?? '' }}</h1>

            <p class="govuk-body-s nexus-alpha-meta">
                @if (! empty($author['name'])){{ __('govuk_alpha.kb.author_label') }} {{ $author['name'] }}@endif
                @if (! empty($author['name']) && $updated) · @endif
                @if ($updated){{ __('govuk_alpha.kb.updated_label') }}: {{ \Illuminate\Support\Str::of((string) $updated)->before('T') }}@endif
            </p>

            {{-- Re-sanitize at the render boundary for imported/legacy/manual rows. --}}
            <div class="legal-content govuk-body">
                {!! \App\Helpers\HtmlSanitizer::sanitizeCms((string) ($article['content'] ?? '')) !!}
            </div>

            @if (! empty($children))
                <h2 class="govuk-heading-m">{{ __('govuk_alpha.kb.related_title') }}</h2>
                <ul class="govuk-list">
                    @foreach ($children as $child)
                        <li>
                            <a class="govuk-link" href="{{ route('govuk-alpha.kb.show', ['tenantSlug' => $tenantSlug, 'id' => $child['id']]) }}">{{ $child['title'] ?? '' }}</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endsection
