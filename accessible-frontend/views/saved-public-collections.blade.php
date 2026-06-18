{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $collections = $collections ?? [];
        $ownerId = (int) ($ownerId ?? 0);
        $ownerName = trim((string) ($ownerName ?? ''));
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $ownerId]) }}">{{ __('govuk_alpha_saved.public.back_to_profile') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_saved.public.caption', ['name' => $ownerName !== '' ? $ownerName : __('govuk_alpha_saved.wall.from_someone')]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_saved.public.heading', ['name' => $ownerName !== '' ? $ownerName : __('govuk_alpha_saved.wall.from_someone')]) }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_saved.public.description') }}</p>

    @if (empty($collections))
        <div class="govuk-inset-text">
            <h2 class="govuk-heading-m">{{ __('govuk_alpha_saved.public.empty_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha_saved.public.empty_body') }}</p>
        </div>
    @else
        <ul class="govuk-list nexus-alpha-card-list">
            @foreach ($collections as $c)
                @php
                    $cId = (int) ($c['id'] ?? 0);
                    $cName = trim((string) ($c['name'] ?? ''));
                    $cDesc = trim((string) ($c['description'] ?? ''));
                    $cColor = (string) ($c['color'] ?? '#6366f1');
                    $cColorSafe = preg_match('/^#[0-9a-fA-F]{6}$/', $cColor) ? $cColor : '#6366f1';
                    $cCount = (int) ($c['items_count'] ?? 0);
                @endphp
                <li class="nexus-alpha-card">
                    <h2 class="govuk-heading-m govuk-!-margin-bottom-1">
                        <span class="nexus-alpha-avatar" style="background-color: {{ $cColorSafe }}; width: 1rem; height: 1rem; display: inline-block; border-radius: 50%; vertical-align: middle;" aria-hidden="true"></span>
                        <a class="govuk-link" href="{{ route('govuk-alpha.saved.collection-detail', ['tenantSlug' => $tenantSlug, 'id' => $cId]) }}">{{ $cName !== '' ? $cName : __('govuk_alpha_saved.detail.title') }}</a>
                    </h2>
                    <p class="govuk-body nexus-alpha-meta">{{ trans_choice('govuk_alpha_saved.collections.count', $cCount, ['count' => $cCount]) }}</p>
                    @if ($cDesc !== '')
                        <p class="govuk-body">{{ $cDesc }}</p>
                    @endif
                    <p class="govuk-body govuk-!-margin-bottom-0">
                        <a class="govuk-link" href="{{ route('govuk-alpha.saved.collection-detail', ['tenantSlug' => $tenantSlug, 'id' => $cId]) }}">{{ __('govuk_alpha_saved.collections.view') }}</a>
                    </p>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
