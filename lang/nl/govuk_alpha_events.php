<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [
    // ---- Navigation links (added by nav-consolidation) ----------------
    'nav' => [
        'browse_by_category' => 'Browse by category',
        'view_location' => 'View location and directions',
        'edit_series' => 'Edit the recurring series',
        'manage_polls' => 'Manage event polls',
        'translate_description' => 'Translate this event',
    ],
    'common' => [
        'back_to_event' => 'Back to event',
        'back_to_events' => 'Back to events',
        'error_title' => 'There is a problem',
        'success_title' => 'Success',
        'warning' => 'Warning',
        'error_prefix' => 'Error:',
        'save' => 'Save',
        'cancel' => 'Cancel',
    ],

    // ---- Category toggle-button browse ---------------------------------
    'browse' => [
        'title' => 'Browse events by category',
        'caption' => 'Events',
        'intro' => 'Choose a category to see the events in it. Select "All events" to clear the filter.',
        'all_categories' => 'All events',
        'all_categories_hint' => 'Show events from every category.',
        'choose_legend' => 'Choose a category',
        'choose_hint' => 'Select one category, then continue to the filtered events list.',
        'view_button' => 'View events',
        'view_all_link' => 'View all events',
        'selected_label' => 'Selected category',
        'none_available' => 'No event categories have been set up for this community yet.',
    ],

    // ---- Accessible location map / directions --------------------------
    'map' => [
        'title' => 'Event location',
        'caption' => 'Location',
        'intro' => 'Where this event is taking place.',
        'address_label' => 'Address',
        'coordinates_label' => 'Map reference',
        'view_on_map_link' => 'View this location on OpenStreetMap',
        'directions_link' => 'Get directions on OpenStreetMap',
        'static_map_alt' => 'Map showing the location of :title',
        'no_location_heading' => 'No map available',
        'no_location_online' => 'This is an online event, so it has no physical location to map.',
        'no_location_missing' => 'This event does not have map coordinates set.',
        'no_location_address' => 'A written address is shown on the event page.',
    ],

    // ---- Recurring-series occurrence edit with scope -------------------
    'recurring_edit' => [
        'title' => 'Edit a repeating event',
        'caption' => 'Repeating event',
        'intro' => 'This event is part of a repeating series. Choose whether your changes apply to only this date or to all future dates.',
        'details_legend' => 'Event details',
        'title_label' => 'Title',
        'description_label' => 'Description',
        'description_hint' => 'Describe what is happening and what people should bring or expect.',
        'time_legend' => 'Date and time',
        'start_time_label' => 'Start',
        'end_time_label' => 'End (optional)',
        'datetime_hint' => 'For example, 31 3 2026 at 19:30.',
        'place_legend' => 'Place',
        'location_label' => 'Location',
        'scope_legend' => 'Apply your changes to',
        'scope_hint' => 'Choose how widely your changes should apply across the repeating series.',
        'scope_single' => 'Only this date',
        'scope_single_hint' => 'Edit just this occurrence. It is detached from the series so future dates are not affected.',
        'scope_all' => 'This and all future dates',
        'scope_all_hint' => 'Apply content changes to this date and every later date in the series. Start and end times are kept per date.',
        'scope_all_warning' => 'Changing all future dates will update every later event in this series.',
        'submit' => 'Save changes',
        'upcoming_heading' => 'Upcoming dates in this series',
        'upcoming_intro' => 'These are the remaining dates in the repeating series.',
        'this_date' => 'This date',
        'view_date_link' => 'View this date',
    ],

    // ---- Attach / detach polls to an owned event -----------------------
    'polls' => [
        'title' => 'Polls for this event',
        'caption' => 'Polls',
        'intro' => 'Attach polls you have created to this event, or remove them. Attendees can vote on the event page.',
        'choose_legend' => 'Your polls',
        'choose_hint' => 'Tick the polls you want shown on this event. Untick to remove a poll.',
        'attached_tag' => 'Attached',
        'save_button' => 'Save poll selection',
        'none_heading' => 'You have no polls yet',
        'none_body' => 'Create a poll first, then come back to attach it to this event.',
        'event_label' => 'Event',
        'updated' => 'Your poll selection has been saved.',
        'failed' => 'Your poll selection could not be saved. Please try again.',
    ],

    // ---- On-demand description translation -----------------------------
    'translate' => [
        'title' => 'Translate event description',
        'caption' => 'Translate',
        'intro' => 'Translate this event description into another language. Translations are provided automatically and may not be perfect.',
        'language_label' => 'Translate into',
        'language_hint' => 'Choose the language you want to read the description in.',
        'translate_button' => 'Translate description',
        'original_heading' => 'Original description',
        'translated_heading' => 'Translated description',
        'machine_note' => 'This translation was produced automatically.',
        'empty' => 'This event has no description to translate.',
        'same' => 'The description is already in the language you chose.',
        'unavailable' => 'Automatic translation is not available for this community at the moment.',
        'failed' => 'The description could not be translated. Please try again later.',
    ],
];
