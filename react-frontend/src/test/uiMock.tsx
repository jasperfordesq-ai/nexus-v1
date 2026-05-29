// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Shared test mock for `@/components/ui`.
 *
 * Vitest 3 throws when a `vi.mock` factory omits an export the code accesses.
 * Many page/component tests hand-rolled incomplete inline factories that
 * predate later UI imports, so they break en masse under v3. Instead of
 * re-enumerating every primitive in every test file, point the mock here:
 *
 *   vi.mock('@/components/ui', async () => (await import('@/test/uiMock')).uiMock);
 *
 * The mock returns a Proxy that produces a sensible lightweight stub for ANY
 * accessed export, so it never throws on a missing name. Stubs render their
 * children (plus common text props) and forward `onPress` → `onClick`, which
 * satisfies the overwhelming majority of `getByText` / `getByRole` assertions.
 */

import React, { type ReactNode } from 'react';

type AnyProps = Record<string, unknown> & { children?: ReactNode };

// Props that are safe to forward to a native DOM element.
const DOM_SAFE = new Set([
  'id', 'className', 'style', 'role', 'type', 'name', 'href', 'title',
  'placeholder', 'value', 'defaultValue', 'checked', 'defaultChecked',
  'disabled', 'readOnly', 'required', 'min', 'max', 'step', 'rows', 'cols',
  'aria-label', 'aria-labelledby', 'aria-describedby', 'aria-hidden',
  'aria-current', 'aria-expanded', 'aria-controls', 'data-testid',
]);

function domSafe(props: AnyProps): Record<string, unknown> {
  const out: Record<string, unknown> = {};
  for (const [k, v] of Object.entries(props)) {
    if (DOM_SAFE.has(k) || k.startsWith('data-')) out[k] = v;
  }
  return out;
}

/** Pull human-visible text props so they render even without `children`. */
function textBits(props: AnyProps): ReactNode[] {
  return [props.label, props.title, props.placeholder, props.description, props.children]
    .filter((v) => v != null) as ReactNode[];
}

function makeStub(name: string) {
  const lower = name.toLowerCase();
  const isInputLike = /input|textarea|searchfield|textfield|numberfield|datefield|timeinput|datepicker|daterangepicker|colorfield/.test(lower);
  const isStatus = /skeleton|spinner|loading/.test(lower);
  const isButton = /^(button|glassbutton|closebutton|togglebutton)/.test(lower) || lower === 'iconbutton';

  const Stub = React.forwardRef<HTMLElement, AnyProps>((props, ref) => {
    const { children, onPress, onChange, onValueChange } = props;

    if (isInputLike) {
      const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (typeof onChange === 'function') (onChange as (ev: unknown) => void)(e);
        if (typeof onValueChange === 'function') (onValueChange as (v: string) => void)(e.target.value);
      };
      const hasHandler = typeof onChange === 'function' || typeof onValueChange === 'function';
      return React.createElement('input', {
        ref: ref as React.Ref<HTMLInputElement>,
        ...domSafe(props),
        onChange: hasHandler ? handleChange : undefined,
        readOnly: props.readOnly === true || !hasHandler ? true : undefined,
      });
    }

    if (isButton) {
      return React.createElement(
        'button',
        {
          ref: ref as React.Ref<HTMLButtonElement>,
          type: 'button',
          ...domSafe(props),
          onClick: typeof onPress === 'function' ? (onPress as () => void) : undefined,
          disabled: props.isDisabled === true || props.disabled === true || undefined,
        },
        children as ReactNode,
      );
    }

    const extra: Record<string, unknown> = { ref, ...domSafe(props) };
    if (isStatus && extra.role == null) extra.role = 'status';
    if (name === 'GlassCard' && extra['data-testid'] == null) extra['data-testid'] = 'glass-card';
    if (typeof onPress === 'function') extra.onClick = onPress;

    return React.createElement('div', extra, ...textBits(props));
  });
  Stub.displayName = `UiMock(${name})`;
  return Stub;
}

// Cache one stub per name so identity is stable across renders.
const cache = new Map<string, unknown>();

const handler: ProxyHandler<Record<string, unknown>> = {
  get(_target, prop: string | symbol) {
    if (typeof prop === 'symbol') {
      if (prop === Symbol.toStringTag) return 'Module';
      return undefined;
    }
    if (prop === '__esModule') return true;
    if (prop === 'default') return undefined;
    if (prop === 'ICON_MAP') return {};
    if (prop === 'ICON_NAMES') return [];
    if (cache.has(prop)) return cache.get(prop);

    let value: unknown;
    if (prop === 'useDisclosure') {
      value = () => ({ isOpen: false, onOpen: () => {}, onClose: () => {}, onOpenChange: () => {}, onToggle: () => {} });
    } else if (prop === 'useConfirm') {
      value = () => () => Promise.resolve(true);
    } else if (/^use[A-Z]/.test(prop)) {
      value = () => ({});
    } else {
      // Component (or compound component — attach common sub-parts lazily).
      value = makeStub(prop);
    }
    cache.set(prop, value);
    return value;
  },
  has() {
    return true;
  },
};

export const uiMock = new Proxy({}, handler) as Record<string, unknown>;
