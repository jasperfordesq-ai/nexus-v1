<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [
    'common' => [
        'back_to_goal' => 'Back to goal',
        'a_member' => 'A member',
        'success_title' => 'Success',
        'error_title' => 'There is a problem',
    ],

    'insights' => [
        'title' => 'Goal insights',
        'caption' => 'Goal',
        'intro' => 'A summary of your check-in cadence, streaks, milestones and recent buddy support.',
        'load_failed' => 'We could not load the insights for this goal.',
        'current_streak' => 'Current streak',
        'streak_value' => '{0} No check-ins yet|{1} 1 check-in in a row|[2,*] :count check-ins in a row',
        'best_streak' => 'Best streak: :count',
        'next_checkin' => 'Next check-in',
        'no_cadence' => 'No cadence set',
        'no_cadence_helper' => 'Set a check-in cadence when you edit this goal.',
        'checkin_due' => 'A check-in is due',
        'frequency_helper' => ':frequency cadence',
        'checkins' => 'Check-ins',
        'checkins_value' => '{0} None recorded|{1} 1 recorded|[2,*] :count recorded',
        'last_checkin' => 'Last check-in: :date',
        'no_checkins' => 'No check-ins yet',
        'milestones' => 'Milestones',
        'milestones_value' => ':completed of :total reached',
        'milestone_plan' => 'Milestone plan',
        'milestone_done' => 'Reached',
        'milestone_target' => 'Target: :percent%',
        'milestones_progress_aria' => 'Milestone progress: :percent percent',
        'recent_buddy_support' => 'Recent buddy support',
        'log_checkin_link' => 'Log a check-in',
        'reminder_link' => 'Reminder settings',
        'buddy_actions_link' => 'Send buddy support',
    ],

    'checkin' => [
        'title' => 'Log a check-in',
        'caption' => 'Goal',
        'intro' => 'Record your real progress, how you are feeling and an optional note. This updates the goal bar and the timeline.',
        'progress_legend' => 'Progress',
        'progress_label' => 'Progress now (percent)',
        'progress_help' => 'Set this to the real progress you want shown on the goal. Leave it blank to log a check-in without changing the bar.',
        'mood_legend' => 'How are you feeling?',
        'mood_none' => 'Prefer not to say',
        'note_label' => 'Note (optional)',
        'note_help' => 'How is it going? Any wins or challenges?',
        'submit' => 'Record check-in',
        'history_title' => 'Recent check-ins',
        'history_empty' => 'You have not recorded any check-ins yet.',
        'history_progress' => 'Progress: :percent%',
        'history_progress_unknown' => 'Progress not recorded',
        'history_mood' => 'Mood: :mood',
    ],

    'reminder' => [
        'title' => 'Reminder settings',
        'caption' => 'Goal',
        'intro' => 'Get an email and a notification reminding you to check in on this goal.',
        'status_active' => 'Reminder active',
        'status_active_detail' => 'You will be reminded :frequency.',
        'status_none' => 'No reminder set',
        'status_none_detail' => 'You are not currently reminded about this goal.',
        'next_reminder' => 'Next reminder: :date',
        'frequency_legend' => 'How often should we remind you?',
        'frequency_label' => 'Reminder frequency',
        'enabled_label' => 'Send me reminders for this goal',
        'save' => 'Save reminder',
        'remove' => 'Remove reminder',
        'remove_warning' => 'Removing the reminder stops all emails and notifications for this goal. You can set it again at any time.',
    ],

    'buddy' => [
        'title' => 'Send buddy support',
        'caption' => 'Goal',
        'intro' => 'Send a small, visible bit of support to the goal owner. Nudges are gentle reminders, not pressure.',
        'type_legend' => 'What would you like to send?',
        'type_label' => 'Support type',
        'message_label' => 'Add a short message (optional)',
        'message_help' => 'Leave this blank to send a friendly default message for the type you chose.',
        'submit' => 'Send to goal owner',
        'not_buddy' => 'Only the goal buddy can send support actions.',
    ],

    'frequency' => [
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'biweekly' => 'Every 2 weeks',
        'monthly' => 'Monthly',
        'none' => 'No cadence',
    ],

    'mood' => [
        'great' => 'Great',
        'good' => 'Good',
        'neutral' => 'Neutral',
        'okay' => 'Okay',
        'struggling' => 'Struggling',
        'stuck' => 'Stuck',
        'motivated' => 'Motivated',
        'grateful' => 'Grateful',
    ],

    'buddy_type' => [
        'nudge' => 'Nudge',
        'encouragement' => 'Encouragement',
        'offer_help' => 'Offer to help',
    ],

    'buddy_type_help' => [
        'nudge' => 'A gentle reminder to keep going.',
        'encouragement' => 'A few words of support and motivation.',
        'offer_help' => 'Let the owner know you can help out.',
    ],

    'states' => [
        'checkin-recorded' => 'Your check-in has been recorded.',
        'checkin-failed' => 'We could not record your check-in. Please try again.',
        'reminder-saved' => 'Your reminder settings have been saved.',
        'reminder-removed' => 'Your reminder has been removed.',
        'reminder-failed' => 'We could not save your reminder. Only the goal owner, or any member for a public goal, can set one.',
        'buddy-action-sent' => 'Your support has been sent to the goal owner.',
        'buddy-action-failed' => 'We could not send your support. You must be the goal buddy.',
    ],

    'nav' => [
        'insights' => 'View goal insights',
    ],
];
