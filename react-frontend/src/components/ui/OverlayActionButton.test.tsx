// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it, vi } from 'vitest';
import userEvent from '@testing-library/user-event';
import { render, screen } from '@/test/test-utils';
import {
  OVERLAY_ACTION_REVEAL_CLASSES,
  OVERLAY_ACTION_TARGET_CLASSES,
  OverlayActionButton,
} from './OverlayActionButton';

describe('OverlayActionButton', () => {
  it('guarantees a 44px target and touch/focus-visible discovery classes', () => {
    render(<OverlayActionButton aria-label="Test action">×</OverlayActionButton>);

    const button = screen.getByRole('button', { name: 'Test action' });
    for (const className of OVERLAY_ACTION_TARGET_CLASSES.split(' ')) {
      expect(button).toHaveClass(className);
    }
    for (const className of OVERLAY_ACTION_REVEAL_CLASSES.split(' ')) {
      expect(button).toHaveClass(className);
    }
  });

  it('retains the target while allowing an always-visible overlay action', () => {
    render(
      <OverlayActionButton aria-label="Persistent action" revealOnFinePointer={false}>
        ×
      </OverlayActionButton>,
    );

    const button = screen.getByRole('button', { name: 'Persistent action' });
    expect(button).toHaveClass('size-11', 'min-h-11', 'min-w-11');
    expect(button).not.toHaveClass('pointer-fine:opacity-0');
  });

  it('preserves HeroUI press semantics', async () => {
    const onPress = vi.fn();
    const user = userEvent.setup();
    render(
      <OverlayActionButton aria-label="Press action" onPress={onPress}>
        ×
      </OverlayActionButton>,
    );

    await user.click(screen.getByRole('button', { name: 'Press action' }));
    expect(onPress).toHaveBeenCalledTimes(1);
  });
});
