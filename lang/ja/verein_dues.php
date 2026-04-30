<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [
    'errors' => [
        'invalid_fee_amount'     => 'Fee amount must be greater than zero.',
        'invalid_billing_cycle'  => 'Billing cycle must be one of: annual, biennial, monthly.',
        'fee_not_configured'     => 'No active membership fee is configured for this Verein.',
        'organization_not_found' => 'Verein not found.',
        'organization_not_club'  => 'This organisation is not a Verein.',
        'organization_required'  => 'organization_id is required.',
        'dues_not_found'         => 'Membership dues record not found.',
        'cannot_waive_paid'      => 'Cannot waive a dues row that has already been paid.',
        'cannot_remind_status'   => 'Reminders can only be sent for pending or overdue dues.',
        'cannot_pay_status'      => 'This dues row is not in a payable state.',
        'payment_intent_failed'  => 'Could not start the payment process. Please try again later.',
        'waive_reason_required'  => 'A waive reason is required.',
    ],
];
