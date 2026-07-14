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
 * `vetting_type_required` values are controlled attestation codes. They never
 * represent member self-declarations or certificate records; an automated
 * contact gate can be activated only when the tenant's explicit jurisdiction
 * policy supports the selected code.
 */

return [
    'ireland' => [
        'name' => 'safeguarding.presets.ireland.name',
        'vetting_authority' => 'safeguarding.presets.ireland.vetting_authority',
        'help_text' => 'safeguarding.presets.common.help_text',
        'options' => [
            // ── VULNERABLE ADULT SELF-IDENTIFICATION (flagging) ──────────
            [
                'option_key' => 'is_vulnerable_adult',
                'option_type' => 'checkbox',
                'label' => 'safeguarding.presets.common.options.is_vulnerable_adult.label',
                'description' => 'safeguarding.presets.common.options.is_vulnerable_adult.description',
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
                'label' => 'safeguarding.presets.common.options.requires_vetted_partners.label',
                'description' => 'safeguarding.presets.ireland.options.requires_vetted_partners.description',
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
                'label' => 'safeguarding.presets.common.options.requires_coordinator_contact.label',
                'description' => 'safeguarding.presets.ireland.options.requires_coordinator_contact.description',
                'triggers' => [
                    'requires_broker_approval' => true,
                    'restricts_messaging' => true,
                    'notify_admin_on_selection' => true,
                ],
            ],
            [
                'option_key' => 'no_home_visits',
                'option_type' => 'checkbox',
                'label' => 'safeguarding.presets.common.options.no_home_visits.label',
                'description' => 'safeguarding.presets.common.options.no_home_visits.description',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                ],
            ],

            // ── SERVICE PROVIDER DECLARATIONS (informational) ────────────
            [
                'option_key' => 'works_with_children',
                'option_type' => 'checkbox',
                'label' => 'safeguarding.presets.common.options.works_with_children.label',
                'description' => 'safeguarding.presets.ireland.options.works_with_children.description',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'garda_vetting',
                ],
            ],
            [
                'option_key' => 'works_with_vulnerable_adults',
                'option_type' => 'checkbox',
                'label' => 'safeguarding.presets.common.options.works_with_vulnerable_adults.label',
                'description' => 'safeguarding.presets.ireland.options.works_with_vulnerable_adults.description',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'garda_vetting',
                ],
            ],
            // ── DECLINATION (always last) ─────────────────────────────────
            [
                'option_key' => 'none_apply',
                'option_type' => 'checkbox',
                'label' => 'safeguarding.presets.common.options.none_apply.label',
                'description' => 'safeguarding.presets.common.options.none_apply.description',
                'triggers' => [],
            ],
        ],
    ],

    'united_kingdom' => [
        'name' => 'safeguarding.presets.united_kingdom.name',
        'vetting_authority' => 'safeguarding.presets.united_kingdom.vetting_authority',
        'help_text' => 'safeguarding.presets.common.help_text',
        'options' => [
            [
                'option_key' => 'is_vulnerable_adult',
                'option_type' => 'checkbox',
                'label' => 'safeguarding.presets.common.options.is_vulnerable_adult.label',
                'description' => 'safeguarding.presets.common.options.is_vulnerable_adult.description',
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
                'label' => 'safeguarding.presets.common.options.requires_vetted_partners.label',
                'description' => 'safeguarding.presets.united_kingdom.options.requires_vetted_partners.description',
                'triggers' => [
                    'requires_vetted_interaction' => true,
                    'restricts_matching' => true,
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'uk_safeguarding_clearance',
                ],
            ],
            [
                'option_key' => 'requires_coordinator_contact',
                'option_type' => 'checkbox',
                'label' => 'safeguarding.presets.common.options.requires_coordinator_contact.label',
                'description' => 'safeguarding.presets.common.options.requires_coordinator_contact.description',
                'triggers' => [
                    'requires_broker_approval' => true,
                    'restricts_messaging' => true,
                    'notify_admin_on_selection' => true,
                ],
            ],
            [
                'option_key' => 'no_home_visits',
                'option_type' => 'checkbox',
                'label' => 'safeguarding.presets.common.options.no_home_visits.label',
                'description' => 'safeguarding.presets.common.options.no_home_visits.description',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                ],
            ],
            [
                'option_key' => 'works_with_children',
                'option_type' => 'checkbox',
                'label' => 'safeguarding.presets.common.options.works_with_children.label',
                'description' => 'safeguarding.presets.united_kingdom.options.works_with_children.description',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'uk_safeguarding_clearance',
                ],
            ],
            [
                'option_key' => 'works_with_vulnerable_adults',
                'option_type' => 'checkbox',
                'label' => 'safeguarding.presets.common.options.works_with_vulnerable_adults.label',
                'description' => 'safeguarding.presets.united_kingdom.options.works_with_vulnerable_adults.description',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'uk_safeguarding_clearance',
                ],
            ],
            [
                'option_key' => 'none_apply',
                'option_type' => 'checkbox',
                'label' => 'safeguarding.presets.common.options.none_apply.label',
                'description' => 'safeguarding.presets.common.options.none_apply.description',
                'triggers' => [],
            ],
        ],
    ],

    'england_wales' => [
        'name' => 'safeguarding.presets.england_wales.name',
        'vetting_authority' => 'safeguarding.presets.england_wales.vetting_authority',
        'help_text' => 'safeguarding.presets.common.help_text',
        'options' => [
            // ── VULNERABLE ADULT SELF-IDENTIFICATION (flagging) ──────────
            [
                'option_key' => 'is_vulnerable_adult',
                'option_type' => 'checkbox',
                'label' => 'safeguarding.presets.common.options.is_vulnerable_adult.label',
                'description' => 'safeguarding.presets.common.options.is_vulnerable_adult.description',
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
                'label' => 'safeguarding.presets.common.options.requires_vetted_partners.label',
                'description' => 'safeguarding.presets.england_wales.options.requires_vetted_partners.description',
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
                'label' => 'safeguarding.presets.common.options.requires_coordinator_contact.label',
                'description' => 'safeguarding.presets.common.options.requires_coordinator_contact.description',
                'triggers' => [
                    'requires_broker_approval' => true,
                    'restricts_messaging' => true,
                    'notify_admin_on_selection' => true,
                ],
            ],
            [
                'option_key' => 'no_home_visits',
                'option_type' => 'checkbox',
                'label' => 'safeguarding.presets.common.options.no_home_visits.label',
                'description' => 'safeguarding.presets.common.options.no_home_visits.description',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                ],
            ],

            // ── SERVICE PROVIDER DECLARATIONS (informational) ────────────
            [
                'option_key' => 'works_with_children',
                'option_type' => 'checkbox',
                'label' => 'safeguarding.presets.common.options.works_with_children.label',
                'description' => 'safeguarding.presets.england_wales.options.works_with_children.description',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'dbs_enhanced',
                ],
            ],
            [
                'option_key' => 'works_with_vulnerable_adults',
                'option_type' => 'checkbox',
                'label' => 'safeguarding.presets.common.options.works_with_vulnerable_adults.label',
                'description' => 'safeguarding.presets.england_wales.options.works_with_children.description',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'dbs_enhanced',
                ],
            ],
            // ── DECLINATION (always last) ─────────────────────────────────
            [
                'option_key' => 'none_apply',
                'option_type' => 'checkbox',
                'label' => 'safeguarding.presets.common.options.none_apply.label',
                'description' => 'safeguarding.presets.common.options.none_apply.description',
                'triggers' => [],
            ],
        ],
    ],

    'scotland' => [
        'name' => 'safeguarding.presets.scotland.name',
        'vetting_authority' => 'safeguarding.presets.scotland.vetting_authority',
        'help_text' => 'safeguarding.presets.common.help_text',
        'options' => [
            // ── VULNERABLE ADULT SELF-IDENTIFICATION (flagging) ──────────
            [
                'option_key' => 'is_vulnerable_adult',
                'option_type' => 'checkbox',
                'label' => 'safeguarding.presets.scotland.options.is_vulnerable_adult.label',
                'description' => 'safeguarding.presets.common.options.is_vulnerable_adult.description',
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
                'label' => 'safeguarding.presets.common.options.requires_vetted_partners.label',
                'description' => 'safeguarding.presets.scotland.options.requires_vetted_partners.description',
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
                'label' => 'safeguarding.presets.common.options.requires_coordinator_contact.label',
                'description' => 'safeguarding.presets.common.options.requires_coordinator_contact.description',
                'triggers' => [
                    'requires_broker_approval' => true,
                    'restricts_messaging' => true,
                    'notify_admin_on_selection' => true,
                ],
            ],
            [
                'option_key' => 'no_home_visits',
                'option_type' => 'checkbox',
                'label' => 'safeguarding.presets.common.options.no_home_visits.label',
                'description' => 'safeguarding.presets.common.options.no_home_visits.description',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                ],
            ],

            // ── SERVICE PROVIDER DECLARATIONS (informational) ────────────
            [
                'option_key' => 'works_with_children',
                'option_type' => 'checkbox',
                'label' => 'safeguarding.presets.common.options.works_with_children.label',
                'description' => 'safeguarding.presets.scotland.options.works_with_children.description',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'pvg_scotland',
                ],
            ],
            [
                'option_key' => 'works_with_vulnerable_adults',
                'option_type' => 'checkbox',
                'label' => 'safeguarding.presets.scotland.options.works_with_vulnerable_adults.label',
                'description' => 'safeguarding.presets.scotland.options.works_with_children.description',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'pvg_scotland',
                ],
            ],
            // ── DECLINATION (always last) ─────────────────────────────────
            [
                'option_key' => 'none_apply',
                'option_type' => 'checkbox',
                'label' => 'safeguarding.presets.common.options.none_apply.label',
                'description' => 'safeguarding.presets.common.options.none_apply.description',
                'triggers' => [],
            ],
        ],
    ],

    'northern_ireland' => [
        'name' => 'safeguarding.presets.northern_ireland.name',
        'vetting_authority' => 'safeguarding.presets.northern_ireland.vetting_authority',
        'help_text' => 'safeguarding.presets.common.help_text',
        'options' => [
            // ── VULNERABLE ADULT SELF-IDENTIFICATION (flagging) ──────────
            [
                'option_key' => 'is_vulnerable_adult',
                'option_type' => 'checkbox',
                'label' => 'safeguarding.presets.common.options.is_vulnerable_adult.label',
                'description' => 'safeguarding.presets.common.options.is_vulnerable_adult.description',
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
                'label' => 'safeguarding.presets.common.options.requires_vetted_partners.label',
                'description' => 'safeguarding.presets.northern_ireland.options.requires_vetted_partners.description',
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
                'label' => 'safeguarding.presets.common.options.requires_coordinator_contact.label',
                'description' => 'safeguarding.presets.common.options.requires_coordinator_contact.description',
                'triggers' => [
                    'requires_broker_approval' => true,
                    'restricts_messaging' => true,
                    'notify_admin_on_selection' => true,
                ],
            ],
            [
                'option_key' => 'no_home_visits',
                'option_type' => 'checkbox',
                'label' => 'safeguarding.presets.common.options.no_home_visits.label',
                'description' => 'safeguarding.presets.common.options.no_home_visits.description',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                ],
            ],

            // ── SERVICE PROVIDER DECLARATIONS (informational) ────────────
            [
                'option_key' => 'works_with_children',
                'option_type' => 'checkbox',
                'label' => 'safeguarding.presets.common.options.works_with_children.label',
                'description' => 'safeguarding.presets.northern_ireland.options.works_with_children.description',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'access_ni',
                ],
            ],
            [
                'option_key' => 'works_with_vulnerable_adults',
                'option_type' => 'checkbox',
                'label' => 'safeguarding.presets.common.options.works_with_vulnerable_adults.label',
                'description' => 'safeguarding.presets.northern_ireland.options.works_with_children.description',
                'triggers' => [
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'access_ni',
                ],
            ],
            // ── DECLINATION (always last) ─────────────────────────────────
            [
                'option_key' => 'none_apply',
                'option_type' => 'checkbox',
                'label' => 'safeguarding.presets.common.options.none_apply.label',
                'description' => 'safeguarding.presets.common.options.none_apply.description',
                'triggers' => [],
            ],
        ],
    ],

    'custom' => [
        'name' => 'safeguarding.presets.custom.name',
        'vetting_authority' => null,
        'help_text' => null,
        'options' => [],
    ],
];
