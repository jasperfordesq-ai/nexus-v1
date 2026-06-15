{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $hs = is_array($activity['hours_summary'] ?? null) ? $activity['hours_summary'] : [];
        $cs = is_array($activity['connection_stats'] ?? null) ? $activity['connection_stats'] : [];
        $eng = is_array($activity['engagement'] ?? null) ? $activity['engagement'] : [];
        $skills = is_array($activity['skills_breakdown'] ?? null) ? $activity['skills_breakdown'] : [];
        $monthly = is_array($activity['monthly_hours'] ?? null) ? $activity['monthly_hours'] : [];
        $timeline = is_array($activity['timeline'] ?? null) ? $activity['timeline'] : [];
        $maxMonth = 0;
        foreach ($monthly as $m) { $maxMonth = max($maxMonth, (float) ($m['given'] ?? 0) + (float) ($m['received'] ?? 0)); }
        $dateFmt = fn ($v): ?string => $v ? \Illuminate\Support\Carbon::parse($v)->diffForHumans() : null;
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha.activity.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.activity.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.activity.description') }}</p>

    <dl class="nexus-alpha-stat-grid govuk-!-margin-bottom-8">
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha.activity.hours_given') }}</dt>
            <dd>{{ number_format((float) ($hs['hours_given'] ?? 0), 1) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha.activity.hours_received') }}</dt>
            <dd>{{ number_format((float) ($hs['hours_received'] ?? 0), 1) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha.activity.connections') }}</dt>
            <dd>{{ number_format((int) ($cs['total_connections'] ?? 0)) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha.activity.groups_joined') }}</dt>
            <dd>{{ number_format((int) ($cs['groups_joined'] ?? 0)) }}</dd>
        </div>
    </dl>

    {{-- ===== Engagement quick-stats (last 30 days) ===== --}}
    @if (!empty($eng))
        <h2 class="govuk-heading-l">{{ __('govuk_alpha.polish_discovery.activity_engagement_title') }}</h2>
        <dl class="nexus-alpha-stat-grid govuk-!-margin-bottom-6">
            <div class="nexus-alpha-stat">
                <dt>{{ __('govuk_alpha.polish_discovery.activity_posts_count') }}</dt>
                <dd>{{ number_format((int) ($eng['posts_count'] ?? 0)) }}</dd>
            </div>
            <div class="nexus-alpha-stat">
                <dt>{{ __('govuk_alpha.polish_discovery.activity_comments_count') }}</dt>
                <dd>{{ number_format((int) ($eng['comments_count'] ?? 0)) }}</dd>
            </div>
            <div class="nexus-alpha-stat">
                <dt>{{ __('govuk_alpha.polish_discovery.activity_likes_given') }}</dt>
                <dd>{{ number_format((int) ($eng['likes_given'] ?? 0)) }}</dd>
            </div>
            <div class="nexus-alpha-stat">
                <dt>{{ __('govuk_alpha.polish_discovery.activity_likes_received') }}</dt>
                <dd>{{ number_format((int) ($eng['likes_received'] ?? 0)) }}</dd>
            </div>
            @if (isset($hs['net_balance']))
                <div class="nexus-alpha-stat">
                    <dt>{{ __('govuk_alpha.polish_discovery.activity_net_balance') }}</dt>
                    <dd>{{ __('govuk_alpha.polish_discovery.activity_net_hours', ['value' => number_format((float) $hs['net_balance'], 1)]) }}</dd>
                </div>
            @endif
        </dl>
    @endif

    {{-- ===== Skills breakdown ===== --}}
    @if (!empty($skills))
        <h2 class="govuk-heading-l">{{ __('govuk_alpha.polish_discovery.activity_skills_title') }}</h2>
        <dl class="govuk-summary-list govuk-!-margin-bottom-6">
            @foreach ($skills as $skill)
                @php
                    $skillName = trim((string) ($skill['name'] ?? ($skill['category'] ?? '')));
                    $skillHours = number_format((float) ($skill['hours'] ?? ($skill['total_hours'] ?? 0)), 1);
                @endphp
                @if ($skillName !== '')
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ $skillName }}</dt>
                        <dd class="govuk-summary-list__value">{{ __('govuk_alpha.polish_discovery.activity_skills_hours_label', ['hours' => $skillHours]) }}</dd>
                    </div>
                @endif
            @endforeach
        </dl>
    @endif

    @if (!empty($monthly))
        <h2 class="govuk-heading-l">{{ __('govuk_alpha.activity.monthly_title') }}</h2>
        @foreach ($monthly as $m)
            @php
                $given = (float) ($m['given'] ?? 0);
                $received = (float) ($m['received'] ?? 0);
                $totalM = $given + $received;
                $pct = $maxMonth > 0 ? (int) round(($totalM / $maxMonth) * 100) : 0;
                $label = trim((string) ($m['label'] ?? ($m['month'] ?? '')));
            @endphp
            <div class="govuk-!-margin-bottom-2">
                <p class="govuk-body-s govuk-!-margin-bottom-1">{{ $label }} — {{ __('govuk_alpha.activity.monthly_given') }} {{ number_format($given, 1) }} · {{ __('govuk_alpha.activity.monthly_received') }} {{ number_format($received, 1) }}</p>
                <progress max="100" value="{{ $pct }}" aria-label="{{ $label }}: {{ number_format($totalM, 1) }}">{{ $pct }}%</progress>
            </div>
        @endforeach
    @endif

    <h2 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha.activity.timeline_title') }}</h2>
    @if (empty($timeline))
        <p class="govuk-inset-text">{{ __('govuk_alpha.activity.timeline_empty') }}</p>
    @else
        <ul class="govuk-list govuk-list--spaced">
            @foreach ($timeline as $item)
                @php
                    $text = trim((string) ($item['description'] ?? ($item['title'] ?? ($item['message'] ?? ($item['content'] ?? '')))));
                    $when = $dateFmt($item['created_at'] ?? ($item['date'] ?? null));
                @endphp
                @if ($text !== '' || $when)
                    <li>
                        @if ($text !== ''){{ \Illuminate\Support\Str::limit($text, 160) }}@endif
                        @if ($when) <span class="govuk-body-s nexus-alpha-meta">— {{ $when }}</span>@endif
                    </li>
                @endif
            @endforeach
        </ul>
    @endif
@endsection
