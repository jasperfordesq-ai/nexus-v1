// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Linking } from 'react-native';

import { APP_URL } from '@/lib/constants';

type PaymentResult =
  | { status: 'completed' }
  | { status: 'canceled' }
  | { status: 'failed'; message?: string }
  | { status: 'redirected' };

interface PresentIdentityPaymentOptions {
  clientSecret: string;
  publishableKey?: string;
  merchantDisplayName: string;
}

export async function presentIdentityPayment(_options: PresentIdentityPaymentOptions): Promise<PaymentResult> {
  await Linking.openURL(`${APP_URL}/settings/verify-identity`);
  return { status: 'redirected' };
}
