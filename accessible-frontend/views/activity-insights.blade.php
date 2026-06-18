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
        $skillsData = is_array($activity['skills_breakdown'] ?? null) ? $activity['skills_breakdown'] : [];
        $skills = is_array($skillsData['skills'] ?? null) ? $skillsData['skills'] : [];
        $monthly = is_array($activity['monthly_hours'] ?? null) ? $activity['monthly_hours'] : [];
        $timeline = is_array($activity['timeline'] ?? null) ? $activity['timeline'] : [];

        // Largest single given/received bar value drives the chart scale, so the
        // two bars per month share a common axis (mirrors React's SimpleBarChart).
        $maxBar = 0.0;
        foreach ($monthly as $m) {
            $maxBar = max($maxBar, (float) ($m['given'] ?? 0), (float) ($m['received'] ?? 0));
        }

        $hasChart = false;
        foreach ($monthly as $m) {
            if ((float) ($m['given'] ?? 0) > 0 || (float) ($m['received'] ?? 0) > 0) { $hasChart = true; break; }
        }

        $netBalance = (float) ($hs['net_balance'] ?? 0);
        $exchanges = (int) ($hs['transactions_given'] ?? 0) + (int) ($hs['transactions_received'] ?? 0);

        // Activity-type → GOV.UK tag colour. Keys are the literal activity_type
        // values MemberActivityService emits, plus React's extra types as
        // forward-compatible aliases.
        $typeColours = [
            'gave_hours' => 'green',
            'received_hours' => 'turquoise',
            'exchange' => 'green',
            'post' => 'pink',
            'comment' => 'blue',
            'connection' => 'light-blue',
            'event_rsvp' => 'purple',
            'event' => 'purple',
            'listing' => 'blue',
            'message' => 'turquoise',
            'review' => 'yellow',
            'activity' => 'grey',
        ];

        $dateFmt = fn ($v): ?string => $v ? \Illuminate\Support\Carbon::parse($v)->diffForHumans() : null;
    @endphp

    <a class="govuk-back-link"
       href="{{ route('govuk-alpha.activity', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_activity.insights.back_to_activity') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_activity.insights.caption') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_activity.insights.heading') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_activity.insights.intro') }}</p>

    {{-- ===== Headline stats ===== --}}
    <h2 class="govuk-visually-hidden">{{ __('govuk_alpha_activity.insights.stats_title') }}</h2>
    <dl class="nexus-alpha-stat-grid govuk-!-margin-bottom-8">
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_activity.insights.stat_hours_given') }}</dt>
            <dd>{{ number_format((float) ($hs['hours_given'] ?? 0), 1) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_activity.insights.stat_hours_received') }}</dt>
            <dd>{{ number_format((float) ($hs['hours_received'] ?? 0), 1) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_activity.insights.stat_connections') }}</dt>
            <dd>{{ number_format((int) ($cs['total_connections'] ?? 0)) }}</dd>
        </div>
        <div class="nexus-alpha-stat">
            <dt>{{ __('govuk_alpha_activity.insights.stat_exchanges') }}</dt>
            <dd>{{ number_format($exchanges) }}</dd>
        </div>
    </dl>

    {{-- ===== Two-column layout: activity (main) + stats/skills (sidebar) ===== --}}
    <div class="govuk-grid-row">
        {{-- Main column: monthly chart + recent activity timeline --}}
        <div class="govuk-grid-column-two-thirds">
            <h2 class="govuk-visually-hidden">{{ __('govuk_alpha_activity.insights.activity_column_title') }}</h2>

            {{-- Monthly dual-bar chart (given vs received) --}}
            <h3 class="govuk-heading-l">{{ __('govuk_alpha_activity.insights.chart_title') }}</h3>
            <p class="govuk-body">{{ __('govuk_alpha_activity.insights.chart_intro') }}</p>
            @if ($hasChart)
                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-4">
                    <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha_activity.insights.chart_given') }}</strong>
                    <strong class="govuk-tag govuk-tag--turquoise">{{ __('govuk_alpha_activity.insights.chart_received') }}</strong>
                </p>
                <div role="img" aria-label="{{ __('govuk_alpha_activity.insights.chart_aria') }}">
                    @foreach ($monthly as $m)
                        @php
                            $given = (float) ($m['given'] ?? 0);
                            $received = (float) ($m['received'] ?? 0);
                            $label = trim((string) ($m['label'] ?? ($m['month'] ?? '')));
                            $givenPct = $maxBar > 0 ? (int) round(($given / $maxBar) * 100) : 0;
                            $receivedPct = $maxBar > 0 ? (int) round(($received / $maxBar) * 100) : 0;
                            $monthAria = __('govuk_alpha_activity.insights.chart_month_aria', [
                                'label' => $label,
                                'given' => number_format($given, 1),
                                'received' => number_format($received, 1),
                            ]);
                        @endphp
                        @if ($given > 0 || $received > 0)
                            <div class="govuk-!-margin-bottom-3">
                                <p class="govuk-body-s govuk-!-margin-bottom-1">
                                    <strong>{{ $label }}</strong>
                                    <span class="nexus-alpha-meta">
                                        — {{ __('govuk_alpha_activity.insights.chart_given') }} {{ number_format($given, 1) }} ·
                                        {{ __('govuk_alpha_activity.insights.chart_received') }} {{ number_format($received, 1) }}
                                    </span>
                                </p>
                                <progress max="100" value="{{ $givenPct }}"
                                          aria-label="{{ $monthAria }}">{{ $givenPct }}%</progress>
                                <progress max="100" value="{{ $receivedPct }}"
                                          aria-hidden="true">{{ $receivedPct }}%</progress>
                            </div>
                        @endif
                    @endforeach
                </div>
            @else
                <div class="govuk-inset-text"><p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha_activity.insights.chart_empty') }}</p></div>
            @endif

            {{-- Recent activity timeline with type badges + relative timestamps --}}
            <h3 class="govuk-heading-l govuk-!-margin-top-7">{{ __('govuk_alpha_activity.insights.timeline_title') }}</h3>
            @if (empty($timeline))
                <div class="govuk-inset-text">
                    <h4 class="govuk-heading-s govuk-!-margin-bottom-1">{{ __('govuk_alpha_activity.insights.timeline_empty_title') }}</h4>
                    <p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha_activity.insights.timeline_empty') }}</p>
                </div>
            @else
                <ul class="govuk-list govuk-list--spaced">
                    @foreach ($timeline as $item)
                        @php
                            $type = trim((string) ($item['activity_type'] ?? 'activity'));
                            $colour = $typeColours[$type] ?? 'grey';
                            $typeKey = 'govuk_alpha_activity.insights.type_' . $type;
                            $typeLabel = \Illuminate\Support\Facades\Lang::has($typeKey)
                                ? __($typeKey)
                                : __('govuk_alpha_activity.insights.type_activity');
                            $text = trim((string) ($item['description'] ?? ($item['title'] ?? ($item['message'] ?? ($item['content'] ?? '')))));
                            $when = $dateFmt($item['created_at'] ?? ($item['date'] ?? null));
                        @endphp
                        @if ($text !== '' || $when)
                            <li>
                                <strong class="govuk-tag govuk-tag--{{ $colour }}">{{ $typeLabel }}</strong>
                                @if ($text !== '') {{ \Illuminate\Support\Str::limit($text, 160) }}@endif
                                @if ($when) <span class="govuk-body-s nexus-alpha-meta">— {{ $when }}</span>@endif
                            </li>
                        @endif
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Sidebar column: quick stats + skills --}}
        <div class="govuk-grid-column-one-third">
            <h2 class="govuk-visually-hidden">{{ __('govuk_alpha_activity.insights.sidebar_title') }}</h2>

            {{-- Quick stats card --}}
            <div class="nexus-alpha-card govuk-!-margin-bottom-6">
                <h3 class="govuk-heading-m">{{ __('govuk_alpha_activity.insights.quick_stats_title') }}</h3>
                <dl class="govuk-summary-list govuk-!-margin-bottom-0">
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha_activity.insights.quick_groups_joined') }}</dt>
                        <dd class="govuk-summary-list__value">{{ number_format((int) ($cs['groups_joined'] ?? 0)) }}</dd>
                    </div>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha_activity.insights.quick_posts_30d') }}</dt>
                        <dd class="govuk-summary-list__value">{{ number_format((int) ($eng['posts_count'] ?? 0)) }}</dd>
                    </div>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha_activity.insights.quick_comments_30d') }}</dt>
                        <dd class="govuk-summary-list__value">{{ number_format((int) ($eng['comments_count'] ?? 0)) }}</dd>
                    </div>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha_activity.insights.quick_likes_given_30d') }}</dt>
                        <dd class="govuk-summary-list__value">{{ number_format((int) ($eng['likes_given'] ?? 0)) }}</dd>
                    </div>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha_activity.insights.quick_likes_received_30d') }}</dt>
                        <dd class="govuk-summary-list__value">{{ number_format((int) ($eng['likes_received'] ?? 0)) }}</dd>
                    </div>
                    <div class="govuk-summary-list__row">
                        <dt class="govuk-summary-list__key">{{ __('govuk_alpha_activity.insights.quick_net_balance') }}</dt>
                        <dd class="govuk-summary-list__value">
                            @php
                                $netAbs = number_format(abs($netBalance), 1);
                                if ($netBalance > 0) {
                                    $netText = __('govuk_alpha_activity.insights.net_balance_positive', ['value' => $netAbs]);
                                    $netTag = 'govuk-tag--green';
                                    $netMeaning = __('govuk_alpha_activity.insights.net_balance_positive_meaning');
                                } elseif ($netBalance < 0) {
                                    $netText = __('govuk_alpha_activity.insights.net_balance_negative', ['value' => '-' . $netAbs]);
                                    $netTag = 'govuk-tag--red';
                                    $netMeaning = __('govuk_alpha_activity.insights.net_balance_negative_meaning');
                                } else {
                                    $netText = __('govuk_alpha_activity.insights.net_balance_negative', ['value' => $netAbs]);
                                    $netTag = 'govuk-tag--grey';
                                    $netMeaning = __('govuk_alpha_activity.insights.net_balance_even_meaning');
                                }
                            @endphp
                            <strong class="govuk-tag {{ $netTag }}">{{ $netText }}</strong>
                            <span class="govuk-visually-hidden">{{ $netMeaning }}</span>
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Skills card with offering/requesting tags + endorsement counts --}}
            <div class="nexus-alpha-card">
                <h3 class="govuk-heading-m">{{ __('govuk_alpha_activity.insights.skills_title') }}</h3>
                @if (empty($skills))
                    <p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha_activity.insights.skills_empty') }}</p>
                @else
                    @php
                        $offeringCount = (int) ($skillsData['offering_count'] ?? 0);
                        $requestingCount = (int) ($skillsData['requesting_count'] ?? 0);
                    @endphp
                    <p class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha_activity.insights.skills_summary', ['offering' => $offeringCount, 'requesting' => $requestingCount]) }}</p>
                    <ul class="govuk-list nexus-alpha-skill-list govuk-!-margin-bottom-0">
                        @foreach (array_slice($skills, 0, 8) as $skill)
                            @php
                                $skillName = trim((string) ($skill['skill_name'] ?? ($skill['name'] ?? '')));
                                $isOffering = (bool) ($skill['is_offering'] ?? false);
                                $isRequesting = (bool) ($skill['is_requesting'] ?? false);
                                $endorsements = (int) ($skill['endorsements'] ?? 0);
                            @endphp
                            @if ($skillName !== '')
                                <li>
                                    <span>{{ $skillName }}</span>
                                    @if ($isOffering)
                                        <strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha_activity.insights.skill_offering') }}</strong>
                                    @endif
                                    @if ($isRequesting)
                                        <strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha_activity.insights.skill_requesting') }}</strong>
                                    @endif
                                    @if ($endorsements > 0)
                                        <span class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha_activity.insights.skill_endorsements', ['count' => $endorsements]) }}</span>
                                    @endif
                                </li>
                            @endif
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <p class="govuk-body govuk-!-margin-top-7">
        <a class="govuk-link"
           href="{{ route('govuk-alpha.activity', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_activity.insights.back_to_activity') }}</a>
    </p>
@endsection
