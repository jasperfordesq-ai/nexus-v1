// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

export interface VerificationBadge {
  type?: string;
  badge_type?: string;
  label?: string;
  description?: string;
  verified?: boolean;
  verified_at?: string | null;
  granted_at?: string | null;
}

interface ApiEnvelope<T> {
  success?: boolean;
  data?: T;
}

const badgeCache = new Map<number, Promise<VerificationBadge[]>>();

function normalizeBadges(response: ApiEnvelope<VerificationBadge[]> | VerificationBadge[] | null | undefined): VerificationBadge[] {
  const badges = Array.isArray(response) ? response : response?.data;

  return (Array.isArray(badges) ? badges : []).map((badge) => ({
    ...badge,
    type: badge.type || badge.badge_type || '',
    verified_at: badge.verified_at ?? badge.granted_at ?? null,
  }));
}

export function clearVerificationBadgeCache(userId?: number): void {
  if (typeof userId === 'number') {
    badgeCache.delete(userId);
    return;
  }
  badgeCache.clear();
}

export function getUserVerificationBadges(userId: number): Promise<VerificationBadge[]> {
  if (!badgeCache.has(userId)) {
    badgeCache.set(
      userId,
      api
        .get<ApiEnvelope<VerificationBadge[]> | VerificationBadge[]>(`${API_V2}/users/${userId}/verification-badges`)
        .then(normalizeBadges)
        .catch((error) => {
          badgeCache.delete(userId);
          throw error;
        }),
    );
  }

  return badgeCache.get(userId)!;
}

export interface IdentityStatus {
  has_id_verified_badge: boolean;
  user_has_dob: boolean;
  fee_cents: number;
  fee_currency: string;
  payment_completed: boolean;
  verification_status: string | null;
  latest_session?: {
    id: number | string;
    status: string;
    provider?: string | null;
    created_at?: string | null;
    failure_reason?: string | null;
  } | null;
}

export interface StartIdentityVerificationResponse {
  session_id?: number | string;
  redirect_url?: string | null;
  client_token?: string | null;
  provider?: string | null;
  expires_at?: string | null;
  status?: string;
  already_verified?: boolean;
}

export interface IdentityPaymentResponse {
  client_secret?: string;
  payment_intent_id?: string;
  publishable_key?: string;
  fee_cents?: number;
  fee_currency?: string;
  payment_required?: boolean;
  already_paid?: boolean;
}

export function getIdentityStatus(): Promise<ApiEnvelope<IdentityStatus>> {
  return api.get<ApiEnvelope<IdentityStatus>>(`${API_V2}/identity/status`);
}

export function saveIdentityDateOfBirth(dateOfBirth: string): Promise<ApiEnvelope<{ date_of_birth: string }>> {
  return api.post<ApiEnvelope<{ date_of_birth: string }>>(`${API_V2}/identity/save-dob`, {
    date_of_birth: dateOfBirth,
  });
}

export function startIdentityVerification(): Promise<ApiEnvelope<StartIdentityVerificationResponse>> {
  return api.post<ApiEnvelope<StartIdentityVerificationResponse>>(`${API_V2}/identity/start`);
}

export function createIdentityVerificationPayment(): Promise<ApiEnvelope<IdentityPaymentResponse>> {
  return api.post<ApiEnvelope<IdentityPaymentResponse>>(`${API_V2}/identity/create-payment`);
}
