// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it, vi } from 'vitest';
import { act, renderHook } from '@testing-library/react';
import { useDisclosure } from './useDisclosure';

describe('useDisclosure (uncontrolled)', () => {
  it('starts closed by default', () => {
    const { result } = renderHook(() => useDisclosure());
    expect(result.current.isOpen).toBe(false);
    expect(result.current.isControlled).toBe(false);
  });

  it('honours defaultOpen', () => {
    const { result } = renderHook(() => useDisclosure({ defaultOpen: true }));
    expect(result.current.isOpen).toBe(true);
  });

  it('onOpen opens and fires the onOpen callback', () => {
    const onOpen = vi.fn();
    const { result } = renderHook(() => useDisclosure({ onOpen }));
    act(() => result.current.onOpen());
    expect(result.current.isOpen).toBe(true);
    expect(onOpen).toHaveBeenCalledTimes(1);
  });

  it('onClose closes and fires the onClose callback', () => {
    const onClose = vi.fn();
    const { result } = renderHook(() => useDisclosure({ defaultOpen: true, onClose }));
    act(() => result.current.onClose());
    expect(result.current.isOpen).toBe(false);
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('onOpenChange(true/false) sets state and fires the matching callback', () => {
    const onOpen = vi.fn();
    const onClose = vi.fn();
    const { result } = renderHook(() => useDisclosure({ onOpen, onClose }));

    act(() => result.current.onOpenChange(true));
    expect(result.current.isOpen).toBe(true);
    expect(onOpen).toHaveBeenCalledTimes(1);

    act(() => result.current.onOpenChange(false));
    expect(result.current.isOpen).toBe(false);
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('onOpenChange() with no argument toggles open state', () => {
    const { result } = renderHook(() => useDisclosure());
    act(() => result.current.onOpenChange());
    expect(result.current.isOpen).toBe(true);
    act(() => result.current.onOpenChange());
    expect(result.current.isOpen).toBe(false);
  });
});

describe('useDisclosure prop getters', () => {
  it('getButtonProps exposes aria state and merges extra props', () => {
    const { result } = renderHook(() => useDisclosure({ id: 'panel-1' }));
    const props = result.current.getButtonProps({ 'data-x': 'y' });
    expect(props['aria-controls']).toBe('panel-1');
    expect(props['aria-expanded']).toBe(false);
    expect(props['data-x']).toBe('y');
    expect(typeof props.onClick).toBe('function');
  });

  it('getDisclosureProps hides content while closed and exposes the id', () => {
    const { result } = renderHook(() => useDisclosure({ id: 'panel-2' }));
    const props = result.current.getDisclosureProps();
    expect(props.hidden).toBe(true);
    expect(props.id).toBe('panel-2');
  });

  it('reflects the open state in the getters after opening', () => {
    const { result } = renderHook(() => useDisclosure({ id: 'panel-3' }));
    act(() => result.current.onOpen());
    expect(result.current.getButtonProps()['aria-expanded']).toBe(true);
    expect(result.current.getDisclosureProps().hidden).toBe(false);
  });
});

describe('useDisclosure (controlled)', () => {
  it('marks itself controlled when isOpen is supplied', () => {
    const { result } = renderHook(() => useDisclosure({ isOpen: true }));
    expect(result.current.isControlled).toBe(true);
    expect(result.current.isOpen).toBe(true);
  });
});
