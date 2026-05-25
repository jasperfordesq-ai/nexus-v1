// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for validation utilities
 */

import { describe, it, expect } from 'vitest';
import {
  validatePassword,
  isPasswordValid,
  getPasswordStrength,
  getPasswordStrengthLevel,
  PASSWORD_MIN_LENGTH,
  PASSWORD_REQUIREMENTS,
  isEmailValid,
  validateEmail,
  validateName,
} from './validation';

describe('Password Validation', () => {
  describe('validatePassword', () => {
    it('returns all errors for empty password', () => {
      const errors = validatePassword('');
      expect(errors).toHaveLength(PASSWORD_REQUIREMENTS.length);
    });

    it('returns no errors for a valid password', () => {
      const errors = validatePassword('SecurePass123!');
      expect(errors).toHaveLength(0);
    });

    it('returns error for password under minimum length', () => {
      const errors = validatePassword('Short1!');
      expect(errors).toContain('auth.password_requirements.length');
    });

    it('does not require uppercase characters', () => {
      const errors = validatePassword('lowercase123!');
      expect(errors).toHaveLength(0);
    });

    it('does not require lowercase characters', () => {
      const errors = validatePassword('UPPERCASE123!');
      expect(errors).toHaveLength(0);
    });

    it('does not require numbers', () => {
      const errors = validatePassword('SecurePassword!');
      expect(errors).toHaveLength(0);
    });

    it('does not require special characters', () => {
      const errors = validatePassword('SecurePassword123');
      expect(errors).toHaveLength(0);
    });

    it('accepts underscore as special character', () => {
      const errors = validatePassword('SecurePass123_abc');
      expect(errors).toHaveLength(0);
    });

    it('accepts various special characters', () => {
      const specialChars = ['!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '-', '+', '='];
      specialChars.forEach((char) => {
        const errors = validatePassword(`SecurePass123${char}`);
        expect(errors).toHaveLength(0);
      });
    });
  });

  describe('isPasswordValid', () => {
    it('returns true for valid password', () => {
      expect(isPasswordValid('SecurePass123!')).toBe(true);
    });

    it('returns false for invalid password', () => {
      expect(isPasswordValid('weak')).toBe(false);
    });

    it('returns true for passwords that only meet the length requirement', () => {
      expect(isPasswordValid('securepass123!')).toBe(true);
    });
  });

  describe('getPasswordStrength', () => {
    it('returns 0 for empty password', () => {
      expect(getPasswordStrength('')).toBe(0);
    });

    it('returns a good score for passwords above the minimum length', () => {
      expect(getPasswordStrength('SecurePass123!')).toBe(78);
    });

    it('returns partial score for partially valid password', () => {
      // Just lowercase and numbers
      const strength = getPasswordStrength('password123');
      expect(strength).toBeGreaterThan(0);
      expect(strength).toBeLessThan(100);
    });
  });

  describe('getPasswordStrengthLevel', () => {
    it('returns weak for very short passwords', () => {
      expect(getPasswordStrengthLevel('abc')).toBe('weak');
    });

    it('returns good for passwords above the minimum length', () => {
      expect(getPasswordStrengthLevel('SecurePass123!')).toBe('good');
    });

    it('returns appropriate level based on password length', () => {
      expect(getPasswordStrengthLevel('password')).toBe('fair');
    });
  });

  describe('PASSWORD_MIN_LENGTH', () => {
    it('should be 12 characters (matching backend)', () => {
      expect(PASSWORD_MIN_LENGTH).toBe(12);
    });
  });
});

describe('Email Validation', () => {
  describe('isEmailValid', () => {
    it('returns true for valid email', () => {
      expect(isEmailValid('user@example.com')).toBe(true);
    });

    it('returns true for email with subdomain', () => {
      expect(isEmailValid('user@mail.example.com')).toBe(true);
    });

    it('returns true for email with plus sign', () => {
      expect(isEmailValid('user+tag@example.com')).toBe(true);
    });

    it('returns false for email without @', () => {
      expect(isEmailValid('userexample.com')).toBe(false);
    });

    it('returns false for email without domain', () => {
      expect(isEmailValid('user@')).toBe(false);
    });

    it('returns false for email without TLD', () => {
      expect(isEmailValid('user@example')).toBe(false);
    });

    it('returns false for empty string', () => {
      expect(isEmailValid('')).toBe(false);
    });
  });

  describe('validateEmail', () => {
    it('returns null for valid email', () => {
      expect(validateEmail('user@example.com')).toBeNull();
    });

    it('returns error for empty email', () => {
      expect(validateEmail('')).toBe('Email is required');
    });

    it('returns error for whitespace-only email', () => {
      expect(validateEmail('   ')).toBe('Email is required');
    });

    it('returns error for invalid email format', () => {
      expect(validateEmail('invalid-email')).toBe('Please enter a valid email address');
    });
  });
});

describe('Name Validation', () => {
  describe('validateName', () => {
    it('returns null for valid name', () => {
      expect(validateName('John', 'First name')).toBeNull();
    });

    it('returns error for empty name', () => {
      expect(validateName('', 'First name')).toBe('First name is required');
    });

    it('returns error for whitespace-only name', () => {
      expect(validateName('   ', 'First name')).toBe('First name is required');
    });

    it('returns error for name that is too short', () => {
      expect(validateName('J', 'First name')).toBe('First name must be at least 2 characters');
    });

    it('returns error for name that is too long', () => {
      const longName = 'A'.repeat(51);
      expect(validateName(longName, 'First name')).toBe('First name must be less than 50 characters');
    });

    it('uses correct field name in error message', () => {
      expect(validateName('', 'Last name')).toBe('Last name is required');
    });
  });
});
