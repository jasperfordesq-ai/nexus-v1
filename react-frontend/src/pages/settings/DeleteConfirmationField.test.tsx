// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
import { describe, it, expect } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { DeleteConfirmationField } from './DeleteConfirmationField';

const LABEL = 'Type DELETE to confirm account deletion';

function Harness({ armKey }: { armKey?: unknown }) {
  const [value, setValue] = useState('');
  return (
    <DeleteConfirmationField
      value={value}
      onValueChange={setValue}
      placeholder="DELETE"
      ariaLabel={LABEL}
      armKey={armKey}
    />
  );
}

describe('DeleteConfirmationField — autofill-proof GDPR delete confirmation', () => {
  it('renders read-only on mount so the browser cannot autofill the account email', () => {
    render(<Harness />);
    const input = screen.getByLabelText(LABEL) as HTMLInputElement;
    // Chrome does not autofill read-only fields — this is the whole guard.
    expect(input.readOnly).toBe(true);
  });

  it('carries belt-and-braces autofill-suppression attributes', () => {
    render(<Harness />);
    const input = screen.getByLabelText(LABEL) as HTMLInputElement;
    expect(input.getAttribute('name')).toBe('delete-account-confirmation');
    expect(input.getAttribute('autocomplete')).toBe('off');
    expect(input.getAttribute('spellcheck')).toBe('false');
  });

  it('becomes editable on focus and forwards typed input', () => {
    render(<Harness />);
    const input = screen.getByLabelText(LABEL) as HTMLInputElement;
    fireEvent.focus(input);
    expect(input.readOnly).toBe(false);
    fireEvent.change(input, { target: { value: 'DELETE' } });
    expect(input.value).toBe('DELETE');
  });

  it('becomes editable on pointer-down (mouse users)', () => {
    render(<Harness />);
    const input = screen.getByLabelText(LABEL) as HTMLInputElement;
    fireEvent.pointerDown(input);
    expect(input.readOnly).toBe(false);
  });

  it('re-arms the read-only guard when the modal re-opens (armKey changes)', () => {
    const { rerender } = render(<Harness armKey={true} />);
    const input = screen.getByLabelText(LABEL) as HTMLInputElement;

    // User interacts → guard disarms.
    fireEvent.focus(input);
    expect(input.readOnly).toBe(false);

    // Modal re-opens (armKey flips) → guard re-arms so autofill is blocked again.
    rerender(<Harness armKey={false} />);
    expect((screen.getByLabelText(LABEL) as HTMLInputElement).readOnly).toBe(true);
  });

  it('shows the confirmation keyword as placeholder when empty', () => {
    render(<Harness />);
    const input = screen.getByLabelText(LABEL) as HTMLInputElement;
    expect(input.placeholder).toBe('DELETE');
    expect(input.value).toBe('');
  });
});
