{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        // Map a stored proficiency level to a translated label. Mirrors the
        // React ProficiencyBadge (beginner / intermediate / advanced / expert).
        $proficiencyLabel = function ($level) {
            $level = strtolower(trim((string) $level));
            $allowed = ['beginner', 'intermediate', 'advanced', 'expert'];
            if (!in_array($level, $allowed, true)) { return ''; }
            return __('govuk_alpha.skills.proficiency.' . $level);
        };

        // Render the category hierarchy as a list of drill-in links. Each
        // category links to ?category={id}, which shows that category's skill
        // breakdown with member / offering / requesting counts.
        $renderNode = function ($node) use (&$renderNode, $tenantSlug) {
            $name = trim((string) ($node['name'] ?? ''));
            if ($name === '') { return ''; }
            $id = (int) ($node['id'] ?? 0);
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            if ($id > 0) {
                $href = route('govuk-alpha.skills.index', ['tenantSlug' => $tenantSlug, 'category' => $id]);
                $out = '<li><a class="govuk-link" href="' . e($href) . '">' . e($name) . '</a>';
            } else {
                $out = '<li>' . e($name);
            }
            if (!empty($children)) {
                $out .= '<ul class="govuk-list govuk-!-margin-left-3">';
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
            <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.skills.no_members') }}</p></div>
        @else
            <ul class="govuk-list">
                @foreach ($skillMembers as $m)
                    @php
                        $mName = trim((string) ($m['name'] ?? '')) ?: trim((string) ($m['first_name'] ?? '') . ' ' . (string) ($m['last_name'] ?? ''));
                        $mId = (int) ($m['id'] ?? ($m['user_id'] ?? 0));
                        $prof = $proficiencyLabel($m['proficiency_level'] ?? '');
                        $isOffering = !empty($m['is_offering']);
                        $isRequesting = !empty($m['is_requesting']);
                    @endphp
                    @if ($mName !== '')
                        <li class="govuk-!-margin-bottom-2">
                            @if ($mId > 0)<a class="govuk-link" href="{{ route('govuk-alpha.members.show', ['tenantSlug' => $tenantSlug, 'id' => $mId]) }}">{{ $mName }}</a>@else{{ $mName }}@endif
                            @if ($prof !== '')<strong class="govuk-tag govuk-tag--grey govuk-!-margin-left-1">{{ $prof }}</strong>@endif
                            @if ($isOffering)<strong class="govuk-tag govuk-tag--green govuk-!-margin-left-1">{{ __('govuk_alpha.skills.offers') }}</strong>@endif
                            @if ($isRequesting)<strong class="govuk-tag govuk-tag--blue govuk-!-margin-left-1">{{ __('govuk_alpha.skills.wants') }}</strong>@endif
                        </li>
                    @endif
                @endforeach
            </ul>
        @endif
        <hr class="govuk-section-break govuk-section-break--visible govuk-section-break--l">
    @endif

    @if (($selectedCategory ?? null) !== null)
        @php $catName = trim((string) ($selectedCategory['name'] ?? '')); @endphp
        <h2 class="govuk-heading-l">{{ __('govuk_alpha.skills.skills_in', ['category' => $catName]) }}</h2>
        <p class="govuk-body">
            <a class="govuk-link" href="{{ route('govuk-alpha.skills.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.skills.back_to_categories') }}</a>
        </p>
        @if (empty($categorySkills))
            <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.skills.no_skills_in_category') }}</p></div>
        @else
            <table class="govuk-table">
                <caption class="govuk-visually-hidden">{{ __('govuk_alpha.skills.skills_in', ['category' => $catName]) }}</caption>
                <thead class="govuk-table__head">
                    <tr class="govuk-table__row">
                        <th scope="col" class="govuk-table__header">{{ __('govuk_alpha.skills.col_skill') }}</th>
                        <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha.skills.col_members') }}</th>
                        <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha.skills.col_offering') }}</th>
                        <th scope="col" class="govuk-table__header govuk-table__header--numeric">{{ __('govuk_alpha.skills.col_requesting') }}</th>
                    </tr>
                </thead>
                <tbody class="govuk-table__body">
                    @foreach ($categorySkills as $cs)
                        @php
                            $csName = trim((string) ($cs['skill_name'] ?? ''));
                            $userCount = (int) ($cs['user_count'] ?? 0);
                            $offeringCount = (int) ($cs['offering_count'] ?? 0);
                            $requestingCount = (int) ($cs['requesting_count'] ?? 0);
                            $skillHref = route('govuk-alpha.skills.index', ['tenantSlug' => $tenantSlug, 'skill' => $csName]);
                        @endphp
                        @if ($csName !== '')
                            <tr class="govuk-table__row">
                                <th scope="row" class="govuk-table__header"><a class="govuk-link" href="{{ $skillHref }}">{{ $csName }}</a></th>
                                <td class="govuk-table__cell govuk-table__cell--numeric">{{ $userCount }}</td>
                                <td class="govuk-table__cell govuk-table__cell--numeric">{{ $offeringCount }}</td>
                                <td class="govuk-table__cell govuk-table__cell--numeric">{{ $requestingCount }}</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @endif
        <hr class="govuk-section-break govuk-section-break--visible govuk-section-break--l">
    @endif

    <h2 class="govuk-heading-l">{{ __('govuk_alpha.skills.browse_by_category') }}</h2>
    @if (!empty($skillTree))
        <ul class="govuk-list govuk-list--spaced">
            @foreach ($skillTree as $node)
                {!! $renderNode($node) !!}
            @endforeach
        </ul>
    @else
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.skills.empty') }}</p></div>
    @endif
@endsection
