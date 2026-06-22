// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import {
  Drawer,
  DrawerContent,
  DrawerHeader,
  DrawerBody,
  DrawerFooter,
} from './Drawer';

// Drawer wraps HeroUIDrawer which renders into a portal.
// We query via screen (searches document.body incl. portals).

describe('Drawer — open/closed gate', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders children when isOpen=true', () => {
    render(
      <Drawer isOpen>
        <DrawerContent>
          <DrawerBody><p>drawer body</p></DrawerBody>
        </DrawerContent>
      </Drawer>
    );
    expect(screen.getByText('drawer body')).toBeInTheDocument();
  });

  it('does not render drawer dialog when isOpen=false', () => {
    render(
      <Drawer isOpen={false}>
        <DrawerContent>
          <DrawerBody><p>hidden content</p></DrawerBody>
        </DrawerContent>
      </Drawer>
    );
    expect(screen.queryByText('hidden content')).not.toBeInTheDocument();
  });
});

describe('Drawer — compound sections', () => {
  it('renders DrawerHeader children', () => {
    render(
      <Drawer isOpen>
        <DrawerContent>
          <DrawerHeader><h2>Drawer Title</h2></DrawerHeader>
          <DrawerBody><p>body</p></DrawerBody>
        </DrawerContent>
      </Drawer>
    );
    expect(screen.getByText('Drawer Title')).toBeInTheDocument();
  });

  it('renders DrawerBody children', () => {
    render(
      <Drawer isOpen>
        <DrawerContent>
          <DrawerBody><span>Body content</span></DrawerBody>
        </DrawerContent>
      </Drawer>
    );
    expect(screen.getByText('Body content')).toBeInTheDocument();
  });

  it('renders DrawerFooter children', () => {
    render(
      <Drawer isOpen>
        <DrawerContent>
          <DrawerBody><p>body</p></DrawerBody>
          <DrawerFooter><button>Confirm action</button></DrawerFooter>
        </DrawerContent>
      </Drawer>
    );
    // Use a unique name that won't collide with the built-in CloseTrigger button
    expect(screen.getByRole('button', { name: /confirm action/i })).toBeInTheDocument();
  });
});

describe('Drawer — render prop children', () => {
  it('passes close function to render-prop children', () => {
    const onClose = vi.fn();
    render(
      <Drawer isOpen onClose={onClose} onOpenChange={(open) => { if (!open) onClose(); }}>
        <DrawerContent>
          {(close) => (
            <DrawerBody>
              <button onClick={close}>Close via prop</button>
            </DrawerBody>
          )}
        </DrawerContent>
      </Drawer>
    );
    expect(screen.getByRole('button', { name: /close via prop/i })).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: /close via prop/i }));
    expect(onClose).toHaveBeenCalled();
  });
});

describe('Drawer — onClose / onOpenChange', () => {
  it('renders at least one button (built-in close trigger) when isOpen=true', () => {
    const onClose = vi.fn();
    render(
      <Drawer isOpen onClose={onClose}>
        <DrawerContent aria-label="Test drawer">
          <DrawerHeader><span>Title</span></DrawerHeader>
          <DrawerBody><p>content</p></DrawerBody>
        </DrawerContent>
      </Drawer>
    );
    // HeroUI renders a CloseTrigger button inside the dialog
    const allButtons = screen.queryAllByRole('button');
    expect(allButtons.length).toBeGreaterThan(0);
  });
});
