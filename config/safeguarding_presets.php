<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Country/jurisdiction safeguarding presets.
 *
 * STRUCTURE: Each preset has TWO sections of options:
 *
 *   1. VULNERABLE ADULT SELF-IDENTIFICATION (priority — these flag the member)
 *      Options for members who consider themselves vulnerable and need
 *      safeguarded/mediated interactions. These trigger broker protections.
 *
 *   2. SERVICE PROVIDER DECLARATIONS (secondary — informational)
 *      Options for members who intend to provide services to vulnerable groups.
 *      Country-specific vetting terminology. Mainly informational for coordinators.
 *
 * When an admin selects a preset, it populates tenant_safeguarding_options rows.
 * The admin can then freely edit, add, or remove options. The preset is a
 * convenience template — no conditional logic checks which preset was used.
 *
 * Presets align with the existing vetting_records.vetting_type enum:
 * dbs_basic, dbs_standard, dbs_enhanced, garda_vetting, access_ni, pvg_scotland, international, other
 */

return [
    'ireland' => [
        'name' => 'Ireland',
        'vetting_authority' => 'National Vetting Bureau',
        'help_text' => 'This community takes safeguarding seriously. If you consider yourself a vulnerable adult or need additional support, please let us know so our coordinators can help arrange safe exchanges for you.',
        'options' => [
            // ── VULNERABLE ADULT SELF-IDENTIFICATION (flagging) ──────────
            [
                'option_key' => 'is_vulnerable_adult',
                'option_type' => 'checkbox',
                'label' => 'I consider myself a vulnerable adult and may need additional safeguarding support',
                'description' => 'This lets our coordinators know you may need extra support when arranging exchanges. A coordinator will be in touch to discuss how we can help. This information is confidential.',
                'triggers' => [
                    'requires_broker_approval' => true,
                    'restricts_messaging' => true,
                    'restricts_matching' => true,
                    'notify_admin_on_selection' => true,
                ],
            ],
            [
                'option_key' => 'requires_vetted_partners',
                'option_type' => 'checkbox',
                'label' => 'I would prefer to only interact with members who have been appropriately vetted',
                'description' => 'In Ireland, this means Garda Vetted members. Our coordinators will ensure you are only matched with vetted members.',
                'triggers' => [
                    'requires_vetted_interaction' => true,
                    'restricts_matching' => true,
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'garda_vetting',
                ],
            ],
            [
                'option_key' => 'requires_coordinator_contact',
                'option_type' => 'checkbox',
                'label' => 'I would like a coordinator to help arrange my exchanges rather than being contacted directly',
                'description' => 'A coordinator (broker) will mediate all contact and help arrange exchanges on your behalf. Other members will not be able to message you directly.',
                'triggers' => [
                    'requires_broker_approval' => true,
                    'restricts_messaging' => true,
                    'notify_admin_on_selection' => true,
                ],
            ],
            [
                'option_key' => 'no_home_visits',
                'option_type' => 'checkbox',
                'label' => 'I do not want members visiting my home without coordinator arrangement',
                'description' => 'All home visits will be arranged through a coordinator who can ensure appropriate safeguards are in place.',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                ],
            ],

            // ── SERVICE PROVIDER DECLARATIONS (informational) ────────────
            [
                'option_key' => 'works_with_children',
                'option_type' => 'checkbox',
                'label' => 'I plan to offer services that may involve children or young people (under 18)',
                'description' => 'A coordinator may discuss Garda Vetting requirements with you. In Ireland, certain activities involving children require vetting under the National Vetting Bureau Act 2012.',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'garda_vetting',
                ],
            ],
            [
                'option_key' => 'works_with_vulnerable_adults',
                'option_type' => 'checkbox',
                'label' => 'I plan to offer services that may involve vulnerable adults',
                'description' => 'A coordinator may discuss Garda Vetting requirements with you. Activities involving vulnerable adults may require vetting.',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'garda_vetting',
                ],
            ],
            [
                'option_key' => 'has_vetting',
                'option_type' => 'checkbox',
                'label' => 'I have current Garda Vetting (NVB)',
                'description' => 'National Vetting Bureau disclosure. You can upload proof in your profile settings.',
                'triggers' => [],
            ],
        ],
    ],

    'england_wales' => [
        'name' => 'England & Wales',
        'vetting_authority' => 'Disclosure and Barring Service',
        'help_text' => 'This community takes safeguarding seriously. If you consider yourself a vulnerable adult or need additional support, please let us know so our coordinators can help arrange safe exchanges for you.',
        'options' => [
            // ── VULNERABLE ADULT SELF-IDENTIFICATION (flagging) ──────────
            [
                'option_key' => 'is_vulnerable_adult',
                'option_type' => 'checkbox',
                'label' => 'I consider myself a vulnerable adult and may need additional safeguarding support',
                'description' => 'This lets our coordinators know you may need extra support when arranging exchanges. A coordinator will be in touch to discuss how we can help. This information is confidential.',
                'triggers' => [
                    'requires_broker_approval' => true,
                    'restricts_messaging' => true,
                    'restricts_matching' => true,
                    'notify_admin_on_selection' => true,
                ],
            ],
            [
                'option_key' => 'requires_vetted_partners',
                'option_type' => 'checkbox',
                'label' => 'I would prefer to only interact with members who have been appropriately vetted',
                'description' => 'In England & Wales, this means DBS-checked members. Our coordinators will ensure you are only matched with vetted members.',
                'triggers' => [
                    'requires_vetted_interaction' => true,
                    'restricts_matching' => true,
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'dbs_enhanced',
                ],
            ],
            [
                'option_key' => 'requires_coordinator_contact',
                'option_type' => 'checkbox',
                'label' => 'I would like a coordinator to help arrange my exchanges rather than being contacted directly',
                'description' => 'A coordinator will mediate all contact and help arrange exchanges on your behalf. Other members will not be able to message you directly.',
                'triggers' => [
                    'requires_broker_approval' => true,
                    'restricts_messaging' => true,
                    'notify_admin_on_selection' => true,
                ],
            ],
            [
                'option_key' => 'no_home_visits',
                'option_type' => 'checkbox',
                'label' => 'I do not want members visiting my home without coordinator arrangement',
                'description' => 'All home visits will be arranged through a coordinator who can ensure appropriate safeguards are in place.',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                ],
            ],

            // ── SERVICE PROVIDER DECLARATIONS (informational) ────────────
            [
                'option_key' => 'works_with_children',
                'option_type' => 'checkbox',
                'label' => 'I plan to offer services that may involve children or young people (under 18)',
                'description' => 'A coordinator may discuss DBS check requirements with you.',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'dbs_enhanced',
                ],
            ],
            [
                'option_key' => 'works_with_vulnerable_adults',
                'option_type' => 'checkbox',
                'label' => 'I plan to offer services that may involve vulnerable adults',
                'description' => 'A coordinator may discuss DBS check requirements with you.',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'dbs_enhanced',
                ],
            ],
            [
                'option_key' => 'has_vetting',
                'option_type' => 'checkbox',
                'label' => 'I have a current DBS check',
                'description' => 'Disclosure and Barring Service check. You can upload proof in your profile settings.',
                'triggers' => [],
            ],
        ],
    ],

    'scotland' => [
        'name' => 'Scotland',
        'vetting_authority' => 'Disclosure Scotland (PVG Scheme)',
        'help_text' => 'This community takes safeguarding seriously. If you consider yourself a vulnerable adult or need additional support, please let us know so our coordinators can help arrange safe exchanges for you.',
        'options' => [
            // ── VULNERABLE ADULT SELF-IDENTIFICATION (flagging) ──────────
            [
                'option_key' => 'is_vulnerable_adult',
                'option_type' => 'checkbox',
                'label' => 'I consider myself a vulnerable or protected adult and may need additional safeguarding support',
                'description' => 'This lets our coordinators know you may need extra support when arranging exchanges. A coordinator will be in touch to discuss how we can help. This information is confidential.',
                'triggers' => [
                    'requires_broker_approval' => true,
                    'restricts_messaging' => true,
                    'restricts_matching' => true,
                    'notify_admin_on_selection' => true,
                ],
            ],
            [
                'option_key' => 'requires_vetted_partners',
                'option_type' => 'checkbox',
                'label' => 'I would prefer to only interact with members who have been appropriately vetted',
                'description' => 'In Scotland, this means PVG scheme members. Our coordinators will ensure you are only matched with vetted members.',
                'triggers' => [
                    'requires_vetted_interaction' => true,
                    'restricts_matching' => true,
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'pvg_scotland',
                ],
            ],
            [
                'option_key' => 'requires_coordinator_contact',
                'option_type' => 'checkbox',
                'label' => 'I would like a coordinator to help arrange my exchanges rather than being contacted directly',
                'description' => 'A coordinator will mediate all contact and help arrange exchanges on your behalf. Other members will not be able to message you directly.',
                'triggers' => [
                    'requires_broker_approval' => true,
                    'restricts_messaging' => true,
                    'notify_admin_on_selection' => true,
                ],
            ],
            [
                'option_key' => 'no_home_visits',
                'option_type' => 'checkbox',
                'label' => 'I do not want members visiting my home without coordinator arrangement',
                'description' => 'All home visits will be arranged through a coordinator who can ensure appropriate safeguards are in place.',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                ],
            ],

            // ── SERVICE PROVIDER DECLARATIONS (informational) ────────────
            [
                'option_key' => 'works_with_children',
                'option_type' => 'checkbox',
                'label' => 'I plan to offer services that may involve children or young people (under 18)',
                'description' => 'A coordinator may discuss PVG scheme membership with you.',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'pvg_scotland',
                ],
            ],
            [
                'option_key' => 'works_with_vulnerable_adults',
                'option_type' => 'checkbox',
                'label' => 'I plan to offer services that may involve protected adults',
                'description' => 'A coordinator may discuss PVG scheme membership with you.',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'pvg_scotland',
                ],
            ],
            [
                'option_key' => 'has_vetting',
                'option_type' => 'checkbox',
                'label' => 'I am a PVG scheme member',
                'description' => 'Protecting Vulnerable Groups scheme via Disclosure Scotland. You can upload proof in your profile settings.',
                'triggers' => [],
            ],
        ],
    ],

    'northern_ireland' => [
        'name' => 'Northern Ireland',
        'vetting_authority' => 'AccessNI',
        'help_text' => 'This community takes safeguarding seriously. If you consider yourself a vulnerable adult or need additional support, please let us know so our coordinators can help arrange safe exchanges for you.',
        'options' => [
            // ── VULNERABLE ADULT SELF-IDENTIFICATION (flagging) ──────────
            [
                'option_key' => 'is_vulnerable_adult',
                'option_type' => 'checkbox',
                'label' => 'I consider myself a vulnerable adult and may need additional safeguarding support',
                'description' => 'This lets our coordinators know you may need extra support when arranging exchanges. A coordinator will be in touch to discuss how we can help. This information is confidential.',
                'triggers' => [
                    'requires_broker_approval' => true,
                    'restricts_messaging' => true,
                    'restricts_matching' => true,
                    'notify_admin_on_selection' => true,
                ],
            ],
            [
                'option_key' => 'requires_vetted_partners',
                'option_type' => 'checkbox',
                'label' => 'I would prefer to only interact with members who have been appropriately vetted',
                'description' => 'In Northern Ireland, this means AccessNI-checked members. Our coordinators will ensure you are only matched with vetted members.',
                'triggers' => [
                    'requires_vetted_interaction' => true,
                    'restricts_matching' => true,
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'access_ni',
                ],
            ],
            [
                'option_key' => 'requires_coordinator_contact',
                'option_type' => 'checkbox',
                'label' => 'I would like a coordinator to help arrange my exchanges rather than being contacted directly',
                'description' => 'A coordinator will mediate all contact and help arrange exchanges on your behalf. Other members will not be able to message you directly.',
                'triggers' => [
                    'requires_broker_approval' => true,
                    'restricts_messaging' => true,
                    'notify_admin_on_selection' => true,
                ],
            ],
            [
                'option_key' => 'no_home_visits',
                'option_type' => 'checkbox',
                'label' => 'I do not want members visiting my home without coordinator arrangement',
                'description' => 'All home visits will be arranged through a coordinator who can ensure appropriate safeguards are in place.',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                ],
            ],

            // ── SERVICE PROVIDER DECLARATIONS (informational) ────────────
            [
                'option_key' => 'works_with_children',
                'option_type' => 'checkbox',
                'label' => 'I plan to offer services that may involve children or young people (under 18)',
                'description' => 'A coordinator may discuss AccessNI checking with you.',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'access_ni',
                ],
            ],
            [
                'option_key' => 'works_with_vulnerable_adults',
                'option_type' => 'checkbox',
                'label' => 'I plan to offer services that may involve vulnerable adults',
                'description' => 'A coordinator may discuss AccessNI checking with you.',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'access_ni',
                ],
            ],
            [
                'option_key' => 'has_vetting',
                'option_type' => 'checkbox',
                'label' => 'I have a current AccessNI check',
                'description' => 'AccessNI criminal record check. You can upload proof in your profile settings.',
                'triggers' => [],
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
