// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Validation utilities for the NEXUS React frontend.
 *
 * Password policy (aligned with NIST SP 800-63B, 2026 rewrite):
 *   - 12 character minimum (length is the primary security signal).
 *   - NO mandatory character classes — they push users to predictable
 *     patterns and don't actually slow down attackers.
 *   - Real defence is a Have I Been Pwned check (k-anonymity) run live
 *     via the usePasswordCheck() hook. See src/hooks/usePasswordCheck.ts.
 *
 * Server-side mirror lives in:
 *   - V1 PHP: app/Services/RegistrationService.php +
 *     app/Http/Controllers/Api/PasswordResetController.php
 *   - V2 .NET: src/Nexus.Api/Controllers/AuthController.cs
 */

export const PASSWORD_MIN_LENGTH = 12;

export interface PasswordRequirement {
  id: string;
  label: string;
  test: (password: string) => boolean;
}

/**
 * Single requirement: length. The live HIBP check that gates submission
 * lives in usePasswordCheck() — it isn't a synchronous boolean, so it's
 * intentionally not in this list.
 */
export const PASSWORD_REQUIREMENTS: PasswordRequirement[] = [
  {
    id: 'length',
    label: 'auth.password_requirements.length',
    test: (p) => p.length >= PASSWORD_MIN_LENGTH,
  },
];

/**
 * Synchronous password validation — length only. The live HIBP check is
 * asynchronous and gates submission separately (see usePasswordCheck).
 */
export function validatePassword(password: string): string[] {
  return password.length >= PASSWORD_MIN_LENGTH ? [] : ['auth.password_requirements.length'];
}

/**
 * Synchronous "minimum bar" check — used by the form to enable the
 * password-strength UI. Full acceptability also requires the HIBP check
 * (handled by usePasswordCheck.isAcceptable).
 */
export function isPasswordValid(password: string): boolean {
  return password.length >= PASSWORD_MIN_LENGTH;
}

/**
 * Length-based strength indicator (0–100). Used for the visual progress
 * bar in the password field. Caps at 100 once the minimum is exceeded by
 * a healthy margin (20+ chars).
 */
export function getPasswordStrength(password: string): number {
  if (!password) return 0;
  const len = password.length;
  if (len >= 20) return 100;
  if (len >= PASSWORD_MIN_LENGTH) return 70 + Math.round(((len - PASSWORD_MIN_LENGTH) / (20 - PASSWORD_MIN_LENGTH)) * 30);
  return Math.round((len / PASSWORD_MIN_LENGTH) * 60);
}

export function getPasswordStrengthLevel(
  password: string,
): 'weak' | 'fair' | 'good' | 'strong' {
  const s = getPasswordStrength(password);
  if (s < 40) return 'weak';
  if (s < 60) return 'fair';
  if (s < 90) return 'good';
  return 'strong';
}

// ─────────────────────────────────────────────────────────────────────────────
// Email Validation
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Validate an email address format
 *
 * @param email - The email to validate
 * @returns true if email format is valid
 */
export function isEmailValid(email: string): boolean {
  // Simple but effective email regex
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
}

/**
 * Validate email and return error message if invalid
 *
 * @param email - The email to validate
 * @returns Error message or null if valid
 */
export function validateEmail(email: string): string | null {
  if (!email || !email.trim()) {
    return 'Email is required';
  }

  if (!isEmailValid(email)) {
    return 'Please enter a valid email address';
  }

  return null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Phone Validation (international format)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Validate a phone number in E.164 format: starts with +, followed by 7–15 digits.
 * Empty string is considered valid (phone is optional).
 *
 * @param phone - The phone number to validate
 * @returns true if phone is valid E.164 or empty
 */
export function isPhoneValid(phone: string): boolean {
  if (!phone || !phone.trim()) return true; // optional field
  const trimmed = phone.trim();
  if (!/^\+[0-9\s().-]+$/.test(trimmed)) return false;

  const digits = trimmed.replace(/\D/g, '');
  return digits.length >= 7 && digits.length <= 15 && digits[0] !== '0';
}

/**
 * Validate a phone number and return an error message if invalid.
 *
 * @param phone - The phone number to validate
 * @returns Error message or null if valid
 */
export function validatePhone(phone: string): string | null {
  if (!phone || !phone.trim()) return null; // optional
  if (!isPhoneValid(phone)) {
    return 'Phone must be in international format (e.g. +1 555 123 4567)';
  }
  return null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Name Validation
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Validate a name field
 *
 * @param name - The name to validate
 * @param fieldName - The field name for error messages (e.g., 'First name')
 * @returns Error message or null if valid
 */
export function validateName(name: string, fieldName: string): string | null {
  if (!name || !name.trim()) {
    return `${fieldName} is required`;
  }

  if (name.trim().length < 2) {
    return `${fieldName} must be at least 2 characters`;
  }

  if (name.trim().length > 50) {
    return `${fieldName} must be less than 50 characters`;
  }

  return null;
}
