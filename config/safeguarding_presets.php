<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Country/jurisdiction safeguarding presets.
 *
 * These presets provide default safeguarding options per country. When an admin
 * selects a preset, it populates tenant_safeguarding_options rows. The admin
 * can then freely edit, add, or remove options. The preset is a convenience
 * template — no conditional logic in the app ever checks which preset was used.
 *
 * Presets align with the existing vetting_records.vetting_type enum:
 * dbs_basic, dbs_standard, dbs_enhanced, garda_vetting, access_ni, pvg_scotland, international, other
 */

return [
    'ireland' => [
        'name' => 'Ireland',
        'vetting_authority' => 'National Vetting Bureau',
        'help_text' => 'Under the National Vetting Bureau Act 2012, certain activities involving children or vulnerable adults require Garda Vetting. Most timebanking exchanges do not require vetting unless they constitute a necessary and regular part of relevant work.',
        'options' => [
            [
                'option_key' => 'works_with_children',
                'option_type' => 'checkbox',
                'label' => 'I may work with children or young people (under 18)',
                'description' => 'If selected, a coordinator may discuss Garda Vetting with you before matching you with services involving children.',
                'triggers' => [
                    'requires_vetted_interaction' => true,
                    'restricts_matching' => true,
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'garda_vetting',
                ],
            ],
            [
                'option_key' => 'works_with_vulnerable_adults',
                'option_type' => 'checkbox',
                'label' => 'I may work with vulnerable adults',
                'description' => 'If selected, a coordinator may discuss Garda Vetting with you before matching you with services involving vulnerable adults.',
                'triggers' => [
                    'requires_vetted_interaction' => true,
                    'restricts_matching' => true,
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'garda_vetting',
                ],
            ],
            [
                'option_key' => 'requires_home_visits',
                'option_type' => 'checkbox',
                'label' => 'My services involve visiting people at home',
                'description' => 'Home visits may require additional safeguarding arrangements. A coordinator will help plan these safely.',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                ],
            ],
            [
                'option_key' => 'has_vetting',
                'option_type' => 'checkbox',
                'label' => 'I have current Garda Vetting (NVB)',
                'description' => 'National Vetting Bureau disclosure. You can upload proof in your profile settings.',
                'triggers' => [],
            ],
            [
                'option_key' => 'needs_support',
                'option_type' => 'checkbox',
                'label' => 'I may need additional support or safeguarding considerations',
                'description' => 'Let us know so a coordinator can help arrange your exchanges safely. This information is confidential and only visible to coordinators.',
                'triggers' => [
                    'requires_broker_approval' => true,
                    'restricts_messaging' => true,
                    'notify_admin_on_selection' => true,
                ],
            ],
        ],
    ],

    'england_wales' => [
        'name' => 'England & Wales',
        'vetting_authority' => 'Disclosure and Barring Service',
        'help_text' => 'DBS checks may be required for regulated activity with children or vulnerable adults. Most timebanking exchanges are personal arrangements and do not constitute regulated activity. It is unlawful to request a DBS check for a non-eligible role.',
        'options' => [
            [
                'option_key' => 'works_with_children',
                'option_type' => 'checkbox',
                'label' => 'I may work with children or young people (under 18)',
                'description' => 'If selected, a coordinator may discuss DBS checking with you before matching you with services involving children.',
                'triggers' => [
                    'requires_vetted_interaction' => true,
                    'restricts_matching' => true,
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'dbs_enhanced',
                ],
            ],
            [
                'option_key' => 'works_with_vulnerable_adults',
                'option_type' => 'checkbox',
                'label' => 'I may work with vulnerable adults',
                'description' => 'If selected, a coordinator may discuss DBS checking with you before matching you with services involving vulnerable adults.',
                'triggers' => [
                    'requires_vetted_interaction' => true,
                    'restricts_matching' => true,
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'dbs_enhanced',
                ],
            ],
            [
                'option_key' => 'requires_home_visits',
                'option_type' => 'checkbox',
                'label' => 'My services involve visiting people at home',
                'description' => 'Home visits may require additional safeguarding arrangements. A coordinator will help plan these safely.',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                ],
            ],
            [
                'option_key' => 'has_vetting',
                'option_type' => 'checkbox',
                'label' => 'I have a current DBS check',
                'description' => 'Disclosure and Barring Service check. You can upload proof in your profile settings.',
                'triggers' => [],
            ],
            [
                'option_key' => 'needs_support',
                'option_type' => 'checkbox',
                'label' => 'I may need additional support or safeguarding considerations',
                'description' => 'Let us know so a coordinator can help arrange your exchanges safely. This information is confidential and only visible to coordinators.',
                'triggers' => [
                    'requires_broker_approval' => true,
                    'restricts_messaging' => true,
                    'notify_admin_on_selection' => true,
                ],
            ],
        ],
    ],

    'scotland' => [
        'name' => 'Scotland',
        'vetting_authority' => 'Disclosure Scotland (PVG Scheme)',
        'help_text' => 'The Protecting Vulnerable Groups (PVG) scheme is managed by Disclosure Scotland. PVG membership may be required for regulated work with children or protected adults. Most timebanking exchanges are not regulated work.',
        'options' => [
            [
                'option_key' => 'works_with_children',
                'option_type' => 'checkbox',
                'label' => 'I may work with children or young people (under 18)',
                'description' => 'If selected, a coordinator may discuss PVG scheme membership with you before matching you with services involving children.',
                'triggers' => [
                    'requires_vetted_interaction' => true,
                    'restricts_matching' => true,
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'pvg_scotland',
                ],
            ],
            [
                'option_key' => 'works_with_vulnerable_adults',
                'option_type' => 'checkbox',
                'label' => 'I may work with protected adults',
                'description' => 'If selected, a coordinator may discuss PVG scheme membership with you before matching you with services involving protected adults.',
                'triggers' => [
                    'requires_vetted_interaction' => true,
                    'restricts_matching' => true,
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'pvg_scotland',
                ],
            ],
            [
                'option_key' => 'requires_home_visits',
                'option_type' => 'checkbox',
                'label' => 'My services involve visiting people at home',
                'description' => 'Home visits may require additional safeguarding arrangements. A coordinator will help plan these safely.',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                ],
            ],
            [
                'option_key' => 'has_vetting',
                'option_type' => 'checkbox',
                'label' => 'I am a PVG scheme member',
                'description' => 'Protecting Vulnerable Groups scheme membership via Disclosure Scotland. You can upload proof in your profile settings.',
                'triggers' => [],
            ],
            [
                'option_key' => 'needs_support',
                'option_type' => 'checkbox',
                'label' => 'I may need additional support or safeguarding considerations',
                'description' => 'Let us know so a coordinator can help arrange your exchanges safely. This information is confidential and only visible to coordinators.',
                'triggers' => [
                    'requires_broker_approval' => true,
                    'restricts_messaging' => true,
                    'notify_admin_on_selection' => true,
                ],
            ],
        ],
    ],

    'northern_ireland' => [
        'name' => 'Northern Ireland',
        'vetting_authority' => 'AccessNI',
        'help_text' => 'AccessNI provides criminal record checks in Northern Ireland. Enhanced checks may be required for working with children or vulnerable adults in regulated positions. Most timebanking exchanges are personal arrangements and are not regulated positions.',
        'options' => [
            [
                'option_key' => 'works_with_children',
                'option_type' => 'checkbox',
                'label' => 'I may work with children or young people (under 18)',
                'description' => 'If selected, a coordinator may discuss AccessNI checking with you before matching you with services involving children.',
                'triggers' => [
                    'requires_vetted_interaction' => true,
                    'restricts_matching' => true,
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'access_ni',
                ],
            ],
            [
                'option_key' => 'works_with_vulnerable_adults',
                'option_type' => 'checkbox',
                'label' => 'I may work with vulnerable adults',
                'description' => 'If selected, a coordinator may discuss AccessNI checking with you before matching you with services involving vulnerable adults.',
                'triggers' => [
                    'requires_vetted_interaction' => true,
                    'restricts_matching' => true,
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'access_ni',
                ],
            ],
            [
                'option_key' => 'requires_home_visits',
                'option_type' => 'checkbox',
                'label' => 'My services involve visiting people at home',
                'description' => 'Home visits may require additional safeguarding arrangements. A coordinator will help plan these safely.',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                ],
            ],
            [
                'option_key' => 'has_vetting',
                'option_type' => 'checkbox',
                'label' => 'I have a current AccessNI check',
                'description' => 'AccessNI criminal record check. You can upload proof in your profile settings.',
                'triggers' => [],
            ],
            [
                'option_key' => 'needs_support',
                'option_type' => 'checkbox',
                'label' => 'I may need additional support or safeguarding considerations',
                'description' => 'Let us know so a coordinator can help arrange your exchanges safely. This information is confidential and only visible to coordinators.',
                'triggers' => [
                    'requires_broker_approval' => true,
                    'restricts_messaging' => true,
                    'notify_admin_on_selection' => true,
                ],
            ],
        ],
    ],

    'custom' => [
        'name' => 'Custom',
        'vetting_authority' => '',
        'help_text' => '',
        'options' => [],
    ],
];
