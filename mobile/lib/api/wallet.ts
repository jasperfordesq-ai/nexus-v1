// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

// ─── Types ───────────────────────────────────────────────────────────────────

export interface TransactionOtherUser {
  id: number;
  name: string;
  avatar_url: string | null;
}

/**
 * A single wallet transaction as returned by GET /api/v2/wallet/transactions.
 *
 * - type 'credit'  → the current user *received* time credits
 * - type 'debit'   → the current user *sent* time credits
 */
export interface TransactionItem {
  id: number;
  type: 'credit' | 'debit';
  amount: number;
  description: string | null;
  other_user: TransactionOtherUser;
  created_at: string;
  status: string;
  transaction_type: string;
  category_id: number | null;
}

export interface WalletTransactionsResponse {
  data: TransactionItem[];
  meta: {
    per_page: number;
    has_more: boolean;
    cursor?: string;
  };
}

export interface WalletBalance {
  balance: number;
  total_credits: number;
  total_debits: number;
  currency: string;
}

export interface WalletBalanceResponse {
  data: WalletBalance;
}

// ─── API Functions ────────────────────────────────────────────────────────────

/**
 * GET /api/v2/wallet/balance
 * Returns the current user's time-credit balance with lifetime stats.
 */
export function getWalletBalance(): Promise<WalletBalanceResponse> {
  return api.get<WalletBalanceResponse>(`${API_V2}/wallet/balance`);
}

/**
 * GET /api/v2/wallet/transactions
 * Returns cursor-paginated transaction history for the current user.
 *
 * @param cursor  Opaque cursor string from the previous page's meta
 * @param perPage Number of items per page (default 20, max 100)
 * @param type    Filter: 'all' | 'sent' | 'received'
 */
export function getWalletTransactions(
  cursor?: string,
  perPage = 20,
  type: 'all' | 'sent' | 'received' = 'all',
): Promise<WalletTransactionsResponse> {
  const params: Record<string, string> = {
    per_page: String(perPage),
    type,
  };
  if (cursor) {
    params.cursor = cursor;
  }
  return api.get<WalletTransactionsResponse>(`${API_V2}/wallet/transactions`, params);
}
