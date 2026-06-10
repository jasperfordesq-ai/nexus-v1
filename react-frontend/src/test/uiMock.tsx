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
    if (DOM_SAFE.has(k) || k.startsWith('data-')) out[k] = v ?? undefined;
  }
  return out;
}

/** Pull human-visible text props so they render even without `children`. */
function textBits(props: AnyProps): ReactNode[] {
  return [props.label, props.title, props.placeholder, props.description, props.children]
    .filter((v) => v != null) as ReactNode[];
}

/** Invoke render-prop children — e.g. `<ModalContent>{(onClose) => …}</ModalContent>`. */
function resolveChildren(children: unknown): ReactNode {
  if (typeof children === 'function') {
    try {
      return (children as (arg: unknown) => ReactNode)(() => {});
    } catch {
      return null;
    }
  }
  return children as ReactNode;
}

const stubCache = new Map<string, unknown>();

function makeStub(name: string): unknown {
  const cached = stubCache.get(name);
  if (cached) return cached;
  const leaf = name.split('.').pop()!.toLowerCase();
  const isContainerPart = /group|section|item|trigger|popover|content|indicator|icon|value|label|description|error|heading|panel|list|menu/.test(leaf);
  const isButton = !isContainerPart && /button/.test(leaf);
  const isCheckable = !isButton && /^(switch|checkbox|radio)$/.test(leaf);
  const isInputLike =
    !isButton && !isCheckable && !isContainerPart &&
    /input|textarea|searchfield|textfield|numberfield|datefield|timeinput|timefield|datepicker|daterangepicker|colorfield|combobox|autocomplete/.test(leaf);
  const isStatus = /skeleton|spinner|loading/.test(leaf);
  // Overlay roots (not their compound sub-parts) honor an explicit closed state.
  const isOverlayRoot = /^(modal|drawer|alertdialog|dialog)$/.test(leaf);

  const Stub = React.forwardRef<HTMLElement, AnyProps>((props, ref) => {
    const { onPress, onChange, onValueChange, onClick, onKeyDown } = props as Record<string, unknown>;
    const children = resolveChildren(props.children);
    const label = props.label as ReactNode;
    const errorMessage = props.errorMessage as ReactNode;
    const description = props.description as ReactNode;

    // Honor a controlled closed overlay so "does not render when isOpen=false" works.
    // Only acts when isOpen is explicitly false — uncontrolled usage still renders.
    if (isOverlayRoot && props.isOpen === false) return null;

    if (isCheckable) {
      const handle = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (typeof onChange === 'function') (onChange as (ev: unknown) => void)(e);
        if (typeof onValueChange === 'function') (onValueChange as (v: boolean) => void)(e.target.checked);
      };
      const hasHandler = typeof onChange === 'function' || typeof onValueChange === 'function';
      const input = React.createElement('input', {
        ref: ref as React.Ref<HTMLInputElement>,
        type: leaf === 'radio' ? 'radio' : 'checkbox',
        ...domSafe(props),
        checked: (props.isSelected as boolean) ?? (props.isChecked as boolean) ?? undefined,
        onChange: hasHandler ? handle : undefined,
        readOnly: !hasHandler ? true : undefined,
      });
      const checkLabel = label ?? children;
      return checkLabel != null ? React.createElement('label', null, input, checkLabel) : input;
    }

    if (isInputLike) {
      const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (typeof onChange === 'function') (onChange as (ev: unknown) => void)(e);
        if (typeof onValueChange === 'function') (onValueChange as (v: string) => void)(e.target.value);
      };
      const hasHandler = typeof onChange === 'function' || typeof onValueChange === 'function';
      const input = React.createElement('input', {
        ref: ref as React.Ref<HTMLInputElement>,
        ...domSafe(props),
        onChange: hasHandler ? handleChange : undefined,
        readOnly: props.readOnly === true || !hasHandler ? true : undefined,
      });
      if (label == null && errorMessage == null && description == null) return input;
      // Wrap so the label is an accessible name (getByLabelText) and validation text shows.
      return React.createElement(
        React.Fragment,
        null,
        label != null ? React.createElement('label', null, label, input) : input,
        description != null ? React.createElement('span', null, description) : null,
        errorMessage != null ? React.createElement('span', null, errorMessage) : null,
      );
    }

    if (isButton) {
      return React.createElement(
        'button',
        {
          ref: ref as React.Ref<HTMLButtonElement>,
          type: 'button',
          ...domSafe(props),
          // `Button as={Link} to="...">` — surface the router destination as an
          // href attribute so tests can still assert where the button navigates.
          href: (props.href as string | undefined) ?? (typeof props.to === 'string' ? props.to : undefined),
          onClick: (typeof onPress === 'function' ? onPress : onClick) as (() => void) | undefined,
          onKeyDown: onKeyDown as ((e: unknown) => void) | undefined,
          disabled: props.isDisabled === true || props.disabled === true || undefined,
        },
        children as ReactNode,
      );
    }

    const extra: Record<string, unknown> = { ref, ...domSafe(props) };
    if (isStatus && extra.role == null) extra.role = 'status';
    if (name === 'GlassCard' && extra['data-testid'] == null) extra['data-testid'] = 'glass-card';
    if (typeof onPress === 'function') extra.onClick = onPress;
    else if (typeof onClick === 'function') extra.onClick = onClick;
    if (typeof onKeyDown === 'function') extra.onKeyDown = onKeyDown;
    if (errorMessage != null) {
      return React.createElement('div', extra, ...textBits({ ...props, children }), React.createElement('span', { key: '__err' }, errorMessage));
    }

    return React.createElement('div', extra, ...textBits({ ...props, children }));
  });
  Stub.displayName = `UiMock(${name})`;
  // Wrap so compound sub-components (Card.Content, Chip.Label, NumberField.Group,
  // Breadcrumbs.Item, SearchField.Input, …) resolve to nested stubs instead of
  // undefined — otherwise React throws "Element type is invalid".
  const wrapped = new Proxy(Stub, {
    get(target, prop, receiver) {
      if (typeof prop === 'string' && /^[A-Z]/.test(prop) && !(prop in target)) {
        return makeStub(`${name}.${prop}`);
      }
      return Reflect.get(target, prop, receiver);
    },
  });
  stubCache.set(name, wrapped);
  return wrapped;
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
