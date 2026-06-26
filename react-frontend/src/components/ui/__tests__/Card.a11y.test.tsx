// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Regression test: a clickable Card must be keyboard-operable.
 *
 * A click/press handler on the default Card renders a <div>. Without button
 * semantics that div is mouse-only (WCAG 2.1.1 Keyboard). The wrapper now
 * exposes interactive cards as role="button" + tabIndex=0 and activates them
 * on Enter/Space — while leaving static cards and caller-chosen `as` elements
 * untouched.
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { Card } from '../Card';

describe('Card — keyboard operability', () => {
  it('exposes a pressable card as a keyboard-operable button', () => {
    const onPress = vi.fn();
    render(<Card onPress={onPress}>Pressable</Card>);

    const card = screen.getByRole('button');
    expect(card).toHaveAttribute('tabindex', '0');

    fireEvent.keyDown(card, { key: 'Enter' });
    expect(onPress).toHaveBeenCalledTimes(1);

    fireEvent.keyDown(card, { key: ' ' });
    expect(onPress).toHaveBeenCalledTimes(2);
  });

  it('does not add button semantics to a static (non-interactive) card', () => {
    render(<Card>Static content</Card>);
    expect(screen.queryByRole('button')).toBeNull();
  });

  it('does not make a disabled pressable card focusable', () => {
    const onPress = vi.fn();
    render(
      <Card onPress={onPress} isDisabled>
        Disabled
      </Card>,
    );
    expect(screen.queryByRole('button')).toBeNull();
  });
});
