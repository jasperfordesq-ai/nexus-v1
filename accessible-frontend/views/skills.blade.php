{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $renderNode = function ($node) use (&$renderNode) {
            $name = trim((string) ($node['name'] ?? ''));
            if ($name === '') { return ''; }
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $skills = is_array($node['skills'] ?? null) ? $node['skills'] : [];
            $out = '<li>' . e($name);
            if (!empty($skills) || !empty($children)) {
                $out .= '<ul class="govuk-list govuk-!-margin-left-3">';
                foreach ($skills as $s) {
                    $sn = trim((string) (is_array($s) ? ($s['name'] ?? '') : $s));
                    if ($sn !== '') { $out .= '<li>' . e($sn) . '</li>'; }
                }
                foreach ($children as $c) { $out .= $renderNode($c); }
                $out .= '</ul>';
            }
            return $out . '</li>';
        };
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha.skills.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.skills.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.skills.description') }}</p>

    <form method="get" action="{{ route('govuk-alpha.skills.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
        <div class="govuk-form-group">
            <label class="govuk-label" for="skill">{{ __('govuk_alpha.skills.search_label') }}</label>
            <div id="skill-hint" class="govuk-hint">{{ __('govuk_alpha.skills.search_hint') }}</div>
            <input class="govuk-input govuk-!-width-two-thirds" id="skill" name="skill" type="search" value="{{ $skillQuery ?? '' }}" aria-describedby="skill-hint">
        </div>
        <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.skills.search_button') }}</button>
    </form>

    @if (($skillQuery ?? '') !== '')
        <h2 class="govuk-heading-l">{{ __('govuk_alpha.skills.members_with', ['skill' => $skillQuery]) }}</h2>
        @if (empty($skillMembers))
            <p class="govuk-inset-text">{{ __('govuk_alpha.skills.no_members') }}</p>
        @else
            <ul class="govuk-list">
                @foreach ($skillMembers as $m)
                    @php
                        $mName = trim((string) ($m['name'] ?? '')) ?: trim((string) ($m['first_name'] ?? '') . ' ' . (string) ($m['last_name'] ?? ''));
                        $mId = (int) ($m['id'] ?? ($m['user_id'] ?? 0));
                    @endphp
                    @if ($mName !== '')
                        <li>@if ($mId > 0)<a class="govuk-link" href="{{ route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $mId]) }}">{{ $mName }}</a>@else{{ $mName }}@endif</li>
                    @endif
                @endforeach
            </ul>
        @endif
        <hr class="govuk-section-break govuk-section-break--visible govuk-section-break--l">
    @endif

    @if (!empty($skillTree))
        <ul class="govuk-list govuk-list--spaced">
            @foreach ($skillTree as $node)
                {!! $renderNode($node) !!}
            @endforeach
        </ul>
    @else
        <p class="govuk-inset-text">{{ __('govuk_alpha.skills.empty') }}</p>
    @endif
@endsection
