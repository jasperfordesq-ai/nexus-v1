{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.goals.show', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}">{{ __('govuk_alpha_goals.common.back_to_goal') }}</a>

    @php
        $gTitle = trim((string) ($goal['title'] ?? '')) ?: __('govuk_alpha_goals.insights.title');
        $streak = (int) ($insights['streak_count'] ?? 0);
        $best = (int) ($insights['best_streak_count'] ?? 0);
        $checkinCount = (int) ($insights['checkin_count'] ?? 0);
        $frequency = (string) ($insights['checkin_frequency'] ?? 'none');
        $hasCadence = $frequency !== 'none';
        $isDue = (bool) ($insights['is_checkin_due'] ?? false);
        $milestones = is_array($insights['milestones'] ?? null) ? $insights['milestones'] : [];
        $completedMilestones = (int) ($insights['completed_milestones'] ?? 0);
        $milestoneCount = (int) ($insights['milestone_count'] ?? 0);
        $milestonePercent = $milestoneCount > 0 ? (int) round(($completedMilestones / $milestoneCount) * 100) : 0;
        $buddyNotes = is_array($insights['buddy_notes'] ?? null) ? $insights['buddy_notes'] : [];
        $fmtDate = function ($value): ?string {
            if (empty($value)) {
                return null;
            }
            try {
                return \Illuminate\Support\Carbon::parse($value)->isoFormat('D MMM YYYY, HH:mm');
            } catch (\Throwable $e) {
                return null;
            }
        };
        $lastCheckin = $fmtDate($insights['last_checkin_at'] ?? null);
        $nextDue = $fmtDate($insights['next_checkin_due_at'] ?? null);
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha_goals.insights.caption') }}: {{ $gTitle }}</span>
    <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">{{ __('govuk_alpha_goals.insights.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_goals.insights.intro') }}</p>

    @if (empty($insights))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_goals.insights.load_failed') }}</p></div>
    @else
        <dl class="govuk-summary-list govuk-!-margin-bottom-6">
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_goals.insights.current_streak') }}</dt>
                <dd class="govuk-summary-list__value">
                    {{ trans_choice('govuk_alpha_goals.insights.streak_value', $streak, ['count' => $streak]) }}
                    <span class="govuk-!-display-block govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha_goals.insights.best_streak', ['count' => $best]) }}</span>
                </dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_goals.insights.next_checkin') }}</dt>
                <dd class="govuk-summary-list__value">
                    @if ($isDue)
                        <strong class="govuk-tag govuk-tag--orange">{{ __('govuk_alpha_goals.insights.checkin_due') }}</strong>
                    @elseif ($nextDue)
                        {{ $nextDue }}
                    @else
                        {{ __('govuk_alpha_goals.insights.no_cadence') }}
                    @endif
                    <span class="govuk-!-display-block govuk-body-s nexus-alpha-meta">
                        @if ($hasCadence)
                            {{ __('govuk_alpha_goals.insights.frequency_helper', ['frequency' => __('govuk_alpha_goals.frequency.' . $frequency)]) }}
                        @else
                            {{ __('govuk_alpha_goals.insights.no_cadence_helper') }}
                        @endif
                    </span>
                </dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_goals.insights.checkins') }}</dt>
                <dd class="govuk-summary-list__value">
                    {{ trans_choice('govuk_alpha_goals.insights.checkins_value', $checkinCount, ['count' => $checkinCount]) }}
                    <span class="govuk-!-display-block govuk-body-s nexus-alpha-meta">
                        @if ($lastCheckin)
                            {{ __('govuk_alpha_goals.insights.last_checkin', ['date' => $lastCheckin]) }}
                        @else
                            {{ __('govuk_alpha_goals.insights.no_checkins') }}
                        @endif
                    </span>
                </dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha_goals.insights.milestones') }}</dt>
                <dd class="govuk-summary-list__value">
                    {{ __('govuk_alpha_goals.insights.milestones_value', ['completed' => $completedMilestones, 'total' => $milestoneCount]) }}
                    <progress class="govuk-!-display-block" max="100" value="{{ $milestonePercent }}" aria-label="{{ __('govuk_alpha_goals.insights.milestones_progress_aria', ['percent' => $milestonePercent]) }}">{{ $milestonePercent }}%</progress>
                </dd>
            </div>
        </dl>

        @if (!empty($milestones))
            <h2 class="govuk-heading-l">{{ __('govuk_alpha_goals.insights.milestone_plan') }}</h2>
            <ul class="govuk-list govuk-list--spaced nexus-alpha-card-list govuk-!-margin-bottom-6">
                @foreach ($milestones as $milestone)
                    @php
                        $msDone = !empty($milestone['completed_at']);
                        $msPct = (int) round((float) ($milestone['target_percent'] ?? 0));
                    @endphp
                    <li class="nexus-alpha-card">
                        <div class="nexus-alpha-module-row">
                            <span class="govuk-body govuk-!-margin-bottom-0">{{ $milestone['title'] ?? '' }}</span>
                            <strong class="govuk-tag {{ $msDone ? 'govuk-tag--green' : 'govuk-tag--grey' }}">
                                {{ $msDone ? __('govuk_alpha_goals.insights.milestone_done') : __('govuk_alpha_goals.insights.milestone_target', ['percent' => $msPct]) }}
                            </strong>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        @if (!empty($buddyNotes))
            <h2 class="govuk-heading-l">{{ __('govuk_alpha_goals.insights.recent_buddy_support') }}</h2>
            <ul class="govuk-list nexus-alpha-card-list govuk-!-margin-bottom-6">
                @foreach ($buddyNotes as $note)
                    @php
                        $noteType = (string) ($note['type'] ?? 'encouragement');
                        $buddyTypeLabels = __('govuk_alpha_goals.buddy_type');
                        $typeLabel = (is_array($buddyTypeLabels) && array_key_exists($noteType, $buddyTypeLabels)) ? __('govuk_alpha_goals.buddy_type.' . $noteType) : $noteType;
                        $noteWhen = $fmtDate($note['created_at'] ?? null);
                    @endphp
                    <li class="nexus-alpha-card">
                        <p class="govuk-body govuk-!-margin-bottom-1"><strong>{{ $typeLabel }}</strong></p>
                        @if (trim((string) ($note['message'] ?? '')) !== '')
                            <p class="govuk-body govuk-!-margin-bottom-1">{{ $note['message'] }}</p>
                        @endif
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-0">
                            {{ trim((string) ($note['buddy_name'] ?? '')) ?: __('govuk_alpha_goals.common.a_member') }}
                            @if ($noteWhen)
                                · {{ $noteWhen }}
                            @endif
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif

    <nav class="govuk-!-margin-top-4" aria-label="{{ __('govuk_alpha_goals.insights.title') }}">
        <ul class="govuk-list">
            @if ($isOwner)
                <li><a class="govuk-link" href="{{ route('govuk-alpha.goals.checkin', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}">{{ __('govuk_alpha_goals.insights.log_checkin_link') }}</a></li>
                <li><a class="govuk-link" href="{{ route('govuk-alpha.goals.reminder', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}">{{ __('govuk_alpha_goals.insights.reminder_link') }}</a></li>
            @endif
            @if ($isBuddy)
                <li><a class="govuk-link" href="{{ route('govuk-alpha.goals.buddy-actions', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}">{{ __('govuk_alpha_goals.insights.buddy_actions_link') }}</a></li>
            @endif
        </ul>
    </nav>
@endsection
