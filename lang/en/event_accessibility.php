<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [
    'form' => [
        'title' => 'Venue accessibility',
        'hint' => 'Record what is available at the venue. Choose “Not known” when the organiser has not verified a feature; this is different from “No”.',
        'parking_details' => 'Parking and arrival details',
        'parking_details_hint' => 'Describe accessible bays, drop-off points and the route to the entrance.',
        'transit_details' => 'Public transport details',
        'transit_details_hint' => 'Describe nearby stops, stations and any barriers on the route.',
        'assistance_contact' => 'Accessibility assistance contact',
        'assistance_contact_hint' => 'Give a public contact method for access questions. Do not enter private attendee information.',
        'notes' => 'Additional access information',
        'notes_hint' => 'Include entrance, lift, surface, lighting, sensory or other useful venue information.',
        'privacy_note' => 'This information is shown to event members. Private accommodation requests belong in the registration form, not here.',
    ],
    'features' => [
        'step_free_access' => 'Step-free access',
        'accessible_toilet' => 'Accessible toilet',
        'hearing_loop' => 'Hearing loop',
        'quiet_space' => 'Quiet space',
        'seating_available' => 'Seating available',
        'accessible_parking' => 'Accessible parking',
    ],
    'status' => ['yes' => 'Yes', 'no' => 'No', 'unknown' => 'Not known'],
    'filters' => [
        'step_free_label' => 'Step-free venue access',
        'step_free_hint' => 'Filter by the venue information confirmed by the organiser.',
        'step_free_options' => ['any' => 'Any venue', 'yes' => 'Step-free access confirmed', 'no' => 'Not step-free', 'unknown' => 'Step-free access not known'],
        'step_free_active' => 'Step-free access: :value',
    ],
    'detail' => [
        'title' => 'Venue accessibility',
        'intro' => 'Access information supplied by the event organiser. Contact the organiser if you need to confirm an arrangement.',
        'features_label' => 'Venue accessibility features',
        'parking_details' => 'Parking and arrival',
        'transit_details' => 'Public transport',
        'assistance_contact' => 'Accessibility assistance',
        'notes' => 'Additional access information',
    ],
];
