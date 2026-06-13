{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $document = $document ?? null;
        $docType = $docType ?? '';
        $hasContent = is_array($document) && trim((string) ($document['content'] ?? '')) !== '';
        $documentTitle = $hasContent && trim((string) ($document['title'] ?? '')) !== ''
            ? $document['title']
            : __('govuk_alpha.legal.documents.' . $docType . '.title');
        $updated = $document['effective_date'] ?? ($document['updated_at'] ?? null);
        $version = $document['version_number'] ?? null;
        $contactHref = route('govuk-alpha.contact', ['tenantSlug' => $tenantSlug]);
    @endphp

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <a class="govuk-back-link" href="{{ route('govuk-alpha.legal.hub', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.legal.back_to_hub') }}</a>

            <span class="govuk-caption-l">{{ __('govuk_alpha.legal.caption', ['community' => $communityName]) }}</span>
            <h1 class="govuk-heading-xl">{{ $documentTitle }}</h1>

            @if ($hasContent)
                @if ($updated || $version)
                    <p class="govuk-body-s nexus-alpha-meta">
                        @if ($updated)
                            {{ __('govuk_alpha.legal.updated_label') }}: {{ \Illuminate\Support\Str::of((string) $updated)->before('T') }}
                        @endif
                        @if ($version)
                            @if ($updated) · @endif
                            {{ __('govuk_alpha.legal.version_label') }} {{ $version }}
                        @endif
                    </p>
                @endif

                {{-- Content is sanitized on save by the legal document service. --}}
                <div class="legal-content govuk-body">
                    {!! $document['content'] !!}
                </div>
            @else
                {{-- No tenant-managed document published: render the general GOV.UK-structured policy. --}}
                <div class="govuk-inset-text">{{ __('govuk_alpha.legal.no_document_notice', ['community' => $communityName]) }}</div>

                @switch($docType)
                    @case('terms')
                        <p class="govuk-body">{{ __('govuk_alpha.legal.fallback.terms_intro', ['name' => $communityName]) }}</p>
                        <ul class="govuk-list govuk-list--bullet">
                            @foreach (__('govuk_alpha.legal.fallback.terms_points') as $point)
                                <li>{{ $point }}</li>
                            @endforeach
                        </ul>
                        @break

                    @case('privacy')
                        <p class="govuk-body">{{ __('govuk_alpha.legal.fallback.privacy_intro', ['name' => $communityName]) }}</p>
                        <ul class="govuk-list govuk-list--bullet">
                            @foreach (__('govuk_alpha.legal.fallback.privacy_points') as $point)
                                <li>{{ $point }}</li>
                            @endforeach
                        </ul>
                        @break

                    @case('cookies')
                        <p class="govuk-body">{{ __('govuk_alpha.legal.fallback.cookies_intro', ['name' => $communityName]) }}</p>
                        <h2 class="govuk-heading-m">{{ __('govuk_alpha.legal.fallback.cookies_types_title') }}</h2>
                        <ul class="govuk-list govuk-list--bullet">
                            @foreach (__('govuk_alpha.legal.fallback.cookies_types') as $type)
                                <li>{{ $type }}</li>
                            @endforeach
                        </ul>
                        <p class="govuk-body">{{ __('govuk_alpha.legal.fallback.cookies_control') }}</p>
                        @break

                    @case('community_guidelines')
                        <p class="govuk-body">{{ __('govuk_alpha.legal.fallback.community_intro', ['name' => $communityName]) }}</p>
                        @foreach (__('govuk_alpha.legal.fallback.community_sections') as $section)
                            <h2 class="govuk-heading-m">{{ $section['heading'] }}</h2>
                            <p class="govuk-body">{{ $section['body'] }}</p>
                        @endforeach
                        @break

                    @case('acceptable_use')
                        <p class="govuk-body">{{ __('govuk_alpha.legal.fallback.acceptable_intro', ['name' => $communityName]) }}</p>
                        @foreach (__('govuk_alpha.legal.fallback.acceptable_sections') as $section)
                            <h2 class="govuk-heading-m">{{ $section['heading'] }}</h2>
                            <p class="govuk-body">{{ $section['body'] }}</p>
                        @endforeach
                        @break
                @endswitch

                <p class="govuk-body">
                    {{ __('govuk_alpha.legal.fallback.contact_prompt_before') }}
                    <a class="govuk-link" href="{{ $contactHref }}">{{ __('govuk_alpha.legal.fallback.contact_link') }}</a>{{ __('govuk_alpha.legal.fallback.contact_prompt_after') }}
                </p>
            @endif
        </div>
    </div>
@endsection
