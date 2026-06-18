{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
{{--
    Recursive resource-category tree (hierarchical sidebar navigation).
    Each node links to the library filtered by that category. Renders nested
    children as nested <ul> lists so the hierarchy is conveyed semantically to
    screen readers. Expects $nodes (array of category nodes) and $depth (int).
--}}
@foreach ($nodes as $node)
    @php
        $isSelected = ((int) ($selectedCategory ?? 0)) === (int) $node['id'];
        $treeParams = array_filter([
            'tenantSlug' => $tenantSlug,
            'q' => ($searchQuery ?? '') !== '' ? $searchQuery : null,
            'category_id' => $node['id'],
            'reorder' => ($reorderMode ?? false) ? '1' : null,
        ], static fn ($v) => $v !== null);
    @endphp
    <li>
        <a class="govuk-link {{ $isSelected ? 'govuk-!-font-weight-bold' : '' }}"
           @if ($isSelected) aria-current="true" @endif
           href="{{ route('govuk-alpha.resources.library', $treeParams) }}">{{ $node['name'] }}@if (($node['resource_count'] ?? 0) > 0) <span class="nexus-alpha-meta">({{ $node['resource_count'] }})</span>@endif</a>
        @if (!empty($node['children']))
            <ul class="govuk-list govuk-!-margin-bottom-0 govuk-!-margin-left-3">
                @include('accessible-frontend::partials.resources-category-tree', ['nodes' => $node['children'], 'depth' => ($depth ?? 0) + 1])
            </ul>
        @endif
    </li>
@endforeach
