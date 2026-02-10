/**
 * Validation utilities for the NEXUS React frontend
 *
 * Password requirements must match backend (PasswordResetApiController.php):
 * - Minimum 12 characters
 * - At least one uppercase letter
 * - At least one lowercase letter
 * - At least one number
 * - At least one special character
 */

// ─────────────────────────────────────────────────────────────────────────────
// Constants - must match backend PasswordResetApiController
// ─────────────────────────────────────────────────────────────────────────────

export const PASSWORD_MIN_LENGTH = 12;

// ─────────────────────────────────────────────────────────────────────────────
// Password Validation
// ─────────────────────────────────────────────────────────────────────────────

export interface PasswordRequirement {
  id: string;
  label: string;
  test: (password: string) => boolean;
}

export const PASSWORD_REQUIREMENTS: PasswordRequirement[] = [
  {
    id: 'length',
    label: `At least ${PASSWORD_MIN_LENGTH} characters`,
    test: (p) => p.length >= PASSWORD_MIN_LENGTH,
  },
  {
    id: 'uppercase',
    label: 'At least one uppercase letter',
    test: (p) => /[A-Z]/.test(p),
  },
  {
    id: 'lowercase',
    label: 'At least one lowercase letter',
    test: (p) => /[a-z]/.test(p),
  },
  {
    id: 'number',
    label: 'At least one number',
    test: (p) => /[0-9]/.test(p),
  },
  {
    id: 'special',
    label: 'At least one special character',
    test: (p) => /[\W_]/.test(p),
  },
];

/**
 * Validate a password against all requirements
 *
 * @param password - The password to validate
 * @returns Array of error messages (empty if valid)
 */
export function validatePassword(password: string): string[] {
  const errors: string[] = [];

  for (const req of PASSWORD_REQUIREMENTS) {
    if (!req.test(password)) {
      errors.push(req.label);
    }
  }

  return errors;
}

/**
 * Check if a password meets all requirements
 *
 * @param password - The password to check
 * @returns true if password is valid
 */
export function isPasswordValid(password: string): boolean {
  return PASSWORD_REQUIREMENTS.every((req) => req.test(password));
}

/**
 * Get the password strength as a percentage (0-100)
 *
 * @param password - The password to check
 * @returns Strength percentage
 */
export function getPasswordStrength(password: string): number {
  if (!password) return 0;

  const passedCount = PASSWORD_REQUIREMENTS.filter((req) =>
    req.test(password)
  ).length;

  return Math.round((passedCount / PASSWORD_REQUIREMENTS.length) * 100);
}

/**
 * Get password strength level for UI display
 *
 * @param password - The password to check
 * @returns 'weak' | 'fair' | 'good' | 'strong'
 */
export function getPasswordStrengthLevel(
  password: string
): 'weak' | 'fair' | 'good' | 'strong' {
  const strength = getPasswordStrength(password);

  if (strength < 40) return 'weak';
  if (strength < 60) return 'fair';
  if (strength < 100) return 'good';
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
