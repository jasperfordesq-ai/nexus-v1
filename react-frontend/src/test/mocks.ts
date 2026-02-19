// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Shared test mocks for common dependencies
 */
import React from 'react';

/**
 * Framer Motion mock - creates a Proxy that handles all motion.* components
 */
const motionProps = [
  'variants', 'initial', 'animate', 'exit', 'transition',
  'whileHover', 'whileTap', 'whileInView', 'whileFocus', 'whileDrag',
  'layout', 'layoutId', 'viewport', 'drag', 'dragConstraints',
  'dragElastic', 'dragMomentum', 'onDragStart', 'onDragEnd',
  'onAnimationStart', 'onAnimationComplete', 'style',
];

export const framerMotionMock = {
  motion: new Proxy({}, {
    get: (_target: object, prop: string | symbol) => {
      return React.forwardRef(({ children, ...props }: any, ref: any) => {
        const clean: Record<string, unknown> = {};
        for (const [k, v] of Object.entries(props)) {
          if (!motionProps.includes(k)) clean[k] = v;
        }
        const Tag = typeof prop === 'string' ? prop : 'div';
        return React.createElement(Tag, { ...clean, ref }, children);
      });
    },
  }),
  AnimatePresence: ({ children }: { children: React.ReactNode }) => React.createElement(React.Fragment, null, children),
  useAnimation: () => ({ start: () => Promise.resolve() }),
  useInView: () => true,
  useMotionValue: (initial: number) => ({ get: () => initial, set: () => {} }),
  useTransform: () => ({ get: () => 0 }),
  useSpring: () => ({ get: () => 0 }),
};
