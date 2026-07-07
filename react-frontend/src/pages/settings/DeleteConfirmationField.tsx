// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState } from 'react';
import { Input } from '@/components/ui/Input';

export interface DeleteConfirmationFieldProps {
  /** Controlled value of the confirmation text. */
  value: string;
  /** Called with the new value as the user types. */
  onValueChange: (value: string) => void;
  /** Placeholder / keyword the user must type (localized). */
  placeholder: string;
  /** Accessible label. */
  ariaLabel: string;
  /**
   * Changes whenever the containing modal (re)opens. The field re-arms its
   * read-only autofill guard on every change, so a modal kept mounted between
   * opens is still protected on the next open.
   */
  armKey?: unknown;
}

/**
 * Text field for the GDPR delete-account "type the keyword to confirm" gate.
 *
 * The single reason this is its own component: **defeating browser autofill.**
 * The field sits next to a `current-password` input, so Chrome classifies it as
 * the username slot of a login form and autofills the account email into it —
 * ignoring `autoComplete="off"`. An email never matches the keyword, so the
 * delete button stays permanently disabled and the user can't tell where to
 * type. Chrome does NOT autofill **read-only** fields, so the field renders
 * read-only until the user actually interacts with it (pointer or keyboard
 * focus); by then autofill has already been skipped. The value is left clean so
 * the "DELETE" placeholder is visible.
 */
export function DeleteConfirmationField({
  value,
  onValueChange,
  placeholder,
  ariaLabel,
  armKey,
}: DeleteConfirmationFieldProps) {
  const [readOnly, setReadOnly] = useState(true);

  // Re-arm the guard each time the modal opens (armKey changes).
  useEffect(() => {
    setReadOnly(true);
  }, [armKey]);

  const disarm = () => setReadOnly(false);

  return (
    <Input
      value={value}
      onValueChange={onValueChange}
      placeholder={placeholder}
      aria-label={ariaLabel}
      isReadOnly={readOnly}
      onPointerDown={disarm}
      onFocus={disarm}
      // Belt-and-braces autofill hints (Chrome ignores these here, hence the
      // read-only guard above — but other browsers / password managers honour
      // them, and the non-credential name avoids username heuristics).
      name="delete-account-confirmation"
      autoComplete="off"
      autoCapitalize="off"
      autoCorrect="off"
      spellCheck={false}
      classNames={{
        input: 'bg-transparent text-theme-primary font-mono',
        inputWrapper: 'bg-theme-elevated border-theme-default',
      }}
    />
  );
}

export default DeleteConfirmationField;
