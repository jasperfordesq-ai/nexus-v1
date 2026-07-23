// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for the ui/Dropdown wrapper components.
 *
 * HeroUI Dropdown menus render into a portal.  Opening them in tests requires
 * passing `defaultOpen` to <Dropdown> (RAC-backed) so the popover is already
 * mounted when the component first renders — no user-event click needed.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import {
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  DropdownSection,
} from './Dropdown';
import { Button } from './Button';

vi.mock('@/contexts', () => createMockContexts());

// cn helper used internally — no external deps to mock
vi.mock(import('@/lib/helpers'), async (importOriginal) => ({
  ...(await importOriginal()),
  cn: (...args: (string | undefined | false)[]) => args.filter(Boolean).join(' '),
}));

beforeEach(() => {
  vi.clearAllMocks();
});

// ─── helpers ────────────────────────────────────────────────────────────────

function renderOpenDropdown(items: React.ReactNode, selectionMode?: 'single' | 'multiple' | 'none') {
  return render(
    <Dropdown defaultOpen>
      <DropdownTrigger>
        <button>Open</button>
      </DropdownTrigger>
      <DropdownMenu selectionMode={selectionMode}>
        {items}
      </DropdownMenu>
    </Dropdown>,
  );
}

// ─── Dropdown + DropdownTrigger ──────────────────────────────────────────────

describe('Dropdown / DropdownTrigger', () => {
  it('renders the trigger element', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
    render(
      <Dropdown>
        <DropdownTrigger>
          <button>Menu</button>
        </DropdownTrigger>
        <DropdownMenu>
          <DropdownItem id="a">Item A</DropdownItem>
        </DropdownMenu>
      </Dropdown>,
    );
    expect(screen.getByRole('button', { name: 'Menu' })).toBeInTheDocument();
    expect(warn).not.toHaveBeenCalledWith(expect.stringContaining('PressResponder'));
    warn.mockRestore();
  });

  it('clones className onto a valid trigger child', () => {
    render(
      <Dropdown>
        <DropdownTrigger className="extra-class">
          <button className="base-class">Trigger</button>
        </DropdownTrigger>
        <DropdownMenu>
          <DropdownItem id="x">X</DropdownItem>
        </DropdownMenu>
      </Dropdown>,
    );
    const btn = screen.getByRole('button', { name: 'Trigger' });
    expect(btn.className).toContain('base-class');
    expect(btn.className).toContain('extra-class');
  });

  it('adapts non-pressable content with one official trigger button', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
    render(
      <Dropdown>
        <DropdownTrigger><span>Custom menu</span></DropdownTrigger>
        <DropdownMenu>
          <DropdownItem id="custom">Custom item</DropdownItem>
        </DropdownMenu>
      </Dropdown>,
    );

    const button = screen.getByRole('button', { name: 'Custom menu' });
    expect(button.querySelector('button')).toBeNull();
    expect(warn).not.toHaveBeenCalledWith(expect.stringContaining('PressResponder'));
    warn.mockRestore();
  });

  it('uses a project Button directly as the documented pressable trigger', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
    render(
      <Dropdown>
        <DropdownTrigger><Button>Project menu</Button></DropdownTrigger>
        <DropdownMenu><DropdownItem id="project">Project item</DropdownItem></DropdownMenu>
      </Dropdown>,
    );

    const button = screen.getByRole('button', { name: 'Project menu' });
    expect(button.querySelector('button')).toBeNull();
    expect(warn).not.toHaveBeenCalledWith(expect.stringContaining('PressResponder'));
    warn.mockRestore();
  });

  it('honours shouldBlockScroll=false through the supported non-modal popover contract', () => {
    render(
      <Dropdown defaultOpen shouldBlockScroll={false}>
        <DropdownTrigger>
          <button>Non-blocking menu</button>
        </DropdownTrigger>
        <DropdownMenu>
          <DropdownItem id="item">Item</DropdownItem>
        </DropdownMenu>
      </Dropdown>,
    );

    expect(screen.getByText('Item')).toBeInTheDocument();
    expect(document.documentElement.style.overflow).not.toBe('hidden');
  });
});

// ─── DropdownMenu (portal items) ────────────────────────────────────────────

describe('DropdownMenu items', () => {
  it('renders menu items in portal when dropdown is open', () => {
    renderOpenDropdown(
      <>
        <DropdownItem id="edit">Edit</DropdownItem>
        <DropdownItem id="delete">Delete</DropdownItem>
      </>,
    );
    expect(screen.getByText('Edit')).toBeInTheDocument();
    expect(screen.getByText('Delete')).toBeInTheDocument();
  });

  it('renders a disabled item', () => {
    renderOpenDropdown(
      <DropdownItem id="disabled" isDisabled>Disabled</DropdownItem>,
    );
    const item = screen.getByText('Disabled').closest('[aria-disabled]');
    expect(item).toBeInTheDocument();
  });

  it('renders item with description', () => {
    renderOpenDropdown(
      <DropdownItem id="desc" description="Some details">Label</DropdownItem>,
    );
    expect(screen.getByText('Label')).toBeInTheDocument();
    expect(screen.getByText('Some details')).toBeInTheDocument();
  });

  it('renders danger variant item', () => {
    renderOpenDropdown(
      <DropdownItem id="del" color="danger">Danger</DropdownItem>,
    );
    // Item should render without throwing
    expect(screen.getByText('Danger')).toBeInTheDocument();
  });

  it('renders shortcut alongside item', () => {
    renderOpenDropdown(
      <DropdownItem id="cmd" shortcut="⌘K">Search</DropdownItem>,
    );
    expect(screen.getByText('Search')).toBeInTheDocument();
    expect(screen.getByText('⌘K')).toBeInTheDocument();
  });
});

// ─── DropdownSection ─────────────────────────────────────────────────────────

describe('DropdownSection', () => {
  it('renders section title and children', () => {
    renderOpenDropdown(
      <DropdownSection title="Actions">
        <DropdownItem id="a1">Copy</DropdownItem>
        <DropdownItem id="a2">Paste</DropdownItem>
      </DropdownSection>,
    );
    expect(screen.getByText('Actions')).toBeInTheDocument();
    expect(screen.getByText('Copy')).toBeInTheDocument();
    expect(screen.getByText('Paste')).toBeInTheDocument();
  });
});

// ─── selectionMode indicator ─────────────────────────────────────────────────

describe('selectionMode context', () => {
  it('renders without crashing in single-selection mode', () => {
    // Skipped assertion on checkmark DOM (indicator is a HeroUI internal slot)
    // We simply verify no render error occurs.
    renderOpenDropdown(
      <DropdownItem id="opt1">Option 1</DropdownItem>,
      'single',
    );
    expect(screen.getByText('Option 1')).toBeInTheDocument();
  });

  it('renders without crashing in multiple-selection mode', () => {
    renderOpenDropdown(
      <>
        <DropdownItem id="opt1">Option A</DropdownItem>
        <DropdownItem id="opt2">Option B</DropdownItem>
      </>,
      'multiple',
    );
    expect(screen.getByText('Option A')).toBeInTheDocument();
    expect(screen.getByText('Option B')).toBeInTheDocument();
  });
});

// ─── isReadOnly maps to isDisabled ───────────────────────────────────────────

describe('DropdownItem isReadOnly', () => {
  it('treats isReadOnly the same as isDisabled (item is aria-disabled)', () => {
    renderOpenDropdown(
      <DropdownItem id="ro" isReadOnly>Read Only</DropdownItem>,
    );
    const item = screen.getByText('Read Only').closest('[aria-disabled]');
    expect(item).toBeInTheDocument();
  });
});
