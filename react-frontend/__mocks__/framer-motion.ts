// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Centralized Vitest manual mock for framer-motion.
 *
 * Place this file at react-frontend/__mocks__/framer-motion.ts so that
 * `vi.mock('framer-motion')` (with no factory) resolves to this file
 * automatically via Vitest's manual-mock resolution.
 *
 * All motion.* elements render as their equivalent HTML tag, stripping
 * framer-motion-specific props (variants, initial, animate, exit, layout,
 * whileHover, whileTap, whileFocus, whileDrag, whileInView, transition,
 * layoutId, drag, dragConstraints, dragElastic) to avoid React DOM warnings.
 */

import React from 'react';

// Motion-specific props to strip before forwarding to the DOM element.
const MOTION_PROPS = new Set([
  'variants',
  'initial',
  'animate',
  'exit',
  'layout',
  'layoutId',
  'whileHover',
  'whileTap',
  'whileFocus',
  'whileDrag',
  'whileInView',
  'transition',
  'drag',
  'dragConstraints',
  'dragElastic',
  'dragMomentum',
  'onDragStart',
  'onDragEnd',
  'onAnimationStart',
  'onAnimationComplete',
  'onLayoutAnimationStart',
  'onLayoutAnimationComplete',
  'transformTemplate',
  'custom',
  'inherit',
]);

type MotionProps = {
  children?: React.ReactNode;
  [key: string]: unknown;
};

function createMotionComponent(tag: string) {
  return function MotionComponent({ children, ...props }: MotionProps) {
    const domProps: Record<string, unknown> = {};
    for (const [key, value] of Object.entries(props)) {
      if (!MOTION_PROPS.has(key)) {
        domProps[key] = value;
      }
    }
    return React.createElement(tag, domProps, children);
  };
}

// Proxy that returns a motion component for any HTML tag accessed.
export const motion = new Proxy({} as Record<string, ReturnType<typeof createMotionComponent>>, {
  get(_target, tag: string) {
    return createMotionComponent(typeof tag === 'string' ? tag : 'div');
  },
});

// AnimatePresence — just renders its children.
export function AnimatePresence({ children }: { children?: React.ReactNode }) {
  return React.createElement(React.Fragment, null, children);
}

// Hooks — return no-op values suitable for test environments.
export function useAnimation() {
  return {
    start: () => Promise.resolve(),
    stop: () => {},
    set: () => {},
    mount: () => () => {},
  };
}

export function useMotionValue(initial: number) {
  return {
    get: () => initial,
    set: () => {},
    onChange: () => () => {},
    destroy: () => {},
  };
}

export function useTransform() {
  return {
    get: () => 0,
    set: () => {},
    onChange: () => () => {},
    destroy: () => {},
  };
}

export function useSpring(value: number) {
  return {
    get: () => value,
    set: () => {},
    onChange: () => () => {},
    destroy: () => {},
  };
}

export function useScroll() {
  return {
    scrollX: { get: () => 0, onChange: () => () => {} },
    scrollY: { get: () => 0, onChange: () => () => {} },
    scrollXProgress: { get: () => 0, onChange: () => () => {} },
    scrollYProgress: { get: () => 0, onChange: () => () => {} },
  };
}

export function useInView() {
  return true;
}

export function useReducedMotion() {
  return false;
}

export function useCycle<T>(...items: T[]) {
  return [items[0], () => {}] as [T, () => void];
}

export function useVelocity() {
  return { get: () => 0, onChange: () => () => {} };
}

export function useDragControls() {
  return { start: () => {} };
}

export const MotionConfig = ({ children }: { children?: React.ReactNode }) =>
  React.createElement(React.Fragment, null, children);

export const LazyMotion = ({ children }: { children?: React.ReactNode }) =>
  React.createElement(React.Fragment, null, children);

export const LayoutGroup = ({ children }: { children?: React.ReactNode }) =>
  React.createElement(React.Fragment, null, children);

export const Reorder = {
  Group: ({ children, ...props }: MotionProps) => React.createElement('ul', props, children),
  Item: ({ children, ...props }: MotionProps) => React.createElement('li', props, children),
};

export default { motion, AnimatePresence };
