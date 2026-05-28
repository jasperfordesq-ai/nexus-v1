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

interface PresentMarketplacePaymentOptions {
  clientSecret: string;
  merchantDisplayName: string;
}

export async function presentMarketplacePayment(_options: PresentMarketplacePaymentOptions): Promise<PaymentResult> {
  await Linking.openURL(`${APP_URL}/marketplace/orders`);
  return { status: 'redirected' };
}
